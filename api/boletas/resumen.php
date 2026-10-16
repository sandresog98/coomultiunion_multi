<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';

Http::requireMethod('GET');
$productoId = (int) ($_GET['producto_id'] ?? 0);
$data = $stock->resumenKpis($productoId);
$data['productos'] = $stock->productosConStock();
Http::ok($data);