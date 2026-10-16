<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';

// GET /api/oficina/export.php?tipo=pse|mora|transacciones|inventario_tienda|asociados_activos|clausulas_asignadas[&start=N] → XLSX
Http::requireMethod('GET');
Http::crud('oficina.descargas', 'exportar');
$tipo  = trim((string) ($_GET['tipo'] ?? ''));
$start = isset($_GET['start']) ? (int) $_GET['start'] : null;

if (!in_array($tipo, DescargasService::tipos(), true)) {
    Http::fail('Tipo de descarga no soportado.', 400);
}

$rep = $service->exportar($tipo, $start);

require_once dirname(__DIR__, 2) . '/utils/ExcelGenerator.php';
ExcelGenerator::generateExcel($rep['data'], $rep['filename'], $rep['sheet'], $rep['widths']);