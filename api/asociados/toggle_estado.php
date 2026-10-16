<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';

// PUT /api/asociados/toggle_estado { cedula, activo }
// Activa/inactiva un asociado. El _bootstrap activa requireModulo y el handler
// DomainException→400 / resto→500.
Http::requireMethod('PUT');
Http::crud('asociados.detalle', 'toggle');
Idempotencia::begin('asociados');
$in = Http::input();
$cedula = trim((string) ($in['cedula'] ?? ''));
if ($cedula === '') { Http::fail('Falta cédula'); }

$activo = (bool) ($in['activo'] ?? true);
$service->toggleEstado($cedula, $activo, Auth::actor());
Http::ok(['cedula' => $cedula, 'estado_activo' => $activo ? 1 : 0]);