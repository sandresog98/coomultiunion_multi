<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';

Http::requireMethod('GET');
$productoId = (int) ($_GET['producto_id'] ?? 0);
$serial = trim((string) ($_GET['serial'] ?? ''));
if ($productoId <= 0 || $serial === '') Http::fail('Parámetros requeridos.');
Http::ok(['exists' => $stock->existeSerial($productoId, $serial)]);