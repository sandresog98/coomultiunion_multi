<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';
require_once $root . '/core/solicitudes/SolicitudService.php';

// GET /api/portal/boleta_pendiente.php → { pendiente: bool } para el asociado logueado.
Http::requireMethod('GET');
$svc = new SolicitudService();
Http::ok(['pendiente' => $svc->tienePendiente('boleta', (string) $actor['cedula'])]);
