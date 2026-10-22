<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/database.php';
require_once dirname(__DIR__) . '/Storage/Storage.php';
require_once dirname(__DIR__) . '/env.php';
require_once dirname(__DIR__) . '/Logger.php';
require_once __DIR__ . '/FirmaService.php';
require_once __DIR__ . '/PhoneNormalizer.php';

/**
 * Adjuntos del módulo: validación, almacenamiento local, URL pública firmada
 * para que Twilio descargue el media y conversión a adjuntos de SendGrid.
 */
final class MediaService
{
    private PDO $db;

    private const MIME = [
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png'  => 'image/png',
        'webp' => 'image/webp',
        'gif'  => 'image/gif',
        'pdf'  => 'application/pdf',
        'mp4'  => 'video/mp4',
        'mp3'  => 'audio/mpeg',
        'ogg'  => 'audio/ogg',
        'opus' => 'audio/opus',
        'm4a'  => 'audio/mp4',
        'doc'  => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xls'  => 'application/vnd.ms-excel',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'vcf'  => 'text/vcard',
    ];

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? db();
    }

    public function subir(array $file, ?string $canal = null, ?array $actor = null): array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new DomainException('No se recibió el archivo.');
        }
        $orig = (string) ($file['name'] ?? 'archivo');
        $ext  = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
        $ext  = preg_replace('/[^a-z0-9]/', '', $ext) ?: '';
        if (!isset(self::MIME[$ext])) {
            throw new DomainException('Tipo de archivo no permitido. Use imagen, PDF, audio, video u Office.');
        }
        $mime = self::MIME[$ext];
        $maxMb = $this->maxMb($ext);
        if ((int) ($file['size'] ?? 0) > $maxMb * 1024 * 1024) {
            throw new DomainException("El archivo excede el máximo permitido ({$maxMb} MB).");
        }

        $clave = substr(hash('sha256', (string) microtime(true) . random_bytes(8)), 0, 16);
        $nombre = Storage::nombreOpaco('com', (string) ($actor['id'] ?? '0'), $clave, $ext);
        $key = 'comunicaciones/' . $nombre;
        Storage::for('comunicaciones')->put($key, (string) $file['tmp_name'], ['mime' => $mime]);

        $this->db->prepare(
            "INSERT INTO com_adjuntos (nombre_original, clave, mime, tamano, canal, creado_por)
             VALUES (?,?,?,?,?,?)"
        )->execute([$orig, $key, $mime, (int) ($file['size'] ?? 0), $canal, $actor['id'] ?? null]);
        $id = (int) $this->db->lastInsertId();
        Logger::log('crear', 'comunicaciones.adjunto', $actor['id'] ?? null, "Adjunto #$id ($orig)");
        return $this->obtener($id) ?? [];
    }

    public function obtener(int $id): ?array
    {
        $st = $this->db->prepare("SELECT * FROM com_adjuntos WHERE id = ? LIMIT 1");
        $st->execute([$id]);
        return $st->fetch() ?: null;
    }

    public function listar(?string $canal = null): array
    {
        if ($canal !== null && $canal !== '') {
            $st = $this->db->prepare("SELECT * FROM com_adjuntos WHERE canal = ? OR canal IS NULL ORDER BY id DESC LIMIT 200");
            $st->execute([$canal]);
        } else {
            $st = $this->db->query("SELECT * FROM com_adjuntos ORDER BY id DESC LIMIT 200");
        }
        $rows = $st->fetchAll();
        foreach ($rows as &$r) {
            $r['tamano_kb'] = round(((int) $r['tamano']) / 1024, 1);
            $r['es_imagen'] = str_starts_with((string) $r['mime'], 'image/');
        }
        return $rows;
    }

    /** URL firmada que Twilio puede descargar sin sesión de staff. */
    public function urlParaTwilio(int $id, int $ttl = 3600): string
    {
        $token = FirmaService::sign(['id' => $id], $ttl);
        return rtrim($this->appUrl(), '/') . '/api/comunicaciones/media_publica.php?t=' . rawurlencode($token);
    }

    public function serve(int $id): void
    {
        $a = $this->obtener($id);
        if (!$a) {
            http_response_code(404);
            exit('Archivo no encontrado.');
        }
        $filename = preg_replace('/[^A-Za-z0-9._ -]/', '_', (string) $a['nombre_original']) ?: 'archivo';
        Storage::for('comunicaciones')->serve((string) $a['clave'], [
            'mime' => (string) $a['mime'],
            'filename' => $filename,
            'disposition' => 'inline',
            'cache_control' => 'public, max-age=300',
        ]);
    }

    /** @return array<int,array<string,mixed>> */
    public function obtenerVarios(array $ids): array
    {
        $ids = array_values(array_filter(array_map('intval', $ids), static fn ($v) => $v > 0));
        if (!$ids) {
            return [];
        }
        $in = implode(',', array_fill(0, count($ids), '?'));
        $st = $this->db->prepare("SELECT * FROM com_adjuntos WHERE id IN ($in)");
        $st->execute($ids);
        return $st->fetchAll();
    }

    /** URLs firmadas para un plan (WhatsApp). */
    public function urlsParaPlan(array $ids, int $ttl = 3600): array
    {
        $out = [];
        foreach ($this->obtenerVarios($ids) as $a) {
            $out[] = $this->urlParaTwilio((int) $a['id'], $ttl);
        }
        return $out;
    }

    /** Adjuntos para PHPMailer/SMTP (rutas locales). */
    public function adjuntosParaSmtp(array $ids): array
    {
        $out = [];
        foreach ($this->obtenerVarios($ids) as $a) {
            $path = $this->rutaLocal((string) $a['clave']);
            if (!is_file($path)) {
                throw new DomainException('El adjunto "' . $a['nombre_original'] . '" no está disponible localmente.');
            }
            $out[] = [
                'path' => $path,
                'name' => (string) $a['nombre_original'],
                'mime' => (string) $a['mime'],
            ];
        }
        return $out;
    }

    /** Adjuntos base64 para SendGrid (integración futura/opcional). */
    public function adjuntosParaEmail(array $ids): array
    {
        $out = [];
        foreach ($this->obtenerVarios($ids) as $a) {
            $path = $this->rutaLocal((string) $a['clave']);
            if (!is_file($path)) {
                throw new DomainException('El adjunto "' . $a['nombre_original'] . '" no está disponible localmente. Configure SENDGRID y almacenamiento local para comunicaciones.');
            }
            $out[] = [
                'content'     => base64_encode((string) file_get_contents($path)),
                'type'        => (string) $a['mime'],
                'filename'    => (string) $a['nombre_original'],
                'disposition' => 'attachment',
            ];
        }
        return $out;
    }

    public function eliminar(int $id, ?array $actor = null): void
    {
        $a = $this->obtener($id);
        if (!$a) {
            throw new DomainException('Adjunto no encontrado.');
        }
        // Se conserva si ya está referenciado por un plan (auditoría/campaña).
        $st = $this->db->prepare(
            "SELECT COUNT(*) FROM com_planes
              WHERE JSON_VALID(media_json) AND JSON_CONTAINS(media_json, ?)"
        );
        $st->execute([(string) $id]);
        if ((int) $st->fetchColumn() > 0) {
            throw new DomainException('No se puede eliminar: el adjunto está usado en un plan.');
        }
        try {
            Storage::for('comunicaciones')->delete((string) $a['clave']);
        } catch (Throwable $e) {
            error_log('[comunicaciones] no se pudo borrar archivo: ' . $e->getMessage());
        }
        $this->db->prepare("DELETE FROM com_adjuntos WHERE id = ?")->execute([$id]);
        Logger::log('eliminar', 'comunicaciones.adjunto', $actor['id'] ?? null, "Adjunto #$id", null, null, 'warning');
    }

    public function maxMb(string $ext): int
    {
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)) {
            return max(1, (int) (env('UPLOAD_MAX_IMAGE_MB') ?: 5));
        }
        if ($ext === 'pdf') {
            return max(1, (int) (env('UPLOAD_MAX_PDF_MB') ?: 10));
        }
        return max(1, (int) (env('UPLOAD_MAX_MEDIA_MB') ?: 20));
    }

    private function rutaLocal(string $clave): string
    {
        $root = (string) (env('UPLOAD_DIR') ?: (dirname(__DIR__, 2) . '/uploads'));
        if ($root !== '' && $root[0] !== '/') {
            $root = dirname(__DIR__, 2) . '/' . $root;
        }
        $clave = ltrim(str_replace('\\', '/', $clave), '/');
        if ($clave === '' || str_contains($clave, '..')) {
            throw new DomainException('Ruta de adjunto inválida.');
        }
        return rtrim($root, '/') . '/' . $clave;
    }

    private function appUrl(): string
    {
        require_once dirname(__DIR__) . '/Twilio/TwilioSignature.php';
        return TwilioSignature::appUrl();
    }
}
