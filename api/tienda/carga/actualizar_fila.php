<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';

// POST /api/tienda/carga/actualizar_fila { fila_id, datos:{...} }
Http::requireMethod('POST');
Http::crud('tienda.catalogo', 'carga');
Idempotencia::begin('tienda.catalogo.carga');
$in = Http::input();
$filaId = trim((string) ($in['fila_id'] ?? ''));
$datos  = $in['datos'] ?? [];
if ($filaId === '' || !is_array($datos)) {
    Http::fail('Faltan fila_id o datos.');
}
Http::ok($carga->actualizarFila($filaId, $datos, $actor));
