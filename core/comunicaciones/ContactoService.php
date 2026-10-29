<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/database.php';
require_once dirname(__DIR__) . '/Logger.php';
require_once dirname(__DIR__) . '/config/ConfigService.php';
require_once __DIR__ . '/PhoneNormalizer.php';

/**
 * Contactos, asociados y consentimiento (opt-in/opt-out) por canal.
 */
final class ContactoService
{
    private PDO $db;
    private ConfigService $cfg;

    public function __construct(?PDO $db = null, ?ConfigService $cfg = null)
    {
        $this->db  = $db ?? db();
        $this->cfg = $cfg ?? new ConfigService($this->db);
    }

    /** Busca asociados para el selector de la UI. */
    public function buscarAsociados(string $q, int $limit = 20): array
    {
        $q = trim($q);
        if ($q === '') {
            return [];
        }
        $like = '%' . $q . '%';
        $st = $this->db->prepare(
            "SELECT a.cedula, a.nombre, a.celular, a.telefono, a.email,
                    COALESCE(ac.estado_activo, 1) AS estado_activo,
                    COALESCE(ac.opt_in, 0) AS opt_in
               FROM vw_asociados a
               LEFT JOIN asociados_control ac ON ac.cedula = a.cedula
              WHERE a.cedula LIKE ? OR a.nombre LIKE ? OR a.celular LIKE ? OR a.telefono LIKE ? OR a.email LIKE ?
              ORDER BY a.nombre
              LIMIT ?"
        );
        $st->bindValue(1, $like);
        $st->bindValue(2, $like);
        $st->bindValue(3, $like);
        $st->bindValue(4, $like);
        $st->bindValue(5, $like);
        $st->bindValue(6, max(1, min(100, $limit)), PDO::PARAM_INT);
        $st->execute();
        $rows = $st->fetchAll();
        foreach ($rows as &$r) {
            $r['telefono'] = PhoneNormalizer::normalize((string) ($r['celular'] ?: $r['telefono'])) ?? '';
            $r['email'] = PhoneNormalizer::normalizeEmail((string) ($r['email'] ?? '')) ?? '';
        }
        return $rows;
    }

    public function fichaAsociado(string $cedula): ?array
    {
        $st = $this->db->prepare(
            "SELECT a.cedula, a.nombre, a.celular, a.telefono, a.email,
                    COALESCE(ac.estado_activo, 1) AS estado_activo,
                    COALESCE(ac.opt_in, 0) AS opt_in
               FROM vw_asociados a
               LEFT JOIN asociados_control ac ON ac.cedula = a.cedula
              WHERE a.cedula = ? LIMIT 1"
        );
        $st->execute([$cedula]);
        $r = $st->fetch();
        if (!$r) {
            return null;
        }
        $r['telefono'] = PhoneNormalizer::normalize((string) ($r['celular'] ?: $r['telefono'])) ?? '';
        $r['email'] = PhoneNormalizer::normalizeEmail((string) ($r['email'] ?? '')) ?? '';
        return $r;
    }

    /** Busca contactos ya registrados (para autocompletar). */
    public function buscarContactos(string $q, int $limit = 20): array
    {
        $q = trim($q);
        if ($q === '') {
            return [];
        }
        $like = '%' . $q . '%';
        $st = $this->db->prepare(
            "SELECT id, canal, direccion, nombre, asociado_cedula, opt_in, opt_out
               FROM com_contactos
              WHERE direccion LIKE ? OR nombre LIKE ? OR asociado_cedula LIKE ?
              ORDER BY fecha_actualizacion DESC, id DESC
              LIMIT ?"
        );
        $st->bindValue(1, $like);
        $st->bindValue(2, $like);
        $st->bindValue(3, $like);
        $st->bindValue(4, max(1, min(100, $limit)), PDO::PARAM_INT);
        $st->execute();
        return $st->fetchAll();
    }

    public function obtener(string $canal, string $direccion): ?array
    {
        $st = $this->db->prepare(
            "SELECT * FROM com_contactos WHERE canal = ? AND direccion = ? LIMIT 1"
        );
        $st->execute([$canal, $direccion]);
        return $st->fetch() ?: null;
    }

