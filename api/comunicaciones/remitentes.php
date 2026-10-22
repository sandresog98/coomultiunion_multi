<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';

/**
 * GET    /api/comunicaciones/remitentes?canal=
 * POST   /api/comunicaciones/remitentes {canal,etiqueta,direccion,...}
 * DELETE /api/comunicaciones/remitentes?id=
 */
if (Http::method() === 'GET') {
    Http::ok($remitentes->listar(trim((string) ($_GET['canal'] ?? '')) ?: null, false));
}

if (Http::method() === 'POST') {
    Http::crud('comunicaciones.remitentes', 'guardar');
    Idempotencia::begin('comunicaciones');
    $in = Http::input();
    $id = $remitentes->guardar($in, $actor);
    Http::ok(['id' => $id, 'remitente' => $remitentes->obtener($id)], 201);
}

if (Http::method() === 'DELETE') {
    Http::crud('comunicaciones.remitentes', 'eliminar');
    Idempotencia::begin('comunicaciones');
    $in = Http::input();
    $id = (int) ($in['id'] ?? 0);
    if ($id <= 0) { Http::fail('Falta el id.'); }
    $remitentes->eliminar($id, $actor);
    Http::ok(['id' => $id]);
}

Http::fail('Método no permitido', 405);
