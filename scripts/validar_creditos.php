<?php
declare(strict_types=1);
/**
 * Validador de la fábrica de créditos migrada (CLI).
 *
 * Uso:
 *   php scripts/validar_creditos.php            # cobertura por campo + incidencias
 *   php scripts/validar_creditos.php 83         # detalle de una solicitud (valores + documentos)
 */
require_once dirname(__DIR__) . '/core/database.php';

$db = db();
$soloId = isset($argv[1]) ? (int) $argv[1] : 0;

function line(string $c = '-'): void { echo str_repeat($c, 78) . PHP_EOL; }

if ($soloId > 0) {
    $st = $db->prepare('SELECT * FROM credito_solicitudes WHERE id = ?');
    $st->execute([$soloId]);
    $s = $st->fetch();
    if (!$s) { echo "No existe la solicitud #$soloId\n"; exit(1); }

    line('=');
    echo "Solicitud #{$s['id']} · {$s['numero_solicitud']} · etapa {$s['etapa_actual']} · estado {$s['estado']}\n";
    echo "Cédula: {$s['cedula_asociado']} · origen: {$s['origen']} · legado: " . ($s['origen_id'] ?? '—') . "\n";
    line('=');

    $st = $db->prepare(
        'SELECT c.campo, c.nombre, v.valor
           FROM credito_campos_valores v
           JOIN credito_config_campo c ON c.id = v.config_campo_id
          WHERE v.solicitud_id = ?
          ORDER BY c.id'
    );
    $st->execute([$soloId]);
    $vals = $st->fetchAll();
    echo "VARIABLES (" . count($vals) . "):\n";
    foreach ($vals as $v) {
        printf("  %-22s %-28s %s\n", $v['campo'], $v['nombre'], $v['valor']);
    }
    if (!$vals) echo "  (ninguna)\n";

    $st = $db->prepare(
        'SELECT id, etapa, tipo_documento, nombre_archivo, ruta_archivo
           FROM credito_documentos WHERE solicitud_id = ? ORDER BY etapa, tipo_documento'
    );
    $st->execute([$soloId]);
    $docs = $st->fetchAll();
    echo "\nDOCUMENTOS (" . count($docs) . "):\n";
    $uploads = rtrim((string) (getenv('UPLOAD_DIR') ?: (dirname(__DIR__) . '/uploads')), '/');
    foreach ($docs as $d) {
        $fisico = $uploads . '/creditos/' . $d['ruta_archivo'];
        printf("  %-18s %-30s %-34s %s\n", $d['etapa'], $d['tipo_documento'],
            mb_substr((string) $d['nombre_archivo'], 0, 32), is_file($fisico) ? 'OK' : 'SIN ARCHIVO');
    }
    exit(0);
}

// ---- Resumen / cobertura ----
$totalMig = (int) $db->query("SELECT COUNT(*) FROM credito_solicitudes WHERE origen_id IS NOT NULL")->fetchColumn();
$totalNew = (int) $db->query("SELECT COUNT(*) FROM credito_solicitudes WHERE origen_id IS NULL")->fetchColumn();
$conVars  = (int) $db->query("SELECT COUNT(*) FROM credito_solicitudes s WHERE s.origen_id IS NOT NULL
                              AND EXISTS (SELECT 1 FROM credito_campos_valores v WHERE v.solicitud_id = s.id AND v.config_campo_id IS NOT NULL)")->fetchColumn();

line('=');
echo "FÁBRICA DE CRÉDITOS — migradas: $totalMig · nuevas (v2): $totalNew · con variables: $conVars\n";
line('=');

echo "\nCobertura por campo (sobre las migradas):\n";
$rows = $db->query(
    "SELECT c.campo, c.nombre, COUNT(v.id) con_valor
       FROM credito_config_campo c
       LEFT JOIN credito_campos_valores v ON v.config_campo_id = c.id
      GROUP BY c.id, c.campo, c.nombre
      ORDER BY c.id"
)->fetchAll();
printf("  %-22s %-28s %8s  %s\n", 'campo', 'nombre', 'con valor', '%');
foreach ($rows as $r) {
    $pct = $totalMig > 0 ? round(100 * (int) $r['con_valor'] / $totalMig, 1) : 0;
    printf("  %-22s %-28s %8d  %5.1f%%\n", $r['campo'], $r['nombre'], (int) $r['con_valor'], $pct);
}

echo "\nIncidencias:\n";
$sinVars = $db->query(
    "SELECT s.id, s.numero_solicitud FROM credito_solicitudes s
      WHERE s.origen_id IS NOT NULL
        AND NOT EXISTS (SELECT 1 FROM credito_campos_valores v WHERE v.solicitud_id = s.id AND v.config_campo_id IS NOT NULL)
      ORDER BY s.id"
)->fetchAll();
echo '  Migradas sin ninguna variable: ' . count($sinVars) . "\n";
foreach (array_slice($sinVars, 0, 10) as $r) echo "    #{$r['id']} {$r['numero_solicitud']}\n";

$sinDocs = $db->query(
    "SELECT s.id, s.numero_solicitud FROM credito_solicitudes s
      WHERE s.origen_id IS NOT NULL
        AND NOT EXISTS (SELECT 1 FROM credito_documentos d WHERE d.solicitud_id = s.id)
      ORDER BY s.id"
)->fetchAll();
echo '  Migradas sin documentos: ' . count($sinDocs) . "\n";
foreach (array_slice($sinDocs, 0, 10) as $r) echo "    #{$r['id']} {$r['numero_solicitud']}\n";

$incons = $db->query(
    "SELECT id, numero_solicitud FROM credito_solicitudes
      WHERE origen_id IS NOT NULL
        AND monto_aprobado IS NOT NULL
        AND (linea_credito_aprobada_id IS NULL OR cuotas_aprobadas IS NULL)
      ORDER BY id"
)->fetchAll();
echo '  Con monto aprobado pero sin línea/cuotas: ' . count($incons) . "\n";
foreach (array_slice($incons, 0, 10) as $r) echo "    #{$r['id']} {$r['numero_solicitud']}\n";

line('=');
echo "Detalle de una solicitud: php scripts/validar_creditos.php <id>\n";
