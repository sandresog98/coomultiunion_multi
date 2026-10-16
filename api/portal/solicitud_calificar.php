<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';
require_once $root . '/core/solicitudes/SolicitudService.php';

// POST /api/portal/solicitud_calificar.php   { id, puntuacion, comentarios? }
// Califica (1-5) una solicitud resuelta del asociado logueado.
Http::requireMethod('POST');
Idempotencia::begin('solicitudes');
$in = Http::input();
$id = (int) ($in['id'] ?? 0);
if ($id <= 0) {
    Http::fail('Falta id');
}

$svc = new SolicitudService();
Http::ok($svc->calificar($id, $in, null, (string) $actor['cedula']));
