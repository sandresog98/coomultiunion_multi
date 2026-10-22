<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';

/**
 * GET    /api/comunicaciones/media?canal=
 * POST   /api/comunicaciones/media (multipart: archivo, canal)
 * DELETE /api/comunicaciones/media?id=
 */
if (Http::method() === 'GET') {
    Http::ok($media->listar(trim((string) ($_GET['canal'] ?? '')) ?: null));
}

if (Http::method() === 'POST') {
    Http::crud('comunicaciones.media', 'subir');
    Idempotencia::begin('comunicaciones');
    if (empty($_FILES['archivo'])) {
        Http::fail('No se recibió el archivo.');
    }
    $canal = trim((string) ($_POST['canal'] ?? '')) ?: null;
    Http::ok($media->subir($_FILES['archivo'], $canal, $actor), 201);
}

if (Http::method() === 'DELETE') {
    Http::crud('comunicaciones.media', 'eliminar');
    Idempotencia::begin('comunicaciones');
    $in = Http::input();
    $id = (int) ($in['id'] ?? 0);
    if ($id <= 0) { Http::fail('Falta el id.'); }
    $media->eliminar($id, $actor);
    Http::ok(['id' => $id]);
}

Http::fail('Método no permitido', 405);
