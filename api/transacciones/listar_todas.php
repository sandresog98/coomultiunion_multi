<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';
// GET /api/transacciones/listar_todas?q=&origen=&fecha_desde=&fecha_hasta=&limit=&offset=
Http::requireMethod('GET');
$q = trim((string) ($_GET['q'] ?? ''));
$origen = trim((string) ($_GET['origen'] ?? ''));
$fechaDesde = trim((string) ($_GET['fecha_desde'] ?? ''));
$fechaHasta = trim((string) ($_GET['fecha_hasta'] ?? ''));
$limit = min(200, max(1, (int) ($_GET['limit'] ?? 20)));
$offset = max(0, (int) ($_GET['offset'] ?? 0));
Http::ok($service->listarTodas($q, $origen, $fechaDesde, $fechaHasta, $limit, $offset));