    public function obtenerPorId(int $id): ?array
    {
        $st = $this->db->prepare("SELECT * FROM com_contactos WHERE id = ? LIMIT 1");
        $st->execute([$id]);
        return $st->fetch() ?: null;
    }

    /**
     * Crea/actualiza el contacto sin tocar los flags de consentimiento.
     * @return int id del contacto
     */
    public function upsert(string $canal, string $direccion, array $data = []): int
    {
        $direccion = $this->normalizarDireccion($canal, $direccion);
        if ($direccion === '') {
            throw new DomainException('Dirección de contacto inválida.');
        }
        $this->db->prepare(
            "INSERT INTO com_contactos (canal, direccion, nombre, asociado_cedula, metadata_json)
             VALUES (?,?,?,?,?)
             ON DUPLICATE KEY UPDATE
                nombre = COALESCE(VALUES(nombre), nombre),
                asociado_cedula = COALESCE(VALUES(asociado_cedula), asociado_cedula),
                metadata_json = COALESCE(VALUES(metadata_json), metadata_json),
                fecha_actualizacion = NOW()"
        )->execute([
            $canal,
            $direccion,
            isset($data['nombre']) && trim((string) $data['nombre']) !== '' ? mb_substr(trim((string) $data['nombre']), 0, 150) : null,
            isset($data['asociado_cedula']) && trim((string) $data['asociado_cedula']) !== '' ? mb_substr(trim((string) $data['asociado_cedula']), 0, 20) : null,
            isset($data['metadata']) ? json_encode($data['metadata'], JSON_UNESCAPED_UNICODE) : null,
        ]);
        $st = $this->db->prepare("SELECT id FROM com_contactos WHERE canal = ? AND direccion = ? LIMIT 1");
        $st->execute([$canal, $direccion]);
        return (int) ($st->fetchColumn() ?: 0);
    }

    /**
     * Upsert de contactos en lote (mismo canal). Devuelve mapa dirección normalizada => id.
     * @param array<int,array{direccion:string,nombre?:?string,asociado_cedula?:?string,metadata?:mixed}> $items
     * @return array<string,int>
     */
    public function upsertLote(string $canal, array $items): array
    {
        $porDireccion = [];
        foreach ($items as $it) {
            $dir = $this->normalizarDireccion($canal, (string) ($it['direccion'] ?? ''));
            if ($dir === '') { continue; }
            if (!isset($porDireccion[$dir])) {
                $porDireccion[$dir] = [
                    'direccion' => $dir,
                    'nombre' => isset($it['nombre']) && trim((string) $it['nombre']) !== ''
                        ? mb_substr(trim((string) $it['nombre']), 0, 150) : null,
                    'asociado_cedula' => isset($it['asociado_cedula']) && trim((string) $it['asociado_cedula']) !== ''
                        ? mb_substr(trim((string) $it['asociado_cedula']), 0, 20) : null,
                    'metadata' => isset($it['metadata']) ? json_encode($it['metadata'], JSON_UNESCAPED_UNICODE) : null,
                ];
            }
        }
        if (!$porDireccion) {
            return [];
        }

        $filas = array_values($porDireccion);
        foreach (array_chunk($filas, 500) as $chunk) {
            $ph = implode(',', array_fill(0, count($chunk), '(?,?,?,?,?)'));
            $params = [];
            foreach ($chunk as $f) {
                array_push($params, $canal, $f['direccion'], $f['nombre'], $f['asociado_cedula'], $f['metadata']);
            }
            $this->db->prepare(
                "INSERT INTO com_contactos (canal, direccion, nombre, asociado_cedula, metadata_json)
                 VALUES $ph
                 ON DUPLICATE KEY UPDATE
                    nombre = COALESCE(VALUES(nombre), nombre),
                    asociado_cedula = COALESCE(VALUES(asociado_cedula), asociado_cedula),
                    metadata_json = COALESCE(VALUES(metadata_json), metadata_json),
                    fecha_actualizacion = NOW()"
            )->execute($params);
        }

        $out = [];
        foreach (array_chunk(array_keys($porDireccion), 500) as $chunk) {
            $in = implode(',', array_fill(0, count($chunk), '?'));
            $st = $this->db->prepare(
                "SELECT id, direccion FROM com_contactos WHERE canal = ? AND direccion IN ($in)"
            );
            $st->execute(array_merge([$canal], $chunk));
            foreach ($st->fetchAll() as $r) {
                $out[(string) $r['direccion']] = (int) $r['id'];
            }
        }
        return $out;
    }

