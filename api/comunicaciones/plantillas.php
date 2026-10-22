<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';

/**
 * GET  /api/comunicaciones/plantillas?canal=&aprobadas=1
 * POST /api/comunicaciones/plantillas {accion:sincronizar|guardar_local|eliminar,...}
 */
if (Http::method() === 'GET') {
    Http::ok($plantillas->listar(
        trim((string) ($_GET['canal'] ?? '')) ?: null,
        (int) ($_GET['aprobadas'] ?? 0) === 1
    ));
}

Http::requireMethod('POST');
$in = Http::input();
$accion = trim((string) ($in['accion'] ?? 'sincronizar'));

if ($accion === 'sincronizar') {
    Http::crud('comunicaciones.plantillas', 'sincronizar');
    Idempotencia::begin('comunicaciones');
    $r = $plantillas->sincronizarTwilio($actor);
    Http::ok(['resultado' => $r, 'plantillas' => $plantillas->listar()]);
}

if ($accion === 'guardar_local') {
    Http::crud('comunicaciones.plantillas', 'crear');
    Idempotencia::begin('comunicaciones');
    $id = $plantillas->guardarLocal($in, $actor);
    Http::ok(['id' => $id, 'plantilla' => $plantillas->obtener($id)], 201);
}

if ($accion === 'eliminar') {
    Http::crud('comunicaciones.plantillas', 'eliminar');
    Idempotencia::begin('comunicaciones');
    $id = (int) ($in['id'] ?? 0);
    if ($id <= 0) { Http::fail('Falta el id.'); }
    $plantillas->eliminar($id, $actor);
    Http::ok(['id' => $id]);
}

Http::fail('Acción no válida.');
