<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';
// GET /api/cobranza/compromisos?cedula= → historial de compromisos con cumplimiento
Http::requireMethod('GET');
$ced = trim((string) ($_GET['cedula'] ?? ''));
if ($ced === '') Http::fail('Falta cédula.');
Http::ok($com->historialCompromisos($ced));