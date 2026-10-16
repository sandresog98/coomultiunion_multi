<?php
declare(strict_types=1);
// Endpoint abierto en navegador: sin sesión → redirige a la raíz (no JSON).
$GLOBALS['AUTH_REDIRECT_UNATH'] = '../../';
require __DIR__ . '/_bootstrap.php';
require_once $root . '/core/Storage/Storage.php';

// GET /api/convenios/serve_imagen?ent=convenio|producto&id=N → imagen (basename opaco o URL externa)
Http::requireMethod('GET');
$ent  = trim((string) ($_GET['ent'] ?? 'convenio'));
$id   = (int) ($_GET['id'] ?? 0);
if ($id <= 0) Http::fail('Falta el id.', 404);

$imagen = $ent === 'producto'
    ? (($p = $cv->obtenerProducto($id)) ? ($p['imagen'] ?? '') : '')
    : (($c = $cv->obtenerConvenio($id)) ? ($c['imagen'] ?? '') : '');
if ($imagen === '' || $imagen === null) Http::fail('Sin imagen.', 404);

if (str_starts_with($imagen, 'http://') || str_starts_with($imagen, 'https://')) {
    header('Location: ' . $imagen);
    exit;
}

// Anti-traversal: basename opaco, sin separadores.
if (!preg_match('/^[A-Za-z0-9_.-]+$/', $imagen)) Http::fail('Archivo no válido.', 404);

try {
    Storage::for('convenios')->serve('convenios/' . $imagen, [
        'mime'         => null, // se detecta solo en el driver/stream
        'cache_control' => 'private, max-age=3600',
    ]);
} catch (Throwable $e) {
    Http::fail('Imagen no disponible.', 404);
}
exit;