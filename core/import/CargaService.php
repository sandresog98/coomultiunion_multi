<?php
declare(strict_types=1);

require_once __DIR__ . '/SifoneImporter.php';
require_once __DIR__ . '/PagosPseImporter.php';
require_once __DIR__ . '/PagosConfiarImporter.php';
require_once __DIR__ . '/ConciliacionPseConfiar.php';
require_once dirname(__DIR__) . '/database.php';
require_once dirname(__DIR__) . '/Logger.php';

/**
 * Orquesta una carga: guarda el archivo, crea el job en `cargas`, ejecuta el
 * importador y actualiza el estado. Historial consultable.
 */
final class CargaService
{
    private const MAX_MB = 25;
    private const EXT_OK = ['xls', 'xlsx'];

    /** Etiquetas visibles (la clave técnica no cambia en BD ni en la validación). */
    private const ETIQUETAS = [
        'sifone_libro'                   => 'Sifone libro / asociados',
        'sifone_cartera_aseguradora'     => 'Sifone cartera aseguradora',
        'sifone_cartera_mora'            => 'Sifone cartera mora',
        'sifone_balance_prueba'          => 'Sifone balance de prueba',
        'sifone_movimientos_tributarios' => 'Sifone movimientos contables',
        'sifone_datacredito'             => 'Sifone datacrédito',
        'estado_cuenta'                  => 'Sifone estado de cuenta',
        'pagos_pse'                      => 'Pagos PSE',
        'pagos_confiar'                  => 'Pagos Confiar',
    ];

    public function __construct(private ?PDO $db = null)
    {
        $this->db = $db ?? db();
    }

    public function tipos(): array
    {
        return array_merge(SifoneImporter::tiposSoportados(), ['pagos_pse', 'pagos_confiar']);
    }

    /** Mapa clave técnica => etiqueta visible para la UI. */
    public function etiquetas(): array
    {
        $out = [];
        foreach ($this->tipos() as $tipo) {
            $out[$tipo] = self::ETIQUETAS[$tipo] ?? $tipo;
        }
        return $out;
    }

    /** Ejecuta el importador correcto según el tipo y devuelve stats normalizadas. */
    private function ejecutar(string $tipo, string $pathAbs): array
    {
        if ($tipo === 'pagos_pse' || $tipo === 'pagos_confiar') {
            $proc = $tipo === 'pagos_pse' ? new PagosPseImporter() : new PagosConfiarImporter();
            $ins = $proc->procesar($pathAbs);
            $d = $proc->getLastDiagnostics() ?? [];
            return ['tipo' => $tipo, 'insertados' => $ins, 'filas_leidas' => $d['filas_leidas'] ?? $ins, 'modo' => 'append(dedup)'];
        }
        return (new SifoneImporter($this->db))->procesar($tipo, $pathAbs);
    }

    /**
     * Procesa un archivo subido ($_FILES['archivo']).
     * @return array stats del importador + id del job, o advertencia de confirmación
     */
    public function procesarSubida(string $tipo, array $file, ?int $usuarioId, bool $confirmar = false): array
    {
        if (!in_array($tipo, $this->tipos(), true)) {
            throw new DomainException('Tipo de carga no soportado.');
        }
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'] ?? '')) {
            throw new DomainException('No se recibió el archivo correctamente.');
        }
        $ext = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
        if (!in_array($ext, self::EXT_OK, true)) {
            throw new DomainException('Formato no permitido. Use .xls o .xlsx.');
        }
        if (($file['size'] ?? 0) > self::MAX_MB * 1024 * 1024) {
            throw new DomainException('El archivo supera ' . self::MAX_MB . ' MB.');
        }

