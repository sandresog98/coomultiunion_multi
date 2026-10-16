<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';
// POST /api/creditos/valores { id, valores: [{config_campo_id, valor|borrar}] } → guarda EAV
Http::requireMethod('POST');
$in = Http::input();
Idempotencia::begin('creditos');
Http::crud('creditos.gestionar_solicitud', 'campos');
$id = (int) ($in['id'] ?? 0); if ($id <= 0) Http::fail('Falta el id.');
$cred->guardarValores($id, $in['valores'] ?? [], Auth::actor());
Http::ok(['id' => $id]);