<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/database.php';
require_once dirname(__DIR__) . '/solicitudes/SolicitudService.php';
require_once dirname(__DIR__) . '/Fondo/FondoBienestarConvenioService.php';
require_once dirname(__DIR__) . '/Fondo/FondoBienestarValidacion.php';
require_once dirname(__DIR__) . '/Storage/Storage.php';

/**
 * Servicio del PORTAL de Fondo de Bienestar (cx/).
 * Envuelve el motor único de solicitudes (tipo 'fondo') + la validación de
 * elegibilidad portada del legacy.
 *
 * REGLA DE ORO (igual que PortalService): toda consulta se filtra por la cédula
 * de la sesión ($cedula). Nunca se confía en datos del cliente para la identidad.
 */
final class FondoService
{
    private SolicitudService $solicitudes;
    private FondoBienestarConvenioService $convenios;
    private FondoBienestarValidacion $validacion;

    public function __construct(private ?PDO $db = null)
    {
        $this->db = $db ?? db();
        $this->solicitudes = new SolicitudService($this->db);
        $this->convenios = new FondoBienestarConvenioService($this->db);
        $this->validacion = new FondoBienestarValidacion($this->db);
    }

    /** Convenios activos con elegibilidad y pendiente para el asociado. */
    public function listarConveniosConElegibilidad(string $cedula): array
    {
        $rows = $this->convenios->listarActivos();
        $out = [];
        foreach ($rows as $c) {
            $cert = $this->validacion->validarApto($cedula, (int) $c['id']);
            $pendiente = $this->validacion->tienePendiente($cedula, (int) $c['id']);
            $out[] = [
                'convenio' => $c,
                'apto' => $cert['apto'],
                'mensaje' => $cert['mensaje'],
                'bloqueado_hasta' => $cert['bloqueado_hasta'],
                'tiene_pendiente' => $pendiente !== null,
                'solicitud_pendiente_id' => $pendiente,
            ];
        }
        return $out;
    }

