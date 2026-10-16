<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';
// GET /api/portal/movimientos.php?mes=  (YYYY-MM) → pagos/transacciones
Http::requireMethod('GET');
$mes = trim((string) ($_GET['mes'] ?? ''));
Http::ok($portal->movimientos($actor['cedula'], $mes));