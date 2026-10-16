<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/database.php';
require_once dirname(__DIR__) . '/Logger.php';

/**
 * Maestro de convenios del Fondo de Bienestar (B7 Fase 2) — tabla
 * `fondo_bienestar_convenios` (sin FK/ENUM). Reconstrucción v2 del CRUD legacy
 * `ui/modules/oficina/{pages,api,models}/fondo_bienestar_convenios*`.
 *
 * Mejoras frente al legacy:
 *  - Validación server-side de `opciones_json` (JSON válido de strings) y de
 *    `meses_permitidos` (JSON `[...]` de 1..12 o CSV de enteros 1..12).
 *  - `codigo` read-only en edición (UNIQUE, inmutable una vez creado).
 *  - Eliminación soft (estado_activo=0) si el convenio ya tiene redenciones.
 */
final class FondoBienestarConvenioService
{
    private const ORDER_COLS = [
        'codigo', 'nombre', 'meses_requeridos', 'bloqueo_meses', 'orden',
        'estado_activo', 'fecha_creacion',
    ];

    public function __construct(private ?PDO $db = null)
    {
        $this->db = $db ?? db();
    }

    public function listarConvenios(
        string $q = '',
        string $estado = '',
        string $orderBy = 'orden',
        string $orderDir = 'ASC',
        int $limit = 20,
        int $offset = 0
    ): array {
        $where = [];
        $params = [];
        if ($q !== '') {
            $where[] = "(codigo LIKE ? OR nombre LIKE ? OR descripcion LIKE ?)";
            $like = '%' . $q . '%';
            $params = [$like, $like, $like];
        }
        if ($estado !== '' && in_array($estado, ['0', '1'], true)) {
            $where[] = "estado_activo = ?";
            $params[] = (int) $estado;
        }
        $w = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $st = $this->db->prepare("SELECT COUNT(*) FROM fondo_bienestar_convenios $w");
        $st->execute($params);
        $total = (int) $st->fetchColumn();

        $orderBy = in_array($orderBy, self::ORDER_COLS, true) ? $orderBy : 'orden';
        $orderDir = strtoupper($orderDir) === 'DESC' ? 'DESC' : 'ASC';

        $sql = "SELECT c.*,
                       (SELECT COUNT(*) FROM fondo_bienestar_redenciones r WHERE r.convenio_id = c.id) AS num_redenciones,
                       (SELECT COUNT(*) FROM solicitudes_det_fondo d WHERE d.convenio_id = c.id) AS num_solicitudes
                FROM fondo_bienestar_convenios c $w ORDER BY c.$orderBy $orderDir LIMIT ? OFFSET ?";
        $st = $this->db->prepare($sql);
        foreach ($params as $i => $v) $st->bindValue($i + 1, $v);
        $st->bindValue(count($params) + 1, $limit, PDO::PARAM_INT);
        $st->bindValue(count($params) + 2, $offset, PDO::PARAM_INT);
        $st->execute();

        return ['rows' => $st->fetchAll(), 'total' => $total, 'limit' => $limit, 'offset' => $offset];
    }

    public function listarActivos(): array
    {
        $st = $this->db->query(
            "SELECT * FROM fondo_bienestar_convenios WHERE estado_activo = 1 AND agotado = 0 ORDER BY orden ASC, id ASC"
        );
        return $st->fetchAll();
    }

    public function obtenerConvenio(int $id): ?array
    {
        $st = $this->db->prepare("SELECT * FROM fondo_bienestar_convenios WHERE id = ?");
        $st->execute([$id]);
        $row = $st->fetch();
        return $row ?: null;
    }