        // Confiar: preflight sobre una copia temporal con extensión, antes de guardar en uploads.
        if ($tipo === 'pagos_confiar' && !$confirmar) {
            $tmpFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'confiar_' . bin2hex(random_bytes(8)) . '.' . $ext;
            if (!@copy($file['tmp_name'], $tmpFile)) {
                throw new DomainException('No se pudo preparar el archivo para validar.');
            }
            try {
                $preflight = (new PagosConfiarImporter())->preflight($tmpFile);
            } catch (Throwable $e) {
                throw $e instanceof DomainException ? $e : new DomainException('Error al procesar: ' . $e->getMessage());
            } finally {
                @unlink($tmpFile);
            }
            if (!empty($preflight['requiere_confirmacion'])) {
                return $preflight;
            }
        }

        $ruta = $this->guardarArchivo($tipo, $file, $ext);

        // Crea el job
        $this->db->prepare(
            "INSERT INTO sifone_cargas_archivos (tipo, archivo_ruta, estado, usuario_id) VALUES (?,?, 'procesando', ?)"
        )->execute([$tipo, $ruta, $usuarioId]);
        $jobId = (int) $this->db->lastInsertId();

        try {
            $stats = $this->ejecutar($tipo, $this->rutaAbsoluta($ruta));

            $this->actualizarJob($jobId, 'completado', $stats['insertados'],
                sprintf('%d insertados (leídas %d, modo %s)', $stats['insertados'], $stats['filas_leidas'], $stats['modo']));
            $modo = $stats['modo'] ?? 'append';
            $reemplazo = $modo === 'reemplazo';
            Logger::log(
                $reemplazo ? 'reemplazar' : 'crear',
                'cargas.' . $tipo,
                $usuarioId !== null ? (string) $usuarioId : null,
                "Carga $tipo: {$stats['insertados']} registros (" . ($stats['tabla'] ?? '') . ", modo $modo)" . ($reemplazo ? ' — reemplazo total (DELETE previo)' : ''),
                null, null,
                $reemplazo ? 'warning' : 'info'
            );
            return $stats + ['job_id' => $jobId];
        } catch (Throwable $e) {
            $this->actualizarJob($jobId, 'error', null, $e->getMessage());
            throw $e instanceof DomainException ? $e : new DomainException('Error al procesar: ' . $e->getMessage());
        }
    }

    public function historial(int $limit = 30): array
    {
        $st = $this->db->prepare(
            "SELECT c.id, c.tipo, c.estado, c.registros, c.mensaje_log, c.fecha_creacion, u.nombre_completo AS usuario
             FROM sifone_cargas_archivos c LEFT JOIN usuarios u ON u.id = c.usuario_id
             ORDER BY c.fecha_creacion DESC LIMIT ?"
        );
        $st->bindValue(1, $limit, PDO::PARAM_INT);
        $st->execute();
        return $st->fetchAll();
    }

    private function guardarArchivo(string $tipo, array $file, string $ext): string
    {
        $rel = 'cargas/' . date('Y/m');
        $dirAbs = $this->rutaAbsoluta($rel);
        if (!is_dir($dirAbs) && !@mkdir($dirAbs, 0775, true) && !is_dir($dirAbs)) {
            throw new RuntimeException('No se pudo crear el directorio de cargas.');
        }
        $nombre = $tipo . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $destino = $dirAbs . '/' . $nombre;
        if (!move_uploaded_file($file['tmp_name'], $destino)) {
            throw new RuntimeException('No se pudo guardar el archivo subido.');
        }
        return $rel . '/' . $nombre;
    }

    private function rutaAbsoluta(string $rel): string
    {
        $base = getenv('UPLOAD_DIR') ?: (dirname(__DIR__, 2) . '/uploads');
        if ($base[0] !== '/') {
            $base = dirname(__DIR__, 2) . '/' . $base;
        }
        return rtrim($base, '/') . '/' . ltrim($rel, '/');
    }

    private function actualizarJob(int $id, string $estado, ?int $registros, string $mensaje): void
    {
        $this->db->prepare(
            "UPDATE sifone_cargas_archivos SET estado = ?, registros = ?, mensaje_log = ?, fecha_actualizacion = NOW() WHERE id = ?"
        )->execute([$estado, $registros, mb_substr($mensaje, 0, 2000), $id]);
    }
}
