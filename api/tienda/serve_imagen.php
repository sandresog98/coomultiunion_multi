<?php
declare(strict_types=1);
// Endpoint abierto en navegador: sin sesión → redirige a la raíz (no JSON).
$GLOBALS['AUTH_REDIRECT_UNATH'] = '../../';
require __DIR__ . '/_bootstrap.php';

// Sirve la foto del producto desde uploads/tienda/ por ID (opaca, sin ruta visible).
Auth::requireModulo('tienda');

Http::requireMethod('GET');
$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) Http::fail('Producto no indicado.', 404);

$producto = $cat->obtenerProducto($id);
if (!$producto || empty($producto['foto_path'])) Http::fail('Imagen no encontrada.', 404);

$imagen = (string) $producto['foto_path'];
if (str_starts_with($imagen, 'http://') || str_starts_with($imagen, 'https://')) {
    header('Location: ' . $imagen);
    exit;
}
if (!preg_match('/^[A-Za-z0-9_.-]+$/', $imagen)) Http::fail('Imagen inválida.', 404);

$ruta = $cat->uploadsTiendaDir() . '/' . $imagen;
if (!is_file($ruta)) Http::fail('Imagen no encontrada.', 404);

$mime = mime_content_type($ruta) ?: 'application/octet-stream';
header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($ruta));
header('Cache-Control: private, max-age=3600');
readfile($ruta);
exit;