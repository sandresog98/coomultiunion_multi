<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';
// GET /api/portal/aportes/retiro_mis_solicitudes.php?limit=&offset=
Http::requireMethod('GET');
$limit = min(200, max(1, (int) ($_GET['limit'] ?? 50)));
$offset = max(0, (int) ($_GET['offset'] ?? 0));
Http::ok($retiro->listarMias($cedula, $limit, $offset));