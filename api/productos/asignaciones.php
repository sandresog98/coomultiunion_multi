<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';

$method = $_SERVER['REQUEST_METHOD'];
if ($method === 'POST')    Http::crud('asociados.detalle', 'asignar');
if ($method === 'PUT')     Http::crud('asociados.detalle', 'editar');
if ($method === 'DELETE')  Http::crud('asociados.detalle', 'eliminar');

if ($method === 'GET') {
    $cedula = trim((string) ($_GET['cedula'] ?? ''));
    if ($cedula === '') { Http::fail('Falta cédula'); }
    Http::ok($service->asignacionesPorAsociado($cedula));

} elseif ($method === 'POST') {
    Idempotencia::begin('productos');
    $in = json_decode(file_get_contents('php://input'), true) ?: [];
    $cedula = trim((string) ($in['cedula'] ?? ''));
    $productoId = (int) ($in['producto_id'] ?? 0);
    if ($cedula === '' || $productoId === 0) { Http::fail('Faltan cédula o producto_id'); }
    $actor = Auth::actor();
    $id = $service->asignar($cedula, $productoId, $in, $actor);
    Http::ok(['id' => $id], 201);

} elseif ($method === 'PUT') {
    Idempotencia::begin('productos');
    $in = json_decode(file_get_contents('php://input'), true) ?: [];
    $id = (int) ($in['id'] ?? 0);
    if ($id === 0) { Http::fail('Falta id'); }
    $actor = Auth::actor();
    $service->editar($id, $in, $actor);
    Http::ok(['updated' => true]);

} elseif ($method === 'DELETE') {
    Idempotencia::begin('productos');
    $in = json_decode(file_get_contents('php://input'), true) ?: [];
    $id = (int) ($in['id'] ?? 0);
    if ($id === 0) { Http::fail('Falta id'); }
    $actor = Auth::actor();
    $service->eliminar($id, $actor);
    Http::ok(['deleted' => true]);

} else {
    Http::fail('Método no permitido', 405);
}
