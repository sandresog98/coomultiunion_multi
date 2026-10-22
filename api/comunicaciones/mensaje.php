<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';

/**
 * GET  /api/comunicaciones/mensaje?id=       → detalle
 * POST /api/comunicaciones/mensaje {id}      → reintentar
 */
if (Http::method() === 'GET') {
    $id = (int) ($_GET['id'] ?? 0);
    if ($id <= 0) { Http::fail('Falta el id del mensaje.'); }
    Http::ok($svc->detalleMensaje($id));
}

Http::requireMethod('POST');
Http::crud('comunicaciones.planes', 'reintentar');
Idempotencia::begin('comunicaciones');
$in = Http::input();
$id = (int) ($in['id'] ?? 0);
if ($id <= 0) { Http::fail('Falta el id del mensaje.'); }
$envio->reintentar($id, $actor);
$detalle = $svc->detalleMensaje($id);
$svc->recalcularPlan((int) ($detalle['mensaje']['plan_id'] ?? 0));
Http::ok($detalle);
