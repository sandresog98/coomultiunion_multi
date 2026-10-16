<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';

// POST /api/boletas/carga/commit { rows:[{producto_id,serial,numero_factura,precio_compra,precio_venta,fecha_vencimiento}, ...] }
// Re-valida TODO en servidor; con ≥1 error responde 400 y NO inserta nada.
Http::requireMethod('POST');
Http::crud('convenios.carga', 'procesar');
$actor = Auth::actor();
Idempotencia::begin('boletas.carga');
$in = Http::input();
$rows = $in['rows'] ?? [];
Http::ok($carga->commit($rows, $actor));