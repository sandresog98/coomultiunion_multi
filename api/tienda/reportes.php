<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';

// API Reportes de Tienda (perfil tienda.informacion) — JSON o XLSX.
Auth::requireModulo('tienda.informacion');

$action   = trim((string) ($_GET['action'] ?? ''));
$desde    = trim((string) ($_GET['fecha_desde'] ?? ''));
$hasta    = trim((string) ($_GET['fecha_hasta'] ?? ''));

switch ($action) {
    case 'inventario':
        Http::requireMethod('GET');
        Http::ok(['rows' => $tienda->reporteInventario()]);

    case 'ventas':
        Http::requireMethod('GET');
        Http::ok(['rows' => $tienda->reporteVentas($desde, $hasta)]);

    case 'ventas_detalle':
        Http::requireMethod('GET');
        Http::ok(['rows' => $tienda->reporteVentasDetalle($desde, $hasta)]);

    case 'descargar':
        Http::requireMethod('GET');
        $tipo = trim((string) ($_GET['tipo'] ?? ''));
        $rep = match ($tipo) {
            'inventario' => ['data' => $tienda->reporteInventario(), 'filename' => 'tienda_inventario.xlsx', 'sheet' => 'Inventario',
                             'widths' => [8, 40, 24, 24, 12, 12, 12, 12, 14]],
            'ventas' => ['data' => $tienda->reporteVentas($desde, $hasta), 'filename' => 'tienda_ventas.xlsx', 'sheet' => 'Ventas',
                         'widths' => [10, 18, 12, 30, 22, 14, 14, 26]],
            'ventas_detalle' => ['data' => $tienda->reporteVentasDetalle($desde, $hasta), 'filename' => 'tienda_ventas_detalle.xlsx', 'sheet' => 'Ventas detalle',
                                 'widths' => [10, 18, 12, 30, 22, 36, 22, 22, 10, 14, 14, 14, 14]],
            default => null,
        };
        if ($rep === null) Http::fail('Tipo de reporte no soportado.', 400);
        if (count($rep['data']) === 0) {
            Http::fail('No hay datos para exportar en el rango seleccionado.', 400);
        }
        Logger::descarga(Auth::actor()['id'] ?? null, 'tienda.reportes',
            sprintf('Reporte %s %s-%s (%d filas)', $tipo, $desde ?: '*', $hasta ?: '*', max(0, count($rep['data']) - 1)));
        require_once dirname(__DIR__, 2) . '/utils/ExcelGenerator.php';
        ExcelGenerator::generateExcel($rep['data'], $rep['filename'], $rep['sheet'], $rep['widths']);
}

Http::fail('Acción no soportada.', 400);