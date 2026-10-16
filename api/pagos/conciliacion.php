<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';
// POST   /api/pagos/conciliacion { pse_id, confiar_id, tipo }  → crear enlace
// DELETE /api/pagos/conciliacion { pse_id, confiar_id }        → eliminar enlace
$method = $_SERVER['REQUEST_METHOD'];
Http::crud('pagos', 'conciliar');
$in = json_decode(file_get_contents('php://input'), true) ?: [];
$actor = Auth::actor();

if ($method === 'POST') {
    Idempotencia::begin('pagos');
    $pseId = trim((string) ($in['pse_id'] ?? ''));
    $confiarId = trim((string) ($in['confiar_id'] ?? ''));
    if ($pseId === '' || $confiarId === '') { Http::fail('Faltan pse_id o confiar_id'); }
    $tipo = trim((string) ($in['tipo'] ?? 'manual'));
    $service->conciliar($pseId, $confiarId, $tipo, $actor);
    Http::ok(['created' => true], 201);

} elseif ($method === 'DELETE') {
    Idempotencia::begin('pagos');
    $pseId = trim((string) ($in['pse_id'] ?? ''));
    $confiarId = trim((string) ($in['confiar_id'] ?? ''));
    if ($pseId === '' || $confiarId === '') { Http::fail('Faltan pse_id o confiar_id'); }
    $service->desconciliar($pseId, $confiarId, $actor);
    Http::ok(['deleted' => true]);

} else {
    Http::fail('Método no permitido', 405);
}
