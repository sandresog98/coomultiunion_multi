<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';
// POST /api/cobranza/snapshot { cedula, comunicacion_id? } → captura snapshot de mora
Http::requireMethod('POST');
Idempotencia::begin('cobranza');
$in = Http::input();
$ced = trim((string) ($in['cedula'] ?? ''));
if ($ced === '') Http::fail('Falta cédula.');
$comm = (int) ($in['comunicacion_id'] ?? 0);
$id = $cob->capturarSnapshot($ced, $comm > 0 ? $comm : null, Auth::actor());
Http::ok(['id' => $id], 201);