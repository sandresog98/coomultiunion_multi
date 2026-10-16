<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';
// DELETE /api/movimientos/eliminar { id }
Http::requireMethod('DELETE');
Http::crud('movimientos', 'eliminar');
Idempotencia::begin('movimientos');
$in = Http::input();
$id = (int) ($in['id'] ?? 0);
if ($id <= 0) { Http::fail('Falta el id.'); }
$service->eliminar($id, Auth::actor());
Http::ok(['id' => $id]);