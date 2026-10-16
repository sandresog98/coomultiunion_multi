<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';
// GET /api/logs/estadisticas?fecha_desde=&fecha_hasta=
Http::requireMethod('GET');
$fechaDesde = trim((string) ($_GET['fecha_desde'] ?? ''));
$fechaHasta = trim((string) ($_GET['fecha_hasta'] ?? ''));
$estadisticas = $service->estadisticas($fechaDesde ?: null, $fechaHasta ?: null);
$opciones = $service->filtrosOpciones();
Http::ok(['estadisticas' => $estadisticas, 'opciones' => $opciones]);
