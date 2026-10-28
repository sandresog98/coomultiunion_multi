<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';
// GET /api/transacciones/listar?cedula=&origen=&fecha_desde=&fecha_hasta=&limit=&offset=
Http::requireMethod('GET');
$cedula = trim((string) ($_GET['cedula'] ?? ''));
if ($cedula === '') { Http::fail('Falta cédula'); }
$origen = trim((string) ($_GET['origen'] ?? ''));
$fechaDesde = trim((string) ($_GET['fecha_desde'] ?? ''));
$fechaHasta = trim((string) ($_GET['fecha_hasta'] ?? ''));
$limit = min(200, max(1, (int) ($_GET['limit'] ?? 20)));
$offset = max(0, (int) ($_GET['offset'] ?? 0));
Http::ok($service->listar($cedula, $limit, $offset, $origen, $fechaDesde, $fechaHasta));