    /**
     * Crea o actualiza un convenio. Validación server-side de CSV/JSON.
     * Al editar, `codigo` es read-only (ignorado si $id > 0).
     *
     * @param array $data  contentType JSON
     * @param array|null $actor  ['id' => usuarios.id]
     */
    public function guardarConvenio(array $data, ?array $actor = null): array
    {
        $id = (int) ($data['id'] ?? 0);
        $codigo = trim((string) ($data['codigo'] ?? ''));
        $nombre = trim((string) ($data['nombre'] ?? ''));

        if ($id <= 0 && $codigo === '') {
            throw new DomainException('El código es obligatorio al crear el convenio.');
        }
        if ($nombre === '') {
            throw new DomainException('El nombre es obligatorio.');
        }

        $opcionesJson = $this->_parseOpcionesJson($data['opciones_json'] ?? null);
        $mesesPermitidos = $this->_parseMesesPermitidos($data['meses_permitidos'] ?? null);

        $campos = [
            'nombre'                      => $nombre,
            'descripcion'                 => $this->_nullIfEmpty($data['descripcion'] ?? null),
            'meses_requeridos'            => (int) ($data['meses_requeridos'] ?? 0),
            'no_redime'                   => $this->_bool($data['no_redime'] ?? false),
            'bloqueo_meses'               => $this->_nullableInt($data['bloqueo_meses'] ?? null),
            'requiere_cumpleanos_30_dias' => $this->_bool($data['requiere_cumpleanos_30_dias'] ?? false),
            'opciones_json'               => $opcionesJson,
            'requiere_pdf_obligatorio'    => $this->_bool($data['requiere_pdf_obligatorio'] ?? false),
            'nombre_pdf_obligatorio'      => $this->_nullIfEmpty($data['nombre_pdf_obligatorio'] ?? null),
            'envia_email_entidad'         => $this->_bool($data['envia_email_entidad'] ?? false),
            'email_entidad'               => $this->_nullIfEmpty($data['email_entidad'] ?? null),
            'meses_permitidos'            => $mesesPermitidos,
            'meses_espera_proxima'        => (int) ($data['meses_espera_proxima'] ?? 0),
            'orden'                       => (int) ($data['orden'] ?? 0),
            'estado_activo'               => $this->_bool($data['estado_activo'] ?? true),
            'agotado'                     => $this->_bool($data['agotado'] ?? false),
        ];

        if ($id > 0) {
            $campos['fecha_actualizacion'] = date('Y-m-d H:i:s');
            $sets = [];
            $vals = [];
            foreach ($campos as $k => $v) {
                $sets[] = "$k = ?";
                $vals[] = $v;
            }
            $vals[] = $id;
            $this->db->prepare("UPDATE fondo_bienestar_convenios SET " . implode(', ', $sets) . " WHERE id = ?")
                ->execute($vals);
            Logger::log('editar', 'fondo_bienestar.convenios', $actor['id'] ?? null, "Editado convenio #$id ($nombre)");
            return ['id' => $id];
        }

        // Creación: verificar UNIQUE codigo
        $st = $this->db->prepare("SELECT id FROM fondo_bienestar_convenios WHERE codigo = ?");
        $st->execute([$codigo]);
        if ($st->fetch()) {
            throw new DomainException("El código '$codigo' ya existe.");
        }

        $campos['codigo'] = $codigo;
        $cols = array_keys($campos);
        $ph = array_fill(0, count($cols), '?');
        $this->db->prepare(
            "INSERT INTO fondo_bienestar_convenios (" . implode(',', $cols) . ") VALUES (" . implode(',', $ph) . ")"
        )->execute(array_values($campos));
        $id = (int) $this->db->lastInsertId();
        Logger::log('crear', 'fondo_bienestar.convenios', $actor['id'] ?? null, "Creado convenio #$id ($nombre)");
        return ['id' => $id];
    }

    /** Soft toggle de estado_activo. */
    public function toggleConvenio(int $id, ?array $actor = null): array
    {
        $c = $this->obtenerConvenio($id);
        if (!$c) throw new DomainException('Convenio no encontrado.');
        $nuevo = (int) $c['estado_activo'] ? 0 : 1;
        $this->db->prepare("UPDATE fondo_bienestar_convenios SET estado_activo = ?, fecha_actualizacion = ? WHERE id = ?")
            ->execute([$nuevo, date('Y-m-d H:i:s'), $id]);
        Logger::log($nuevo ? 'activar' : 'inactivar', 'fondo_bienestar.convenios', $actor['id'] ?? null,
            ($nuevo ? 'Activado' : 'Inactivado') . " convenio #$id", null, null, 'warning');
        return ['id' => $id, 'estado_activo' => $nuevo];
    }