    /**
     * Opt-in/opt-out maestro para un lote de cédulas.
     * Devuelve las cédulas que no existen (para que el llamador aplique su fallback).
     * @param string[] $cedulas
     * @return string[]
     */
    public function setOptInAsociadoLote(array $cedulas, bool $optIn, ?array $actor = null): array
    {
        $cedulas = array_values(array_unique(array_filter(array_map(
            static fn($c): string => trim((string) $c),
            $cedulas
        ), static fn(string $c): bool => $c !== '')));
        if (!$cedulas) {
            return [];
        }

        $asociados = [];
        foreach (array_chunk($cedulas, 500) as $chunk) {
            $in = implode(',', array_fill(0, count($chunk), '?'));
            $st = $this->db->prepare(
                "SELECT cedula, celular, telefono, email FROM vw_asociados WHERE cedula IN ($in)"
            );
            $st->execute($chunk);
            foreach ($st->fetchAll() as $r) {
                $asociados[(string) $r['cedula']] = $r;
            }
        }
        $faltantes = array_values(array_filter($cedulas, static fn(string $c): bool => !isset($asociados[$c])));
        if (!$asociados) {
            return $faltantes;
        }

        $ids = array_keys($asociados);
        foreach (array_chunk($ids, 500) as $chunk) {
            $ph = implode(',', array_fill(0, count($chunk), '(?,?,1)'));
            $params = [];
            foreach ($chunk as $c) { array_push($params, $c, $optIn ? 1 : 0); }
            $this->db->prepare(
                "INSERT INTO asociados_control (cedula, opt_in, estado_activo)
                 VALUES $ph
                 ON DUPLICATE KEY UPDATE opt_in = VALUES(opt_in), fecha_actualizacion = NOW()"
            )->execute($params);
        }

        // Asegura que existan los contactos de todos los canales del asociado.
        $porCanal = ['sms' => [], 'whatsapp' => [], 'email' => []];
        foreach ($asociados as $c => $a) {
            $tel = PhoneNormalizer::normalize((string) ($a['celular'] ?? ''));
            if ($tel) {
                $porCanal['sms'][$tel] = ['direccion' => $tel, 'asociado_cedula' => $c];
                $porCanal['whatsapp'][$tel] = ['direccion' => $tel, 'asociado_cedula' => $c];
            }
            $tel2 = PhoneNormalizer::normalize((string) ($a['telefono'] ?? ''));
            if ($tel2 && $tel2 !== $tel) {
                $porCanal['sms'][$tel2] = ['direccion' => $tel2, 'asociado_cedula' => $c];
                $porCanal['whatsapp'][$tel2] = ['direccion' => $tel2, 'asociado_cedula' => $c];
            }
            $mail = PhoneNormalizer::normalizeEmail((string) ($a['email'] ?? ''));
            if ($mail) {
                $porCanal['email'][$mail] = ['direccion' => $mail, 'asociado_cedula' => $c];
            }
        }
        foreach ($porCanal as $canal => $items) {
            if ($items) { $this->upsertLote($canal, array_values($items)); }
        }

        $baseSql = $optIn
            ? "UPDATE com_contactos
                  SET opt_in=1, opt_in_fuente='asociado', opt_in_fecha=NOW(),
                      opt_out=0, opt_out_fuente=NULL, opt_out_fecha=NULL, opt_out_motivo=NULL,
                      fecha_actualizacion=NOW()
                WHERE asociado_cedula IN (%s)"
            : "UPDATE com_contactos
                  SET opt_in=0, opt_in_fuente='asociado', opt_in_fecha=NOW(),
                      fecha_actualizacion=NOW()
                WHERE asociado_cedula IN (%s)";
        foreach (array_chunk($ids, 500) as $chunk) {
            $in = implode(',', array_fill(0, count($chunk), '?'));
            $this->db->prepare(sprintf($baseSql, $in))->execute($chunk);
        }

        Logger::log($optIn ? 'opt_in' : 'opt_out', 'comunicaciones', $actor['id'] ?? null,
            'Opt-in maestro por lote: ' . count($ids) . ' asociados ' . ($optIn ? 'activos' : 'inactivos'),
            null, null, $optIn ? 'info' : 'warning');

        return $faltantes;
    }

