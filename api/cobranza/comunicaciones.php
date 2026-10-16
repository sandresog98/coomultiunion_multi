<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';
// GET /api/cobranza/comunicaciones?cedula=
Http::requireMethod('GET');
$ced = trim((string) ($_GET['cedula'] ?? ''));
if ($ced === '') Http::fail('Falta cédula.');
Http::ok($com->listarPorCedula($ced));