<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';

// POST /api/tienda/carga/cancelar
Http::requireMethod('POST');
Http::crud('tienda.catalogo', 'carga');
Idempotencia::begin('tienda.catalogo.carga');
$carga->cancelar($actor);
Http::ok(['cancelado' => true]);
