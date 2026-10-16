<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';
// GET /api/transacciones/rubros?cedula=...
// POST /api/transacciones/crear {...}
// DELETE /api/transacciones/eliminar {id}
$method = $_SERVER['REQUEST_METHOD'];
if ($method === 'POST')   Http::crud('transacciones', 'crear');
if ($method === 'DELETE') Http::crud('transacciones', 'eliminar');
$actor = Auth::actor();

if ($method === 'GET') {
    $cedula = trim((string) ($_GET['cedula'] ?? ''));
    if ($cedula === '') { Http::fail('Falta cédula'); }
    Http::ok($service->rubrosRecomendados($cedula));

} elseif ($method === 'POST') {
    Idempotencia::begin('transacciones');
    $in = json_decode(file_get_contents('php://input'), true) ?: [];
    $id = $service->crear($in, $actor);
    Http::ok(['id' => $id], 201);

} elseif ($method === 'DELETE') {
    Idempotencia::begin('transacciones');
    $in = json_decode(file_get_contents('php://input'), true) ?: [];
    $id = (int) ($in['id'] ?? 0);
    if ($id === 0) { Http::fail('Falta id'); }
    $service->eliminar($id, $actor);
    Http::ok(['deleted' => true]);

} else {
    Http::fail('Método no permitido', 405);
}
