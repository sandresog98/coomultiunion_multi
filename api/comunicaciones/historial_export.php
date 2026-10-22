<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';

Http::requireMethod('GET');
Http::crud('comunicaciones.historial', 'exportar');

$rows = $svc->historialExportar([
    'q' => $_GET['q'] ?? '',
    'canal' => $_GET['canal'] ?? '',
    'estado' => $_GET['estado'] ?? '',
    'plan_id' => $_GET['plan_id'] ?? 0,
    'desde' => $_GET['desde'] ?? '',
    'hasta' => $_GET['hasta'] ?? '',
]);

$data = [[
    'Fecha', 'Plan', 'Canal', 'Destino', 'Nombre', 'Cédula', 'Estado', 'Motivo omitido',
    'SID / ID proveedor', 'Código error', 'Error', 'Segmentos', 'Costo USD', 'Moneda',
    'Fecha envío', 'Fecha entrega', 'Fecha lectura',
]];
foreach ($rows as $r) {
    $data[] = [
        (string) ($r['fecha_creacion'] ?? ''),
        (string) ($r['plan_nombre'] ?? ''),
        (string) ($r['canal'] ?? ''),
        (string) ($r['destino'] ?? ''),
        (string) ($r['nombre'] ?? ''),
        (string) ($r['asociado_cedula'] ?? ''),
        (string) ($r['estado'] ?? ''),
        (string) ($r['motivo_omitido'] ?? ''),
        (string) ($r['proveedor_message_id'] ?? ''),
        (string) ($r['error_code'] ?? ''),
        (string) ($r['error_message'] ?? ''),
        (string) ($r['num_segmentos'] ?? ''),
        (string) ($r['precio_usd'] ?? ''),
        (string) ($r['precio_moneda'] ?? ''),
        (string) ($r['fecha_envio'] ?? ''),
        (string) ($r['fecha_entrega'] ?? ''),
        (string) ($r['fecha_lectura'] ?? ''),
    ];
}

Logger::log('descargar', 'comunicaciones.historial', $staff['id'] ?? null, 'Exportación de historial (' . count($rows) . ' filas)');
require_once dirname(__DIR__, 2) . '/utils/ExcelGenerator.php';
ExcelGenerator::generateExcel($data, 'comunicaciones_' . date('Ymd_His') . '.xlsx', 'Comunicaciones', [18, 28, 10, 22, 28, 14, 14, 20, 38, 12, 32, 10, 12, 8, 18, 18, 18]);
