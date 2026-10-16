<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';
// GET /api/transacciones/listar?cedula=&limit=&offset=
Http::requireMethod('GET');
$cedula = trim((string) ($_GET['cedula'] ?? ''));
if ($cedula === '') { Http::fail('Falta cédula'); }
$limit = min(200, max(1, (int) ($_GET['limit'] ?? 20)));
$offset = max(0, (int) ($_GET['offset'] ?? 0));
Http::ok($service->listar($cedula, $limit, $offset));
