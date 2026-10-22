<?php
declare(strict_types=1);

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/Http.php';
require_once __DIR__ . '/UsuarioRepository.php';
require_once __DIR__ . '/Logger.php';
require_once __DIR__ . '/Permisos.php';

/**
 * Autenticación por token Bearer para integraciones externas (curl).
 *
 * Convive con la auth por sesión (core/Auth.php):
 *   - Si la petición trae `Authorization: Bearer <token>` (o `X-Api-Key`),
 *     TokenAuth::boot() valida el token y deja un actor staff en memoria.
 *   - Auth::staff()/actor() devuelven ese actor cuando TokenAuth::active().
 *   - Http::csrf() es no-op para peticiones autenticadas por token (el bearer
 *     ES la credencial; no hay cookie/sesión que proteger).
 *
 * Autorización efectiva (la aplican Auth::requireModulo / requireAccion):
 *   rol del usuario (RBAC de ui/config/secciones.php)
 *   ∩ clave ∈ api_endpoints_autorizados (allowlist global)
 *   ∩ clave ∈ scopes del token.
 *
 * El secreto NUNCA se guarda en claro: solo su SHA-256. El valor en claro se
 * devuelve una única vez al crear el token.
 */
final class TokenAuth
{
    private static bool $checked = false;
    private static ?array $actor = null;
    private static array $scopes = [];
    private static ?array $token = null;
    private static ?array $allowlist = null;

    /**
     * Detecta y valida el token de la petición actual. Idempotente.
     * Debe llamarse al inicio de los `_bootstrap.php` de los módulos expuestos.
     * Si no hay Bearer, no hace nada (flujo de sesión normal).
     */
    public static function boot(): void
    {
        if (self::$checked) {
            return;
        }
        self::$checked = true;

        if ((int) (env('API_TOKEN_ENABLED', '1') ?: 1) !== 1) {
            return;
        }

        $raw = self::bearer();
        if ($raw === '') {
            return;
        }

        if (!self::httpsOk()) {
            Http::fail('La API por token requiere HTTPS', 403);
        }

        $hash = hash('sha256', $raw);
        try {
            $st = db()->prepare('SELECT * FROM api_tokens WHERE token_hash = ? LIMIT 1');
            $st->execute([$hash]);
            $row = $st->fetch();
        } catch (Throwable $e) {
            error_log('[TokenAuth] ' . $e->getMessage());
            Http::fail('API por token no configurada', 503);
        }

        if (!$row || !hash_equals((string) $row['token_hash'], $hash)) {
            Http::fail('Token inválido', 401);
        }
        if ((int) $row['activo'] !== 1 || !empty($row['revoked_at'])) {
            Http::fail('Token revocado', 401);
        }
        if (strtotime((string) $row['expires_at']) < time()) {
            Http::fail('Token expirado', 401);
        }

        $u = (new UsuarioRepository(db()))->porId((int) $row['actor_id']);
        if (!$u || (int) $u['estado_activo'] !== 1) {
            Http::fail('Usuario del token inactivo', 401);
        }

        $sc = json_decode((string) ($row['scopes'] ?? '[]'), true);
        self::$scopes = is_array($sc)
            ? array_values(array_filter($sc, static fn($s) => is_string($s) && $s !== ''))
            : [];
        self::$token = $row;
        self::$actor = [
            'tipo'            => 'interno',
            'id'              => (string) $u['id'],
            'rol'             => (string) $u['rol'],
            'usuario'         => (string) $u['usuario'],
            'nombre_completo' => (string) ($u['nombre_completo'] ?? ''),
            'must_change'     => false,
            'token_id'        => (int) $row['id'],
            'token_prefijo'   => (string) $row['prefijo'],
        ];

        self::rateLimitOrFail((int) $row['id']);

        try {
            db()->prepare('UPDATE api_tokens SET last_used_at = NOW() WHERE id = ?')
                ->execute([(int) $row['id']]);
            Logger::log(
                'api_token',
                'api',
                (string) $u['id'],
                'Uso de token ' . $row['prefijo'] . ' · ' . Http::method() . ' ' . ($_SERVER['REQUEST_URI'] ?? '')
            );
        } catch (Throwable $e) {
            error_log('[TokenAuth] auditoría: ' . $e->getMessage());
        }
    }

    /** ¿La petición actual quedó autenticada por token? */
    public static function active(): bool
    {
        return self::$actor !== null;
    }

    /** Actor staff resuelto desde el token (o null). */
    public static function actor(): ?array
    {
        return self::$actor;
    }

    /** Scopes (claves) del token actual. */
    public static function scopes(): array
    {
        return self::$scopes;
    }

    /**
     * ¿El token activo tiene este scope exacto? Para scopes ESPECIALES que no son
     * gates de módulo/acción (p. ej. 'solicitudes.portal.actuar').
     */
    public static function hasScope(string $scope): bool
    {
        return self::active() && in_array($scope, self::$scopes, true);
    }

    /** Datos del token actual (sin el hash). */
    public static function token(): ?array
    {
        if (self::$token === null) {
            return null;
        }
        $t = self::$token;
        unset($t['token_hash']);
        return $t;
    }

