<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';

// GET /api/boletas/descargar_reporte?accion=boletas|vendidas → XLSX
Http::requireMethod('GET');
$accion = trim((string) ($_GET['accion'] ?? ''));
$rep = $stock->reporteBoletas($accion);

Logger::descarga(Auth::actor()['id'] ?? null, 'boletas.reportes',
    sprintf('Reporte de boletas accion=%s (%d filas)', $accion, max(0, count($rep['data']) - 1)));

require_once dirname(__DIR__, 2) . '/utils/ExcelGenerator.php';
ExcelGenerator::generateExcel($rep['data'], $rep['filename'], $rep['sheet'], $rep['widths']);