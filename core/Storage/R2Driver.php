<?php
declare(strict_types=1);

require_once __DIR__ . '/StorageDriver.php';
require_once dirname(__DIR__, 2) . '/utils/vendor/r2min/R2Client.php';

/**
 * R2Driver — almacenamiento en Cloudflare R2 (S3-compatible) vía utils/vendor/r2min.
 *
 * - Privados (por defecto): put/get/del contra el bucket privado; serve() hace un
 *   GetObject y streamea con las cabeceras correctas (proxy autenticado, conserva
 *   auditoría/control del dueño del endpoint). Sin presigned URLs.
 * - Públicos (bucket público / convenios): url() devuelve R2_PUBLIC_URL/key; serve()
 *   sirve desde el bucket (redirige o streamea), y url() expone la URL pública.
 *
 * Este único driver aísla la decisión R2; si a futuro se quiere CDN/presigned se
 * cambia SOLO esta clase.
 */
final class R2Driver implements StorageDriver
{
    private R2Client $client;
    private string $bucket;
    private bool $public;
    private ?string $publicUrl;

    public function __construct(
        string $endpoint,
        string $accessKeyId,
        string $secretAccessKey,
        string $bucket,
        bool $public = false,
        ?string $publicUrl = null
    ) {
        $this->client    = new R2Client($endpoint, $accessKeyId, $secretAccessKey);
        $this->bucket    = $bucket;
        $this->public    = $public;
        $this->publicUrl = $publicUrl ? rtrim($publicUrl, '/') : null;
    }

    public function put(string $key, $streamOrPath, array $opts = []): void
    {
        if (is_resource($streamOrPath)) {
            rewind($streamOrPath);
            $body = $streamOrPath;
        } else {
            $src = (string) $streamOrPath;
            if (!is_file($src)) { throw new RuntimeException('Origen del archivo no existe.'); }
            $body = fopen($src, 'rb');
        }
        $this->client->putObject($this->bucket, $key, $body, [
            'Content-Type' => $opts['mime'] ?? 'application/octet-stream',
            'Cache-Control' => $opts['cache_control'] ?? 'private, max-age=3600',
        ]);
    }

    public function serve(string $key, array $opts = []): void
    {
        if ($this->public && $this->publicUrl && !($opts['force_stream'] ?? false)) {
            header('Location: ' . $this->publicUrl . '/' . ltrim($key, '/'));
            exit;
        }
        // Proxy autenticado: trae objeto y streamea con cabeceras correctas.
        [$stream, $mime, $length] = $this->client->getObject($this->bucket, $key);
        $mime = $opts['mime'] ?? ($mime ?: 'application/octet-stream');
        $disposition = $opts['disposition'] ?? 'inline';
        $filename    = $opts['filename'] ?? basename($key);

        header('X-Content-Type-Options: nosniff');
        header('Content-Type: ' . $mime);
        if ($opts['no_store'] ?? false) {
            header('Cache-Control: no-store, no-cache, must-revalidate');
            header('Pragma: no-cache');
        } else {
            header('Cache-Control: ' . ($opts['cache_control'] ?? 'private, max-age=3600'));
        }
        header('Content-Disposition: ' . $disposition . '; filename="' . $filename . '"');
        if ($length !== null) { header('Content-Length: ' . (string) $length); }

        fpassthru($stream);
        if (is_resource($stream)) { fclose($stream); }
    }

    public function url(string $key, array $opts = []): ?string
    {
        if (!$this->public || !$this->publicUrl) { return null; }
        return $this->publicUrl . '/' . ltrim($key, '/');
    }

    public function exists(string $key): bool
    {
        return $this->client->exists($this->bucket, $key);
    }

    public function delete(string $key): void
    {
        $this->client->deleteObject($this->bucket, $key);
    }
}