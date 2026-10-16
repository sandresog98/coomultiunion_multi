<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';
// GET /api/logs/listar?usuario=&modulo=&accion=&nivel=&fecha_desde=&fecha_hasta=&q=&order=&dir=&limit=&offset=
Http::requireMethod('GET');
$filtros = [];
foreach (['usuario','modulo','accion','nivel','fecha_desde','fecha_hasta','q'] as $k) {
    $v = trim((string) ($_GET[$k] ?? ''));
    if ($v !== '') $filtros[$k] = $v;
}
$filtros['limit'] = min(200, max(1, (int) ($_GET['limit'] ?? 20)));
$filtros['offset'] = max(0, (int) ($_GET['offset'] ?? 0));
$filtros['order'] = trim((string) ($_GET['order'] ?? 'id'));
$filtros['dir'] = trim((string) ($_GET['dir'] ?? 'DESC'));
Http::ok($service->listar($filtros));
