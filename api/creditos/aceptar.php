<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';
// POST /api/creditos/aceptar { id, items: [{config_aceptacion_id, aceptado}] }
Http::requireMethod('POST');
$in = Http::input();
Idempotencia::begin('creditos');
Http::crud('creditos.gestionar_solicitud', 'aceptaciones');
$id = (int) ($in['id'] ?? 0); if ($id <= 0) Http::fail('Falta el id.');
$cred->guardarAceptaciones($id, $in['items'] ?? [], Auth::actor());
Http::ok(['id' => $id]);