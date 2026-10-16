<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';

Http::requireMethod('GET');
$estados = null;
if (isset($_GET['estados'])) {
    $estados = array_values(array_filter((array) $_GET['estados'], fn ($e) => in_array($e, ['disponible', 'vendida', 'contabilizada', 'anulada'], true)));
}

Http::ok($stock->listar(
    (int) ($_GET['producto_id'] ?? 0),
    $estados,
    trim((string) ($_GET['q'] ?? '')),
    trim((string) ($_GET['cedula'] ?? '')),
    trim((string) ($_GET['fecha_creacion_desde'] ?? '')),
    trim((string) ($_GET['fecha_creacion_hasta'] ?? '')),
    trim((string) ($_GET['fecha_vendida_desde'] ?? '')),
    trim((string) ($_GET['fecha_vendida_hasta'] ?? '')),
    trim((string) ($_GET['order'] ?? 'fecha_creacion')),
    trim((string) ($_GET['dir'] ?? 'DESC')),
    min(200, max(1, (int) ($_GET['limit'] ?? 20))),
    max(0, (int) ($_GET['offset'] ?? 0))
));