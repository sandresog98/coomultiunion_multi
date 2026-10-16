<?php
declare(strict_types=1);
// Endpoint abierto en navegador: sin sesión → redirige a la raíz (no JSON).
$GLOBALS['AUTH_REDIRECT_UNATH'] = '../../';
require __DIR__ . '/_bootstrap.php';
require_once $root . '/core/Storage/Storage.php';

// GET /api/solicitudes/serve_adjunto.php?id=123   → descarga opaca con re-autorización del dueño.
Http::requireMethod('GET');
$adjId = (int) ($_GET['id'] ?? 0);
if ($adjId <= 0) {
    Http::fail('Falta id');
}

$adj = $service->serveAdjunto($adjId, $actor);

// La ruta en BD puede venir como 'uploads/solicitudes/...' o 'solicitudes/...'.
// Normalizamos a clave del storage (relativa a la raíz de uploads, con prefijo de módulo).
$ruta = ltrim((string) $adj['ruta_archivo'], '/');
if (str_starts_with($ruta, 'uploads/')) {
    $ruta = substr($ruta, strlen('uploads/'));
}
// Anti-traversal genérico.
if ($ruta === '' || str_contains($ruta, '..')) {
    Http::fail('Archivo no disponible', 404);
}

try {
    // MIME derivado SIEMPRE de la extensión (nunca del tipo_mime de BD, que puede ser del cliente).
    $ext = strtolower(pathinfo($ruta, PATHINFO_EXTENSION));
    $mimes = ['pdf' => 'application/pdf', 'png' => 'image/png', 'jpg' => 'image/jpeg',
              'jpeg' => 'image/jpeg', 'gif' => 'image/gif', 'webp' => 'image/webp'];
    $mime = $mimes[$ext] ?? 'application/octet-stream';
    // attachment salvo tipos seguros: un HTML/SVG viejo no se renderiza en el mismo origen (anti-XSS).
    Storage::for('solicitudes')->serve($ruta, [
        'mime'        => $mime,
        'disposition' => isset($mimes[$ext]) ? 'inline' : 'attachment',
        'filename'    => basename($ruta),
    ]);
} catch (Throwable $e) {
    Http::fail('Archivo no disponible', 404);
}
exit;