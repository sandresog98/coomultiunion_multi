<?php
declare(strict_types=1);
// Endpoint abierto en navegador: sin sesión → redirige a la raíz (no JSON).
$GLOBALS['AUTH_REDIRECT_UNATH'] = '../../';
require_once __DIR__ . '/_bootstrap.php';
require_once $root . '/core/Storage/Storage.php';

// GET /api/pagos/serve_file?f=YYYY/MM/nombre_archivo  (o nombre_archivo plano legacy)
// Sirve comprobantes de pago. Requiere permiso del módulo y autenticación.
Http::requireMethod('GET');

$f = trim((string) ($_GET['f'] ?? ''));
if ($f === '' || $f[0] === '/' || strpos($f, '..') !== false) {
    Http::fail('Archivo no encontrado', 404);
}

Logger::descarga(Auth::actor()['id'] ?? null, 'pagos.comprobante', 'Comprobante ' . $f);

try {
    $mime = null;
    $ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
    $mime = match ($ext) {
        'pdf' => 'application/pdf',
        'jpg', 'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        default => null,
    };
    Storage::for('pagos')->serve('pagos/' . $f, [
        'mime' => $mime ?: 'application/octet-stream',
        'cache_control' => 'private, max-age=3600',
    ]);
} catch (Throwable $e) {
    Http::fail('Archivo no encontrado', 404);
}
exit;