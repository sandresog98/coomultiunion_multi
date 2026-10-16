<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';
// GET /api/movimientos/listar?q=&tipo=&fecha_desde=&fecha_hasta=&order=&dir=&limit=&offset=
Http::requireMethod('GET');
$q = trim((string) ($_GET['q'] ?? ''));
$tipo = trim((string) ($_GET['tipo'] ?? ''));
$fechaDesde = trim((string) ($_GET['fecha_desde'] ?? ''));
$fechaHasta = trim((string) ($_GET['fecha_hasta'] ?? ''));
$order = trim((string) ($_GET['order'] ?? 'fecha'));
$dir = trim((string) ($_GET['dir'] ?? 'DESC'));
$limit = min(200, max(1, (int) ($_GET['limit'] ?? 20)));
$offset = max(0, (int) ($_GET['offset'] ?? 0));
Http::ok($service->listar($q, $tipo, $fechaDesde, $fechaHasta, $order, $dir, $limit, $offset));