<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';
// GET /api/portal/extractos.php?cuenta=&mes=  (YYYY-MM) → movimientos tributarios
Http::requireMethod('GET');
$cuenta = (int) ($_GET['cuenta'] ?? 0);
$mes    = trim((string) ($_GET['mes'] ?? ''));
Http::ok($portal->extractos($actor['cedula'], $cuenta, $mes));