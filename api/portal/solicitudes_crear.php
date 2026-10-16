<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';
// POST /api/portal/solicitudes_crear.php  { tipo_solicitante, monto_deseado, numero_cuotas_deseadas, ... }
// Crea solicitud con origen='portal' y cédula = sesión del asociado.
Http::requireMethod('POST');
Idempotencia::begin('portal.solicitudes');
$in = Http::input();
$id = $portal->solicitudCrear($actor['cedula'], $in);
Http::ok(['id' => $id], 201);