    public function registrarOptIn(string $canal, string $direccion, string $fuente = 'manual', ?string $evidencia = null, ?array $actor = null): int
    {
        $direccion = $this->normalizarDireccion($canal, $direccion);
        $id = $this->upsert($canal, $direccion);
        $this->db->prepare(
            "UPDATE com_contactos
                SET opt_in = 1, opt_in_fuente = ?, opt_in_fecha = NOW(),
                    opt_out = 0, opt_out_fuente = NULL, opt_out_fecha = NULL, opt_out_motivo = NULL,
                    fecha_actualizacion = NOW()
              WHERE id = ?"
        )->execute([$fuente, $id]);
        Logger::log('opt_in', 'comunicaciones', $actor['id'] ?? null,
            "Opt-in $canal $direccion ($fuente)" . ($evidencia ? ' · ' . $evidencia : ''));
        return $id;
    }

    public function registrarOptOut(string $canal, string $direccion, string $fuente = 'manual', ?string $motivo = null, ?array $actor = null): int
    {
        $direccion = $this->normalizarDireccion($canal, $direccion);
        $id = $this->upsert($canal, $direccion);
        $this->db->prepare(
            "UPDATE com_contactos
                SET opt_out = 1, opt_out_fuente = ?, opt_out_fecha = NOW(), opt_out_motivo = ?,
                    fecha_actualizacion = NOW()
              WHERE id = ?"
        )->execute([$fuente, $motivo !== null ? mb_substr($motivo, 0, 160) : null, $id]);
        Logger::log('opt_out', 'comunicaciones', $actor['id'] ?? null,
            "Opt-out $canal $direccion ($fuente)" . ($motivo ? ' · ' . $motivo : ''), null, null, 'warning');
        return $id;
    }

    /**
     * ¿Se puede enviar a esta dirección?
     * @return array{ok:bool,motivo:string,contacto:?array}
     */
    public function puedeEnviar(string $canal, string $direccion): array
    {
        $direccion = $this->normalizarDireccion($canal, $direccion);
        $c = $this->obtener($canal, $direccion);
        if ($c && (int) $c['opt_out'] === 1) {
            return ['ok' => false, 'motivo' => 'opt_out', 'contacto' => $c];
        }
        $obligatorio = (int) $this->cfg->get('com_consentimiento_obligatorio', '1') === 1;
        if (!$obligatorio) {
            return ['ok' => true, 'motivo' => '', 'contacto' => $c];
        }
        if ($c && (int) $c['opt_in'] === 1) {
            return ['ok' => true, 'motivo' => '', 'contacto' => $c];
        }
        if ($this->optInAsociadoPorDireccion($canal, $direccion)) {
            return ['ok' => true, 'motivo' => '', 'contacto' => $c];
        }
        return ['ok' => false, 'motivo' => 'sin_opt_in', 'contacto' => $c];
    }

    /** ¿El asociado tiene opt-in maestro para comunicaciones? */
    public function optInAsociado(string $cedula): bool
    {
        if ($cedula === '') {
            return false;
        }
        $st = $this->db->prepare("SELECT opt_in FROM asociados_control WHERE cedula = ? LIMIT 1");
        $st->execute([$cedula]);
        return (int) ($st->fetchColumn() ?: 0) === 1;
    }

    /** ¿La dirección pertenece a un asociado con opt-in maestro? */
    public function optInAsociadoPorDireccion(string $canal, string $direccion): bool
    {
        $cedula = $this->asociadoPorDireccion($canal, $direccion);
        return $cedula !== null && $this->optInAsociado($cedula);
    }

