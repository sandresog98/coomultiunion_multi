<?php
declare(strict_types=1);

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/Http.php';

/**
 * Idempotencia por clave — prevención de doble envío / duplicidad (ROLL).
 *
 * Uso en endpoints mutadores (POST/PUT/DELETE), tras autenticar:
 *   Idempotencia::begin('<modulo>');
 *
 * Comportamiento:
 *   - Lee X-Idempotency-Key (header) o campo `idempotency_key` (JSON/form).
 *   - Mismo clave+modulo+actor dentro de la ventana (TTL) → replay de la
 *     respuesta previa SIN re-ejecutar la acción.
 *   - Primera ocurrencia → ejecuta la acción y persiste la respuesta JSON si
 *     la acción terminó en éxito (ok=true). Los errores NO se persisten, así
 *     el mismo clic corregido puede reintentar con la misma clave.
 *   - Sin clave en un mutador marcado → 400 (estricto). Para suavizar en un
 *     rollback: IDEMPOTENCIA_ESTRICTO=0 (solo dedup si viene clave).
 *
 * Carrera de concurrencia: la fila `respuesta IS NULL` marca "en proceso"
 * (UNIQUE clave); el request perdedor espera brevemente y, si el ganador ya
 * respondió, recibe esa respuesta. Nunca re-inserta.
 *
 * La tabla `idempotencia` no tiene FK/ENUM (§3) y la respuesta se guarda como
 * JSON literal para re-emitirla idéntica (junto con su código HTTP).
 */
final class Idempotencia
{
    private static ?string $clave = null;

    /**
     * Marca el arranque de una operación idempotente.
     * @throws void — corta con Http::json/ok/fail según el caso.
     */
    public static function begin(string $modulo): void
    {
        $clave = self::clave();
        $estricto = (int) (getenv('IDEMPOTENCIA_ESTRICTO') ?: 1) === 1;

        if ($clave === '') {
            if ($estricto) {
                Http::fail('Falta header X-Idempotency-Key para operaciones de mutación.', 400);
            }
            return; // modo suave: ejecuta sin dedup
        }

        $actor = self::actorUid();
        $ttl   = max(60, (int) (getenv('IDEMPOTENCIA_TTL') ?: 3600));
        self::prune($ttl);

        $pdo = db();
        $st = $pdo->prepare(
            'SELECT id, modulo, usuario, respuesta, http_code,
                    (created_at < (CURRENT_TIMESTAMP - INTERVAL ? SECOND)) AS vencida
               FROM idempotencia WHERE clave = ? LIMIT 1'
        );
        $st->execute([$ttl, $clave]);
        $row = $st->fetch();

        if ($row) {
            if ((int) $row['vencida'] === 1) {
                // Fuera de la ventana: se libera la clave y se re-ejecuta.
                $pdo->prepare('DELETE FROM idempotencia WHERE id = ?')->execute([(int) $row['id']]);
            } elseif (self::esMismaOperacion($row, $modulo, $actor)) {
                if ($row['respuesta'] !== null) {
                    self::rejugar($row['respuesta'], (int) ($row['http_code'] ?: 200));
                }
                // Fila en proceso (NULL) que quedó huérfana por un proceso caído.
                if (self::filaVieja($row['id'])) {
                    self::liberar($row['id']);
                } else {
                    if (self::esperarYRejugar($clave, $modulo, $actor)) {
                        Http::fail('Operación en proceso', 409);
                    }
                }
            } else {
                Http::fail('La clave idempotente ya fue usada en otra operación.', 409);
            }
        }

        // Alta del placeholder (atómica por el UNIQUE de clave).
        try {
            $pdo->prepare('INSERT INTO idempotencia (clave, modulo, usuario) VALUES (?, ?, ?)')
                ->execute([$clave, $modulo, $actor]);
        } catch (PDOException $e) {
            if ((int) $e->getCode() !== 23000) {
                throw $e;
            }
            // Perdió la carrera contra un request idéntico: esperar su respuesta.
            if (self::esperarYRejugar($clave, $modulo, $actor)) {
                Http::fail('Operación en proceso', 409);
            }
        }

        // Captura la respuesta JSON final y la persiste solo si terminó en éxito.
        ob_start();
        register_shutdown_function(static function () use ($clave, $modulo, $actor): void {
            try {
                $body = ob_get_clean();
                if ($body === false || $body === '') {
                    return;
                }
                $decoded = json_decode($body, true);
                if (is_array($decoded) && ($decoded['ok'] ?? false) === true) {
                    // Éxito: se persiste la respuesta para el replay.
                    db()->prepare(
                        'UPDATE idempotencia SET respuesta = ?, http_code = ?,
                                modulo = ?, usuario = ? WHERE clave = ? AND respuesta IS NULL'
                    )->execute([$body, http_response_code(), $modulo, $actor, $clave]);
                } else {
                    // Fracaso: se libera la clave (la fila quedó como placeholder NULL)
                    // para que el mismo clic corregido pueda reintentar con la misma clave.
                    db()->prepare('DELETE FROM idempotencia WHERE clave = ? AND respuesta IS NULL')
                        ->execute([$clave]);
                }
            } catch (Throwable $e) {
                error_log('[idempotencia] no se persistió la respuesta: ' . $e->getMessage());
            }
            if ($body !== '') {
                echo $body;
            }
        });
    }

