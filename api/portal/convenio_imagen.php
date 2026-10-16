<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';
require_once $root . '/core/Storage/Storage.php';

// GET /api/portal/convenio_imagen.php?id=N → imagen principal del convenio (solo asociado autenticado).
Http::requireMethod('GET');
$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    Http::fail('Falta el id.', 404);
}

$st = db()->prepare("SELECT imagen FROM convenios WHERE id = ? AND activo = 1 LIMIT 1");
$st->execute([$id]);
$imagen = (string) ($st->fetchColumn() ?: '');
if ($imagen === '') {
    Http::fail('Sin imagen.', 404);
}

if (str_starts_with($imagen, 'http://') || str_starts_with($imagen, 'https://')) {
    header('Location: ' . $imagen);
    exit;
}

// Anti-traversal: basename opaco, sin separadores.
if (!preg_match('/^[A-Za-z0-9_.-]+$/', $imagen)) {
    Http::fail('Archivo no válido.', 404);
}

try {
    Storage::for('convenios')->serve('convenios/' . $imagen, [
        'mime'          => null,
        'cache_control' => 'private, max-age=3600',
    ]);
} catch (Throwable $e) {
    Http::fail('Imagen no disponible.', 404);
}
exit;
