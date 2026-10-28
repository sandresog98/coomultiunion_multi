<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';
// GET /api/pagos/listar?tipo=pse|confiar&q=&estado=&conciliado=&fecha_desde=&fecha_hasta=&limit=&offset=&order=&dir=
// Confiar acepta descripcion (string) o descripcion[] (multifiltro).
Http::requireMethod('GET');
$tipo = trim((string) ($_GET['tipo'] ?? 'pse'));
$q = trim((string) ($_GET['q'] ?? ''));
$estado = trim((string) ($_GET['estado'] ?? ''));
$conciliado = trim((string) ($_GET['conciliado'] ?? ''));
$fechaDesde = trim((string) ($_GET['fecha_desde'] ?? ''));
$fechaHasta = trim((string) ($_GET['fecha_hasta'] ?? ''));
$asignacion = trim((string) ($_GET['asignacion'] ?? ''));
$pse = trim((string) ($_GET['pse'] ?? ''));
$confiarId = trim((string) ($_GET['confiar_id'] ?? ''));
$descripcion = $_GET['descripcion'] ?? '';
if (!is_array($descripcion)) {
    $descripcion = [$descripcion];
}
$descripcion = array_values(array_filter(
    array_map(static fn($d) => trim((string) $d), $descripcion),
    static fn($d) => $d !== ''
));
$disponible = (($_GET['disponible'] ?? '') === '1');
$limit = min(200, max(1, (int) ($_GET['limit'] ?? 20)));
$offset = max(0, (int) ($_GET['offset'] ?? 0));
$orderBy = trim((string) ($_GET['order'] ?? ($tipo === 'confiar' ? 'fecha' : 'fecha_resolucion')));
$orderDir = trim((string) ($_GET['dir'] ?? 'DESC'));

if ($tipo === 'confiar') {
    Http::ok($service->listarConfiar($q, $estado, $fechaDesde, $fechaHasta, $asignacion, $orderBy, $orderDir, $limit, $offset, $pse, $descripcion, $disponible));
} else {
    Http::ok($service->listarPse($q, $estado, $conciliado, $fechaDesde, $fechaHasta, $asignacion, $orderBy, $orderDir, $limit, $offset, $confiarId));
}
