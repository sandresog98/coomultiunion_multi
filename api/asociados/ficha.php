<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';
// GET /api/asociados/ficha?cedula=...
Http::requireMethod('GET');
$cedula = trim((string) ($_GET['cedula'] ?? ''));
if ($cedula === '') { Http::fail('Falta cédula'); }
Http::ok($service->ficha($cedula));
