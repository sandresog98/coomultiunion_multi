<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/database.php';
require_once dirname(__DIR__) . '/solicitudes/SolicitudService.php';
require_once dirname(__DIR__) . '/Fondo/FondoMailService.php';
require_once dirname(__DIR__) . '/Storage/Storage.php';

/**
 * Servicio del PORTAL para Retiro de Aportes (cx/).
 * Envuelve el motor único de solicitudes (tipo 'retiro_aporte') + subida de los
 * dos PDF obligatorios (firma de retiro y certificado de aportes) y el envío
 * (encolado) del correo de creación.
 *
 * REGLA DE ORO (igual que PortalService/FondoService): toda consulta se filtra
 * por la cédula de la sesión ($cedula). Nunca se confía en datos del cliente
 * para la identidad.
 */
final class RetiroAporteService
{
    private SolicitudService $solicitudes;

    public function __construct(private ?PDO $db = null)
    {
        $this->db = $db ?? db();
        $this->solicitudes = new SolicitudService($this->db);
    }

    /**
     * Crea una solicitud de retiro de aportes a nombre del asociado.
     * @param array       $data       tipo (total|parcial), monto_solicitado (parcial)
     * @param array|null  $fileRetiro $_FILES entry (PDF firma de retiro)
     * @param array|null  $fileCert   $_FILES entry (PDF certificado de aportes)
     * @return int id de la solicitud
     */
    public function solicitar(string $cedula, array $data, ?array $fileRetiro = null, ?array $fileCert = null): int
    {
        $tipo = (string) ($data['tipo'] ?? '');
        if (!in_array($tipo, ['total', 'parcial'], true)) {
            throw new DomainException('Seleccione un tipo de retiro (total o parcial).');
        }

        $monto = ($tipo === 'parcial')
            ? (float) ($data['monto_solicitado'] ?? 0)
            : null;
        if ($tipo === 'parcial' && $monto <= 0) {
            throw new DomainException('Indique el monto a retirar.');
        }

        // Ambos PDF obligatorios.
        $rutaRetiro = $this->_guardarPdf($cedula, 'pdf_retiro', $fileRetiro);
        $rutaCert   = $this->_guardarPdf($cedula, 'pdf_certificado', $fileCert);

        $dias = $tipo === 'total' ? 90 : 30;
        $fechaEstimada = date('Y-m-d', strtotime("+$dias days"));

        $detalle = [
            'tipo'                      => $tipo,
            'monto_solicitado'          => $monto,
            'ruta_pdf_retiro'           => $rutaRetiro,
            'ruta_pdf_certificado'      => $rutaCert,
            'fecha_estimada_desembolso' => $fechaEstimada,
        ];

        $id = $this->solicitudes->crear('retiro_aporte', $cedula, $detalle, [
            'origen' => 'portal',
            'solicitante_tipo' => 'asociado',
            'actor_tipo' => 'asociado',
            'actor_id' => $cedula,
        ])['solicitud']['id'];

        $this->_notificarCreacion($cedula, $id);
        return $id;
    }

    /** Solicitudes de retiro de aportes del asociado. */
    public function listarMias(string $cedula, int $limit = 50, int $offset = 0): array
    {
        return $this->solicitudes->listar(['tipo' => 'retiro_aporte', 'cedula' => $cedula], $limit, $offset);
    }

    /** Detalle (verifica dueño y tipo). */
    public function detalle(string $cedula, int $id): array
    {
        $d = $this->solicitudes->detalle($id);
        $s = $d['solicitud'];
        if ((string) $s['cedula_solicitante'] !== $cedula) {
            throw new DomainException('La solicitud no le pertenece.');
        }
        if ($s['tipo_codigo'] !== 'retiro_aporte') {
            throw new DomainException('Solicitud no válida para retiro de aportes.');
        }
        return $d;
    }

    /** Ruta relativa de un PDF del detalle (para servirlo), verificando dueño. */
    public function archivoPdf(string $cedula, int $id, string $tipo): ?array
    {
        $d = $this->detalle($cedula, $id);
        if (!in_array($tipo, ['ruta_pdf_retiro', 'ruta_pdf_certificado'], true)) {
            throw new DomainException('Documento no válido.');
        }
        $ruta = $d['detalle'][$tipo] ?? null;
        if (!$ruta) {
            return null;
        }
        return ['relativa' => $ruta, 'absoluta' => $this->_rutaAbsoluta($ruta), 'existe' => is_file($this->_rutaAbsoluta($ruta))];
    }

    // ── Privados ──────────────────────────────────────────────────────────

    private function _notificarCreacion(string $cedula, int $id): void
    {
        $st = $this->db->prepare('SELECT email FROM vw_asociados WHERE cedula = ?');
        $st->execute([$cedula]);
        $email = $st->fetchColumn();
        if (!$email) {
            return;
        }
        $d = $this->solicitudes->detalle($id);
        $s = $d['solicitud'];
        $asunto = 'Solicitud de retiro de aportes recibida';
        $cuerpo = "Hemos recibido su solicitud de retiro de aportes.\n"
            . 'Radicado: ' . ($s['numero_radicado'] ?? '') . "\n"
            . 'Asociado: ' . $cedula . "\n"
            . 'Fecha estimada de desembolso: ' . date('d/m/Y', strtotime((string) ($d['detalle']['fecha_estimada_desembolso'] ?? 'now')));
        (new FondoMailService($this->db))->encolar('crear', (string) $email, $asunto, $cuerpo, $id);
    }

    private function _guardarPdf(string $cedula, string $tipoDoc, ?array $file): string
    {
        if ($file === null || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new DomainException($tipoDoc === 'pdf_retiro'
                ? 'Debe adjuntar el PDF de firma de retiro.'
                : 'Debe adjuntar el PDF de certificado de aportes.');
        }
        $mime  = (string) ($file['type'] ?? '');
        $ext   = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
        if ($ext !== 'pdf' && $mime !== 'application/pdf' && !str_ends_with($mime, '/pdf')) {
            throw new DomainException('Los documentos deben ser PDF.');
        }
        if ($file['size'] > 10 * 1024 * 1024) {
            throw new DomainException('El PDF supera el límite de 10 MB.');
        }
        $subdir = date('Y') . '/' . date('m');
        $clave = substr(hash('sha256', (string) microtime(true)), 0, 8);
        $nombre = Storage::nombreOpaco('retiro', $tipoDoc, $clave, 'pdf');
        $ruta = 'solicitudes/retiro_aporte/' . $subdir . '/' . $nombre;
        Storage::for('solicitudes')->put($ruta, $file['tmp_name'], ['mime' => 'application/pdf']);
        return $ruta;
    }

    private function _uploadsDir(): string
    {
        $base = getenv('UPLOAD_DIR') ?: (dirname(__DIR__, 2) . '/uploads');
        if ($base[0] !== '/') {
            $base = dirname(__DIR__, 2) . '/' . $base;
        }
        $dir = rtrim($base, '/') . '/solicitudes/retiro_aporte';
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
        @chmod($dir, 0777);
        return $dir;
    }

    /** Ruta absoluta de la raíz de uploads (para contención al servir). */
    public function uploadsRoot(): string
    {
        $base = getenv('UPLOAD_DIR') ?: (dirname(__DIR__, 2) . '/uploads');
        if ($base[0] !== '/') {
            $base = dirname(__DIR__, 2) . '/' . $base;
        }
        return rtrim($base, '/');
    }

    private function _rutaAbsoluta(string $relativa): string
    {
        return $this->uploadsRoot() . '/' . $relativa;
    }
}