    /** Espera la respuesta de un request idéntico en vuelo (y hace replay si llega). */
    private static function esperarYRejugar(string $clave, string $modulo, string $actor): bool
    {
        $pdo = db();
        for ($i = 0; $i < 10; $i++) {
            usleep(200000); // 0.2 s
            $st = $pdo->prepare('SELECT respuesta, http_code, modulo, usuario FROM idempotencia WHERE clave = ? LIMIT 1');
            $st->execute([$clave]);
            $r = $st->fetch();
            if ($r && (string) $r['modulo'] === $modulo && (string) $r['usuario'] === $actor) {
                if ($r['respuesta'] !== null) {
                    self::rejugar($r['respuesta'], (int) ($r['http_code'] ?: 200));
                }
            }
        }
        return false; // sigue en proceso → el caller responde 409
    }

    private static function rejugar(string $respuesta, int $codigo): void
    {
        $json = json_decode($respuesta, true);
        Http::json(is_array($json) ? $json : ['ok' => true, 'data' => null], $codigo);
    }

    private static function filaVieja(int $id): bool
    {
        $st = db()->prepare(
            'SELECT (created_at < (CURRENT_TIMESTAMP - INTERVAL 30 SECOND)) AS vieja
               FROM idempotencia WHERE id = ?'
        );
        $st->execute([$id]);
        $r = $st->fetch();
        return (int) ($r['vieja'] ?? 0) === 1;
    }

    private static function liberar(int $id): void
    {
        db()->prepare('DELETE FROM idempotencia WHERE id = ?')->execute([$id]);
    }

    private static function esMismaOperacion(array $row, string $modulo, string $actor): bool
    {
        return (string) ($row['modulo'] ?? '') === $modulo && (string) ($row['usuario'] ?? '') === $actor;
    }

    /** Clave idempotente: header o campo de cuerpo (JSON/form). */
    private static function clave(): string
    {
        if (self::$clave === null) {
            $k = trim((string) ($_SERVER['HTTP_X_IDEMPOTENCY_KEY'] ?? ''));
            if ($k === '') {
                $in = Http::input();
                $k = trim((string) ($in['idempotency_key'] ?? ''));
            }
            self::$clave = substr($k, 0, 64);
        }
        return self::$clave;
    }

    /** Actor normalizado para la clave compuesta (nunca se confía en el cliente). */
    private static function actorUid(): string
    {
        if (class_exists('Auth', false)) {
            $a = Auth::actor();
            if ($a !== null) {
                return 'interno:' . (string) $a['id'];
            }
        }
        if (class_exists('PortalAuth', false)) {
            $p = PortalAuth::actor();
            if (is_array($p) && isset($p['cedula'])) {
                return 'asociado:' . (string) $p['cedula'];
            }
        }
        return 'anon';
    }

    /** Purga de vencidos (solo ~2 % de los requests, para no escribir en cada uno). */
    private static function prune(int $ttl): void
    {
        if (random_int(1, 100) > 2) {
            return;
        }
        db()->prepare('DELETE FROM idempotencia WHERE created_at < (CURRENT_TIMESTAMP - INTERVAL ? SECOND)')
            ->execute([$ttl]);
    }
}