    /** Eliminación: si tiene redenciones/solicitudes → soft (cero pérdida), si no → DELETE físico. */
    public function eliminarConvenio(int $id, ?array $actor = null): array
    {
        $c = $this->obtenerConvenio($id);
        if (!$c) throw new DomainException('Convenio no encontrado.');
        $st = $this->db->prepare(
            "SELECT
               (SELECT COUNT(*) FROM fondo_bienestar_redenciones WHERE convenio_id = ?) +
               (SELECT COUNT(*) FROM solicitudes_det_fondo WHERE convenio_id = ?)"
        );
        $st->execute([$id, $id]);
        $usos = (int) $st->fetchColumn();
        if ($usos > 0) {
            $this->db->prepare("UPDATE fondo_bienestar_convenios SET estado_activo = 0, fecha_actualizacion = ? WHERE id = ?")
                ->execute([date('Y-m-d H:i:s'), $id]);
            Logger::log('inactivar', 'fondo_bienestar.convenios', $actor['id'] ?? null,
                "Convenio #$id con uso ($usos): desactivado en lugar de eliminar.", null, null, 'warning');
            return ['id' => $id, 'estado_activo' => 0, 'soft' => true];
        }
        $this->db->prepare("DELETE FROM fondo_bienestar_convenios WHERE id = ?")->execute([$id]);
        Logger::log('eliminar', 'fondo_bienestar.convenios', $actor['id'] ?? null, "Eliminado convenio #$id", null, null, 'warning');
        return ['id' => $id, 'soft' => false];
    }

    // ── Validación server-side (mejora vs legacy) ────────────────────────

    /** Devuelve JSON canónico de string[] o null. */
    private function _parseOpcionesJson(mixed $raw): ?string
    {
        if ($raw === null || $raw === '' || $raw === false) return null;
        $s = (string) $raw;
        $dec = json_decode($s, true);
        if (!is_array($dec)) {
            throw new DomainException('opciones_json debe ser un JSON válido de arreglo, p. ej. ["Opción A","Opción B"].');
        }
        foreach ($dec as $v) {
            if (!is_string($v) || trim($v) === '') {
                throw new DomainException('opciones_json debe contener solo textos no vacíos.');
            }
        }
        return json_encode(array_values($dec), JSON_UNESCAPED_UNICODE);
    }

    /** Devuelve JSON `[1,2,6]` (cumple con validarPeriodo) o null. */
    private function _parseMesesPermitidos(mixed $raw): ?string
    {
        if ($raw === null || $raw === '' || $raw === false) return null;
        $s = trim((string) $raw);
        $meses = [];
        if (str_starts_with($s, '[')) {
            $dec = json_decode($s, true);
            if (!is_array($dec)) {
                throw new DomainException('meses_permitidos debe ser JSON `[1,2,6]` o lista separada por comas.');
            }
            $meses = array_map('intval', $dec);
        } else {
            $partes = array_map('trim', explode(',', $s));
            $meses = array_map('intval', array_filter($partes, fn($p) => $p !== ''));
        }
        foreach ($meses as $m) {
            if ($m < 1 || $m > 12) {
                throw new DomainException('meses_permitidos admite únicamente valores entre 1 y 12.');
            }
        }
        $meses = array_values(array_unique($meses));
        sort($meses);
        return $meses ? json_encode($meses) : null;
    }

    private function _bool(mixed $v): int
    {
        return $v === true || $v === 1 || $v === '1' || $v === 'true' ? 1 : 0;
    }

    private function _nullableInt(mixed $v): ?int
    {
        if ($v === null || $v === '' || $v === false) return null;
        return (int) $v;
    }

    private function _nullIfEmpty(mixed $v): ?string
    {
        $s = trim((string) ($v ?? ''));
        return $s === '' ? null : $s;
    }
}