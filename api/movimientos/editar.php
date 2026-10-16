<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';
// PUT /api/movimientos/editar { id, cedula?, tipo?, valor?, fecha?, asociado_referido?, observaciones? }
Http::requireMethod('PUT');
Http::crud('movimientos', 'editar');
Idempotencia::begin('movimientos');
$in = Http::input();
$id = (int) ($in['id'] ?? 0);
if ($id <= 0) { Http::fail('Falta el id.'); }
$service->editar($id, $in, Auth::actor());
Http::ok(['id' => $id]);