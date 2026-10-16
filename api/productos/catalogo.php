<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $q = trim((string) ($_GET['q'] ?? ''));
    $orderBy = trim((string) ($_GET['order'] ?? 'tipo'));
    $orderDir = trim((string) ($_GET['dir'] ?? 'ASC'));
    $limit = min(200, max(1, (int) ($_GET['limit'] ?? 20)));
    $offset = max(0, (int) ($_GET['offset'] ?? 0));
    Http::ok($service->catalogoListar($q, $orderBy, $orderDir, $limit, $offset));

} elseif ($method === 'POST') {
    Idempotencia::begin('productos');
    $in = json_decode(file_get_contents('php://input'), true) ?: [];
    $actor = Auth::actor();
    $id = $service->catalogoCrear($in, $actor);
    Http::ok(['id' => $id], 201);

} elseif ($method === 'PUT') {
    Idempotencia::begin('productos');
    $in = json_decode(file_get_contents('php://input'), true) ?: [];
    $id = (int) ($in['id'] ?? 0);
    if ($id === 0) { Http::fail('Falta id'); }
    $actor = Auth::actor();
    $service->catalogoEditar($id, $in, $actor);
    Http::ok(['updated' => true]);

} elseif ($method === 'DELETE') {
    Idempotencia::begin('productos');
    $in = json_decode(file_get_contents('php://input'), true) ?: [];
    $id = (int) ($in['id'] ?? 0);
    if ($id === 0) { Http::fail('Falta id'); }
    $actor = Auth::actor();
    $service->catalogoEliminar($id, $actor);
    Http::ok(['deleted' => true]);

} else {
    Http::fail('Método no permitido', 405);
}
