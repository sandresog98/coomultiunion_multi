<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';

// GET /api/tienda/carga/plantilla → plantilla XLSX
Http::requireMethod('GET');
$filas = $carga->plantillaDatos();

require_once dirname(__DIR__, 3) . '/utils/ExcelGenerator.php';
ExcelGenerator::generateExcel($filas, 'plantilla_catalogo_tienda.xlsx', 'Catálogo', [24, 24, 40, 60, 20, 20, 16, 14]);
