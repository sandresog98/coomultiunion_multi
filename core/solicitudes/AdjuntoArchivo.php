<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/Storage/Storage.php';

/**
 * Guarda archivos subidos para una solicitud con una whitelist estricta y
 * límites por entorno. Centraliza la lógica usada por adjuntar.php y comentar.php.
 *
 * Devuelve la metadata lista para `SolicitudRepository::insertarAdjunto()`.
 */
final class AdjuntoArchivo
{
    /** Extensión → MIME permitido (deny-by-default: nada de HTML/SVG/scripts). */
    private const PERMITIDAS = [
        'pdf'  => 'application/pdf',
        'png'  => 'image/png',
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif'  => 'image/gif',
        'webp' => 'image/webp',
    ];

    /**
     * Valida, guarda y devuelve la metadata del adjunto. `null` si no hay archivo.
     * Lanza DomainException si el tipo o el tamaño no son permitidos.
     */
    public static function guardar(array $f, int $solicitudId): ?array
    {
        $a = self::analizar($f);
        if ($a === null) {
            return null;
        }
        $subdir = date('Y/m');
        $clave  = substr(md5($solicitudId . uniqid('', true)), 0, 12);
        $nombre = Storage::nombreOpaco('sol', (string) $solicitudId, $clave, $a['ext']);
        $ruta   = 'solicitudes/' . $subdir . '/' . $nombre;
        // MIME derivado de la extensión (nunca del cliente) → evita XSS vía tipo_mime.
        Storage::for('solicitudes')->put($ruta, $f['tmp_name'], ['mime' => $a['mime']]);

        return [
            'tipo'            => $a['tipo'],
            'nombre_original' => $f['name'],
            'nombre_archivo'  => $nombre,
            'ruta_archivo'    => $ruta,
            'tamano_archivo'  => $f['size'],
            'tipo_mime'       => $a['mime'],
        ];
    }

    /** Valida un archivo subido sin guardarlo (para comprobar ANTES de crear). */
    public static function validar(array $f): void
    {
        if (self::analizar($f) === null) {
            throw new DomainException('No se recibió el archivo.');
        }
    }

    /**
     * Valida el descriptor de archivo y devuelve [ext, mime, tipo]. `null` si no hay archivo.
     * Lanza DomainException si el tipo o el tamaño no son permitidos.
     */
    private static function analizar(array $f): ?array
    {
        if (($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        if ((int) $f['error'] !== UPLOAD_ERR_OK) {
            throw new DomainException('Error al subir el archivo.');
        }
        $ext = strtolower((string) pathinfo((string) $f['name'], PATHINFO_EXTENSION));
        $ext = (string) preg_replace('/[^a-z0-9]/', '', $ext);
        if (!isset(self::PERMITIDAS[$ext])) {
            throw new DomainException('Tipo de archivo no permitido. Use PDF o imagen (png/jpg/jpeg/gif/webp).');
        }
        $maxMb = $ext === 'pdf'
            ? (int) (env('UPLOAD_MAX_PDF_MB') ?: 10)
            : (int) (env('UPLOAD_MAX_IMAGE_MB') ?: 5);
        if ((int) $f['size'] > $maxMb * 1024 * 1024) {
            throw new DomainException("El archivo excede el tamaño máximo permitido ({$maxMb} MB).");
        }
        return [
            'ext'  => $ext,
            'mime' => self::PERMITIDAS[$ext],
            'tipo' => $ext === 'pdf' ? 'pdf' : 'imagen',
        ];
    }

    /**
     * Normaliza $_FILES['<campo>'] (single o múltiple) a una lista de descriptores
     * con la forma estándar ['name','type','tmp_name','error','size'].
     */
    public static function normalizar(array $files): array
    {
        if (!isset($files['name'])) {
            return [];
        }
        if (!is_array($files['name'])) {
            return [$files];
        }
        $out = [];
        $n   = count($files['name']);
        for ($i = 0; $i < $n; $i++) {
            $out[] = [
                'name'     => $files['name'][$i],
                'type'     => $files['type'][$i] ?? null,
                'tmp_name' => $files['tmp_name'][$i] ?? '',
                'error'    => $files['error'][$i] ?? UPLOAD_ERR_NO_FILE,
                'size'     => $files['size'][$i] ?? 0,
            ];
        }
        return $out;
    }
}