    /**
     * ¿El token puede invocar esta clave (módulo o acción)?
     * - Sin token activo → true (el flujo de sesión no pasa por aquí).
     * - La clave debe estar en el allowlist global Y en los scopes del token.
     * - Un scope de acción (`a.b.c`) cubre sus módulos padre (`a`, `a.b`).
     */
    public static function scopePermite(string $clave): bool
    {
        if (!self::active()) {
            return true;
        }
        $clave = trim($clave);
        if ($clave === '') {
            return false;
        }
        $allow = self::allowlist();
        foreach (self::$scopes as $s) {
            if (!in_array($s, $allow, true)) {
                continue;
            }
            if ($s === $clave) {
                return true;
            }
            if (str_starts_with($s, $clave . '.')) {
                return true;
            }
            // El scope es una acción cuyo módulo-gate es $clave (prefijos distintos).
            if (Permisos::moduloRequerido($s) === $clave) {
                return true;
            }
        }
        return false;
    }

    /**
     * Genera un token nuevo (devuelve el secreto en claro, UNA sola vez).
     * @return array{secreto:string,prefijo:string,hash:string,expires_at:string,dias:int}
     */
    public static function generar(int $dias = 180): array
    {
        $max  = max(1, (int) (env('API_TOKEN_MAX_DIAS', '365') ?: 365));
        $dias = max(1, min($max, $dias));
        $secreto = bin2hex(random_bytes(32));
        return [
            'secreto'    => $secreto,
            'prefijo'    => substr($secreto, 0, 12),
            'hash'       => hash('sha256', $secreto),
            'expires_at' => date('Y-m-d H:i:s', time() + $dias * 86400),
            'dias'       => $dias,
        ];
    }

    /** Allowlist global (api_endpoints_autorizados activos), cacheada por request. */
    public static function allowlist(): array
    {
        if (self::$allowlist === null) {
            try {
                $rows = db()->query('SELECT clave FROM api_endpoints_autorizados WHERE activo = 1')->fetchAll();
                self::$allowlist = array_map(static fn($r) => (string) $r['clave'], $rows);
            } catch (Throwable $e) {
                error_log('[TokenAuth] allowlist: ' . $e->getMessage());
                self::$allowlist = [];
            }
        }
        return self::$allowlist;
    }

    /**
     * Límite de solicitudes por token (ventana fija). API_TOKEN_RATE_MAX <= 0 lo desactiva.
     * Nunca interrumpe el flujo si la tabla aún no existe (solo error_log).
     */
    private static function rateLimitOrFail(int $tokenId): void
    {
        $max = (int) (env('API_TOKEN_RATE_MAX', '120') ?: 120);
        if ($max <= 0) {
            return;
        }
        $win    = max(1, (int) (env('API_TOKEN_RATE_WINDOW', '60') ?: 60));
        $bucket = (string) intdiv(time(), $win);
        try {
            $pdo = db();
            $pdo->prepare(
                'INSERT INTO api_token_rate (token_id, ventana, usos) VALUES (?,?,1)
                 ON DUPLICATE KEY UPDATE usos = usos + 1'
            )->execute([$tokenId, $bucket]);
            $st = $pdo->prepare('SELECT usos FROM api_token_rate WHERE token_id = ? AND ventana = ?');
            $st->execute([$tokenId, $bucket]);
            if ((int) $st->fetchColumn() > $max) {
                Http::fail('Límite de solicitudes excedido. Intente de nuevo en unos segundos.', 429);
            }
            // Purga ocasional de ventanas viejas.
            if (random_int(1, 100) <= 2) {
                $pdo->prepare('DELETE FROM api_token_rate WHERE ventana < ?')
                    ->execute([(string) (intdiv(time(), $win) - 5)]);
            }
        } catch (Throwable $e) {
            error_log('[TokenAuth] rate-limit: ' . $e->getMessage());
        }
    }

    /** Token crudo de la petición (Bearer o X-Api-Key). */
    private static function bearer(): string
    {
        $h = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
        if (!is_string($h) || $h === '') {
            // Apache/mod_php puede no exponer Authorization en $_SERVER:
            // se recoge de getallheaders() como respaldo.
            $all = function_exists('getallheaders') ? getallheaders() : [];
            if (is_array($all)) {
                foreach ($all as $k => $v) {
                    if (strcasecmp((string) $k, 'Authorization') === 0) {
                        $h = (string) $v;
                        break;
                    }
                }
            }
        }
        if (is_string($h) && $h !== '' && preg_match('/^\s*Bearer\s+(.+?)\s*$/i', $h, $m)) {
            return trim($m[1]);
        }
        $k = $_SERVER['HTTP_X_API_KEY'] ?? '';
        return is_string($k) ? trim($k) : '';
    }

    /** HTTPS obligatorio en producción (o si API_TOKEN_HTTPS_ONLY=1). */
    private static function httpsOk(): bool
    {
        $default = app_env() === 'production' ? '1' : '0';
        if ((int) (env('API_TOKEN_HTTPS_ONLY', $default) ?: $default) !== 1) {
            return true;
        }
        if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
            return true;
        }
        if (($_SERVER['SERVER_PORT'] ?? null) == 443) {
            return true;
        }
        return strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
    }
}
