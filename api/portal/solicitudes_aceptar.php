<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';
// POST /api/portal/solicitudes_aceptar.php  { id, items:[{config_aceptacion_id, aceptado}] }
// El asociado autoriza/acepta su solicitud (registra consentimiento + marca).
Http::requireMethod('POST');
Idempotencia::begin('portal.solicitudes');
$in = Http::input();
$id = (int) ($in['id'] ?? 0);
if ($id <= 0) Http::fail('Falta la solicitud.');
$items = is_array($in['items'] ?? null) ? $in['items'] : [];
$portal->solicitudAceptar($id, $actor['cedula'], $items);
Http::ok(['id' => $id]);