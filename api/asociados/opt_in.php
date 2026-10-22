<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';

// PUT /api/asociados/opt_in { cedula, opt_in }
// Activa/inactiva el consentimiento maestro de comunicaciones del asociado.
Http::requireMethod('PUT');
Http::crud('asociados.detalle', 'opt_in');
Idempotencia::begin('asociados');

$in = Http::input();
$cedula = trim((string) ($in['cedula'] ?? ''));
if ($cedula === '') { Http::fail('Falta cédula'); }
$optIn = (bool) ($in['opt_in'] ?? false);
$service->setOptIn($cedula, $optIn, Auth::actor());
Http::ok(['cedula' => $cedula, 'opt_in' => $optIn ? 1 : 0]);
