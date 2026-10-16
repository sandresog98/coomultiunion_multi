<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';

// GET /api/boletas/carga/plantilla → plantilla .xlsx para la carga masiva
Http::requireMethod('GET');
$filas = $carga->plantillaDatos();

require_once dirname(__DIR__, 3) . '/utils/ExcelGenerator.php';
ExcelGenerator::generateExcel($filas, 'plantilla_carga_boletas.xlsx', 'Plantilla', [14, 30, 22, 16, 14, 14, 14]);