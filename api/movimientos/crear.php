<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';
// POST /api/movimientos/crear { cedula, tipo, valor, fecha, asociado_referido?, observaciones? }
Http::requireMethod('POST');
Http::crud('movimientos', 'crear');
Idempotencia::begin('movimientos');
$in = Http::input();
$in['cedula'] = $in['cedula'] ?? '';
$id = $service->crear($in, Auth::actor());
Http::ok(['id' => $id], 201);