    /**
     * Guarda el opt-in maestro en asociados_control y lo replica a los
     * contactos del módulo para que el envío lo vea sin consultas adicionales.
     */
    public function setOptInAsociado(string $cedula, bool $optIn, ?array $actor = null): void
    {
        $cedula = trim($cedula);
        if ($cedula === '') {
            throw new DomainException('Falta la cédula del asociado.');
        }
        $st = $this->db->prepare("SELECT cedula FROM vw_asociados WHERE cedula = ? LIMIT 1");
        $st->execute([$cedula]);
        if ($st->fetchColumn() === false) {
            throw new DomainException('Asociado no encontrado.');
        }

        $this->db->prepare(
            "INSERT INTO asociados_control (cedula, opt_in, estado_activo)
             VALUES (?, ?, 1)
             ON DUPLICATE KEY UPDATE opt_in = VALUES(opt_in), fecha_actualizacion = NOW()"
        )->execute([$cedula, $optIn ? 1 : 0]);

        $st = $this->db->prepare("SELECT celular, telefono, email FROM vw_asociados WHERE cedula = ? LIMIT 1");
        $st->execute([$cedula]);
        $p = $st->fetch() ?: [];
        $direcciones = [];
        $tel = PhoneNormalizer::normalize((string) ($p['celular'] ?? ''));
        if ($tel) { $direcciones['sms'][] = $tel; $direcciones['whatsapp'][] = $tel; }
        $tel2 = PhoneNormalizer::normalize((string) ($p['telefono'] ?? ''));
        if ($tel2 && $tel2 !== $tel) { $direcciones['sms'][] = $tel2; $direcciones['whatsapp'][] = $tel2; }
        $mail = PhoneNormalizer::normalizeEmail((string) ($p['email'] ?? ''));
        if ($mail) { $direcciones['email'][] = $mail; }

        foreach ($direcciones as $canal => $lista) {
            foreach (array_unique($lista) as $dir) {
                $id = $this->upsert($canal, $dir, ['asociado_cedula' => $cedula]);
                if ($optIn) {
                    $this->db->prepare(
                        "UPDATE com_contactos
                            SET opt_in=1, opt_in_fuente='asociado', opt_in_fecha=NOW(),
                                opt_out=0, opt_out_fuente=NULL, opt_out_fecha=NULL, opt_out_motivo=NULL,
                                fecha_actualizacion=NOW()
                          WHERE id=?"
                    )->execute([$id]);
                } else {
                    $this->db->prepare(
                        "UPDATE com_contactos
                            SET opt_in=0, opt_in_fuente='asociado', opt_in_fecha=NOW(),
                                fecha_actualizacion=NOW()
                          WHERE id=?"
                    )->execute([$id]);
                }
            }
        }

        Logger::log($optIn ? 'opt_in' : 'opt_out', 'comunicaciones', $actor['id'] ?? null,
            "Opt-in maestro del asociado $cedula: " . ($optIn ? 'activo' : 'inactivo'), null, null,
            $optIn ? 'info' : 'warning');
    }

    public function normalizarDireccion(string $canal, string $direccion): string
    {
        $direccion = trim($direccion);
        if ($canal === 'email') {
            return (string) PhoneNormalizer::normalizeEmail($direccion);
        }
        return (string) PhoneNormalizer::normalize($direccion);
    }

    /** Intenta resolver la cédula de un asociado a partir de su teléfono. */
    public function asociadoPorDireccion(string $canal, string $direccion): ?string
    {
        if ($canal === 'email') {
            $st = $this->db->prepare("SELECT cedula FROM vw_asociados WHERE LOWER(email) = ? LIMIT 1");
            $st->execute([strtolower($direccion)]);
            $v = $st->fetchColumn();
            return $v !== false ? (string) $v : null;
        }

        $tel = $this->normalizarDireccion('sms', $direccion);
        if ($tel === '') {
            return null;
        }
        $digits = preg_replace('/\D/', '', $tel) ?? '';
        $last10 = substr($digits, -10);
        $st = $this->db->prepare(
            "SELECT cedula FROM vw_asociados
              WHERE RIGHT(REPLACE(REPLACE(REPLACE(REPLACE(celular, ' ', ''), '-', ''), '+', ''), '.', ''), 10) = ?
                 OR RIGHT(REPLACE(REPLACE(REPLACE(REPLACE(telefono, ' ', ''), '-', ''), '+', ''), '.', ''), 10) = ?
              LIMIT 1"
        );
        $st->execute([$last10, $last10]);
        $v = $st->fetchColumn();
        return $v !== false ? (string) $v : null;
    }

    /** Dirección normalizada para mostrar/login. */
    public static function display(string $canal, string $direccion): string
    {
        return $canal === 'email' ? $direccion : PhoneNormalizer::display($direccion);
    }
}
