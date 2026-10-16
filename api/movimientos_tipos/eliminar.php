<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';
// DELETE /api/movimientos_tipos/eliminar { tipo }  → inactiva (baja lógica)
Http::requireMethod('DELETE');
Http::crud('movimientos_tipos', 'eliminar');
Idempotencia::begin('movimientos.tipos');
$in = Http::input();
$tipo = trim((string) ($in['tipo'] ?? ''));
if ($tipo === '') { Http::fail('Falta el código del tipo.'); }
$service->eliminar($tipo, Auth::actor());
Http::ok(['tipo' => $tipo]);