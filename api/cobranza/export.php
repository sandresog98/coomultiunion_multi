<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';
// GET /api/cobranza/export → XLSX con el reporte completo de comunicaciones
Http::requireMethod('GET');
Http::crud('cobranza.lista', 'exportar');

$db = db();
$rows = $db->query(
    "SELECT c.asociado_cedula, COALESCE(sa.nombre,'') AS nombre, c.tipo_origen, c.tipo_comunicacion, c.estado,
            c.fecha, c.fecha_compromiso, c.monto_compromiso, c.comentario, COALESCE(u.nombre_completo,'') AS creador
     FROM cobranza_comunicaciones c
     LEFT JOIN sifone_asociados sa ON sa.cedula = c.asociado_cedula
     LEFT JOIN usuarios u ON u.id = c.creado_por
     ORDER BY c.fecha DESC, c.id DESC"
)->fetchAll();

$data = [[
    'Cédula', 'Asociado', 'Tipo mora', 'Tipo', 'Estado',
    'Fecha comunicación', 'Fecha compromiso', 'Monto compromiso', 'Comentario', 'Registrado por',
]];
foreach ($rows as $r) {
    $data[] = [
        $r['asociado_cedula'], $r['nombre'], $r['tipo_origen'], $r['tipo_comunicacion'], $r['estado'],
        $r['fecha'] ? date('Y-m-d H:i', strtotime($r['fecha'])) : '', $r['fecha_compromiso'] ?? '',
        $r['monto_compromiso'] !== null ? (int) $r['monto_compromiso'] : 0, $r['comentario'] ?? '', $r['creador'],
    ];
}

require_once dirname(__DIR__, 2) . '/utils/ExcelGenerator.php';
ExcelGenerator::generateExcel($data, 'comunicaciones_cobranza_' . date('Ymd') . '.xlsx', 'Comunicaciones', [12, 28, 10, 14, 16, 18, 16, 16, 40, 20]);