    /**
     * Crea una solicitud de fondo a nombre del asociado.
     * Valida elegibilidad + pending; sube el PDF si el convenio lo exige.
     * @param array $data   convenio_id, opcion_elegida?, observaciones?
     * @param array|null $fileArchivo  $_FILES entry (si requiere_pdf_obligatorio)
     */
    public function solicitar(string $cedula, array $data, ?array $fileArchivo = null): int
    {
        $convenioId = (int) ($data['convenio_id'] ?? 0);
        if ($convenioId <= 0) {
            throw new DomainException('Seleccione un convenio.');
        }
        $convenio = $this->convenios->obtenerConvenio($convenioId);
        if (!$convenio) {
            throw new DomainException('Convenio no encontrado.');
        }

        $cert = $this->validacion->validarApto($cedula, $convenioId);
        if (!$cert['apto']) {
            throw new DomainException($cert['mensaje']);
        }
        if ($this->validacion->tienePendiente($cedula, $convenioId)) {
            throw new DomainException('Ya tiene una solicitud pendiente para este convenio.');
        }

        // opciones: obligatorias cuando hay opciones configuradas.
        $opciones = [];
        if (!empty($convenio['opciones_json'])) {
            $opciones = json_decode((string) $convenio['opciones_json'], true) ?: [];
        }
        $opcion = trim((string) ($data['opcion_elegida'] ?? ''));
        if ($opciones && $opcion === '') {
            throw new DomainException('Seleccione una opción del beneficio.');
        }

        $observaciones = trim((string) ($data['observaciones'] ?? ''));
        if ($observaciones === '') {
            throw new DomainException('Las observaciones son obligatorias.');
        }

        // PDF obligatorio.
        $rutaPdf = null;
        if (!empty($convenio['requiere_pdf_obligatorio'])) {
            $nombrePdf = $convenio['nombre_pdf_obligatorio'] ?: 'PDF obligatorio';
            if ($fileArchivo === null || ($fileArchivo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                throw new DomainException("Debe adjuntar: $nombrePdf.");
            }
            $rutaPdf = $this->_guardarPdf($cedula, $convenioId, $fileArchivo);
        }

        $detalle = [
            'convenio_id'          => $convenioId,
            'opcion_elegida'       => $opcion !== '' ? $opcion : null,
            'ruta_pdf_obligatorio' => $rutaPdf,
            'observaciones'        => $observaciones,
            'dato_extra'           => json_encode(['origen' => 'portal'], JSON_UNESCAPED_UNICODE),
        ];

        return $this->solicitudes->crear('fondo', $cedula, $detalle, [
            'origen' => 'portal',
            'solicitante_tipo' => 'asociado',
            'actor_tipo' => 'asociado',
            'actor_id' => $cedula,
        ])['solicitud']['id'];
    }

    /** Solicitudes de fondo del asociado. */
    public function listarMias(string $cedula, int $limit = 50, int $offset = 0): array
    {
        return $this->solicitudes->listar(['tipo' => 'fondo', 'cedula' => $cedula], $limit, $offset);
    }

    /** Detalle (verifica dueño). */
    public function detalle(string $cedula, int $id): array
    {
        $d = $this->solicitudes->detalle($id);
        $s = $d['solicitud'];
        if ((string) $s['cedula_solicitante'] !== $cedula) {
            throw new DomainException('La solicitud no le pertenece.');
        }
        if ($s['tipo_codigo'] !== 'fondo') {
            throw new DomainException('Solicitud no válida para Fondo de Bienestar.');
        }
        $d['elegibilidad'] = !empty($d['detalle']['convenio_id'])
            ? $this->validacion->validarApto($cedula, (int) $d['detalle']['convenio_id'])
            : null;
        return $d;
    }

    /** Agrega un comentario del asociado. */
    public function comentar(string $cedula, int $id, string $mensaje): array
    {
        $d = $this->solicitudes->detalle($id);
        $s = $d['solicitud'];
        if ((string) $s['cedula_solicitante'] !== $cedula) {
            throw new DomainException('La solicitud no le pertenece.');
        }
        return $this->solicitudes->comentar($id, ['tipo' => 'asociado', 'id' => $cedula], $mensaje);
    }

    /** Ruta relativa del PDF obligatorio de una solicitud del asociado (para servir). */
    public function archivoPdf(string $cedula, int $id): ?array
    {
        $d = $this->detalle($cedula, $id);
        $ruta = $d['detalle']['ruta_pdf_obligatorio'] ?? null;
        if (!$ruta) {
            return null;
        }
        $abs = $this->_rutaAbsoluta($ruta);
        return ['relativa' => $ruta, 'absoluta' => $abs, 'existe' => is_file($abs)];
    }

    // ── Staff: gestión de solicitudes (CX Control) ───────────────────────

    /** Listado de solicitudes 'fondo' enriquecido (convenio + asociado). */
    public function listarGestion(string $q = '', string $estado = '', string $orderBy = 'fecha_creacion', string $orderDir = 'DESC', int $limit = 50, int $offset = 0): array
    {
        $w = ["s.tipo_codigo = 'fondo'"];
        $p = [];
        if ($estado !== '' && preg_match('/^[a-z_]+$/', $estado)) {
            $w[] = 's.estado_codigo = ?';
            $p[] = $estado;
        }
        if ($q !== '') {
            $w[] = '(s.numero_radicado LIKE ? OR s.cedula_solicitante LIKE ? OR c.nombre LIKE ? OR v.nombre LIKE ?)';
            $like = '%' . $q . '%';
            $p = array_merge($p, [$like, $like, $like, $like]);
        }
        $where = 'WHERE ' . implode(' AND ', $w);

        $st = $this->db->prepare("SELECT COUNT(*) FROM solicitudes s $where");
        $st->execute($p);
        $total = (int) $st->fetchColumn();

        $dir = strtoupper($orderDir) === 'ASC' ? 'ASC' : 'DESC';
        $col = in_array($orderBy, ['fecha_creacion', 'estado_codigo', 'numero_radicado'], true) ? "s.$orderBy" : 's.fecha_creacion';

        $sql = "SELECT s.id, s.numero_radicado, s.estado_codigo, s.fecha_creacion, s.cedula_solicitante,
                       s.responsable_id, s.resultado, s.fecha_respuesta,
                       c.nombre AS convenio_nombre, d.opcion_elegida, d.fecha_uso, d.bloqueado_hasta,
                       v.nombre AS asociado_nombre
                FROM solicitudes s
                LEFT JOIN solicitudes_det_fondo d ON d.solicitud_id = s.id
                LEFT JOIN fondo_bienestar_convenios c ON c.id = d.convenio_id
                LEFT JOIN vw_asociados v ON v.cedula = s.cedula_solicitante
                $where ORDER BY $col $dir LIMIT ? OFFSET ?";
        $st = $this->db->prepare($sql);
        foreach ($p as $i => $v) $st->bindValue($i + 1, $v);
        $st->bindValue(count($p) + 1, $limit, PDO::PARAM_INT);
        $st->bindValue(count($p) + 2, $offset, PDO::PARAM_INT);
        $st->execute();

        return ['rows' => $st->fetchAll(), 'total' => $total, 'limit' => $limit, 'offset' => $offset];
    }

    /** Staff activo para asignación transparente (sin exigir permisos de usuarios). */
    public function listarResponsables(): array
    {
        $st = $this->db->prepare("SELECT id, usuario, nombre_completo, rol FROM usuarios WHERE estado_activo = 1 ORDER BY nombre_completo");
        $st->execute();
        return $st->fetchAll();
    }

    // ── Extras para el detalle staff ────────────────────────────────────

    /** Convenio por id (para pintar en detalle). */
    public function obtenerConvenio(int $id): ?array
    {
        return $this->convenios->obtenerConvenio($id);
    }

    /** Directorio base de uploads. */
    public function uploadsFondoDir(): string
    {
        $base = getenv('UPLOAD_DIR') ?: (dirname(__DIR__, 2) . '/uploads');
        if ($base[0] !== '/') $base = dirname(__DIR__, 2) . '/' . $base;
        $dir = rtrim($base, '/') . '/fondo_bienestar';
        if (!is_dir($dir)) @mkdir($dir, 0777, true);
        @chmod($dir, 0777);
        return $dir;
    }

    private function _guardarPdf(string $cedula, int $convenioId, array $file): string
    {
        if (($file['error'] ?? 1) !== UPLOAD_ERR_OK) {
            throw new DomainException('No se pudo subir el archivo.');
        }
        $mime = (string) ($file['type'] ?? '');
        $ext = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
        if ($ext !== 'pdf' && $mime !== 'application/pdf' && !str_ends_with($mime, '/pdf')) {
            throw new DomainException('El documento debe ser PDF.');
        }
        if ($file['size'] > 10 * 1024 * 1024) {
            throw new DomainException('El PDF supera el límite de 10 MB.');
        }
        $subdir = date('Y') . '/' . date('m');
        $clave = substr(hash('sha256', (string) microtime(true)), 0, 12);
        $nombre = Storage::nombreOpaco('fondo', (string) $convenioId, $clave, 'pdf');
        $ruta = 'fondo_bienestar/' . $subdir . '/' . $nombre;
        Storage::for('fondo')->put($ruta, $file['tmp_name'], ['mime' => 'application/pdf']);
        return $ruta;
    }

    private function _rutaAbsoluta(string $relativa): string
    {
        $base = getenv('UPLOAD_DIR') ?: (dirname(__DIR__, 2) . '/uploads');
        if ($base[0] !== '/') $base = dirname(__DIR__, 2) . '/' . $base;
        return rtrim($base, '/') . '/' . $relativa;
    }
}