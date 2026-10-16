<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';
// PUT /api/movimientos_tipos/editar { tipo, nombre?, clasificacion?, requiere_referido?, valida_disponible?, activo? }
Http::requireMethod('PUT');
Http::crud('movimientos_tipos', 'editar');
Idempotencia::begin('movimientos.tipos');
$in = Http::input();
$tipo = trim((string) ($in['tipo'] ?? ''));
if ($tipo === '') { Http::fail('Falta el código del tipo.'); }
$service->editar($tipo, $in, Auth::actor());
Http::ok(['tipo' => $tipo]);