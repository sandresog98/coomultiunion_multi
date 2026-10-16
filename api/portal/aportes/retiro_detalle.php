<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';
// GET /api/portal/aportes/retiro_detalle.php?id=
Http::requireMethod('GET');
$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    Http::fail('Falta id');
}
Http::ok($retiro->detalle($cedula, $id));