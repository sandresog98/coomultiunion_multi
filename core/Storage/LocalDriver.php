<?php
declare(strict_types=1);

require_once __DIR__ . '/StorageDriver.php';

/**
 * LocalDriver — comportamiento histórico: archivos en UPLOAD_DIR/<modulo>/…
 * Mantiene exactamente la ubicación y las cabeceras actuales.
 */
final class LocalDriver implements StorageDriver
{
    public function __construct(private string $root)
    {
        $this->root = rtrim($root, '/');
    }

    public function put(string $key, $streamOrPath, array $opts = []): void
    {
        $dest = $this->abs($key);
        $dir  = dirname($dest);
        if (!is_dir($dir)) { @mkdir($dir, 0775, true); }

        if (is_resource($streamOrPath)) {
            $src = $streamOrPath;
            // Si viene de un upload, PHP ya lo movió a tmp; aquí lo copiamos a destino.
            $fp = fopen($dest, 'w+b');
            if (!$fp) { throw new RuntimeException('No se pudo abrir destino de almacenamiento.'); }
            rewind($src);
            stream_copy_to_stream($src, $fp);
            fclose($fp);
            @chmod($dest, 0777);
        } else {
            $srcPath = (string) $streamOrPath;
            if (!is_file($srcPath)) { throw new RuntimeException('Origen del archivo no existe.'); }
            if (!@copy($srcPath, $dest)) { throw new RuntimeException('No se pudo guardar el archivo.'); }
            @chmod($dest, 0777);
        }
    }

    public function serve(string $key, array $opts = []): void
    {
        $path = $this->abs($key);
        if (!is_file($path)) { throw new RuntimeException('Archivo no disponible.', 404); }

        $mime = $opts['mime'] ?? (mime_content_type($path) ?: 'application/octet-stream');
        $disposition = $opts['disposition'] ?? 'inline';
        $filename    = $opts['filename'] ?? basename($path);

        header('X-Content-Type-Options: nosniff');
        header('Content-Type: ' . $mime);
        if ($opts['no_store'] ?? false) {
            header('Cache-Control: no-store, no-cache, must-revalidate');
            header('Pragma: no-cache');
        } else {
            header('Cache-Control: ' . ($opts['cache_control'] ?? 'private, max-age=3600'));
        }
        header('Content-Disposition: ' . $disposition . '; filename="' . $filename . '"');
        header('Content-Length: ' . (string) filesize($path));
        readfile($path);
    }

    public function url(string $key, array $opts = []): ?string
    {
        return null; // local no tiene URL pública directa
    }

    public function exists(string $key): bool
    {
        return is_file($this->abs($key));
    }

    public function delete(string $key): void
    {
        $path = $this->abs($key);
        if (is_file($path)) { @unlink($path); }
    }

    /** Ruta absoluta bajo la raíz de uploads. */
    public function abs(string $key): string
    {
        $k = ltrim($key, '/');
        if ($k === '' || str_contains($k, '..')) {
            throw new InvalidArgumentException('Clave de archivo inválida.');
        }
        return $this->root . '/' . $k;
    }
}