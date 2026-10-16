<?php
declare(strict_types=1);
/**
 * Simulador de crédito (B14) — endpoint PÚBLICO (sin auth).
 * POST /api/simulacion  → JSON del plan de amortización.
 * GET  /api/simulacion → líneas de crédito activas y visibles en fábrica.
 */
require_once __DIR__ . '/../core/database.php';
require_once __DIR__ . '/../core/creditos/AmortizacionService.php';

header('Content-Type: application/json; charset=utf-8');

function jsonOut(array $d): void { echo json_encode($d, JSON_UNESCAPED_UNICODE); exit; }

$db = db();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
    $lines = $db->query(
        "SELECT id, nombre_credito, tasa, seguro_vida, seguro_deudores, limite_meses
         FROM credito_lineas WHERE estado_activo = 1 AND mostrar_en_fabrica = 1
         ORDER BY nombre_credito"
    )->fetchAll();
    jsonOut(['ok' => true, 'lineas' => $lines]);
}

$raw = file_get_contents('php://input');
$in = json_decode($raw ?: '', true);
if (!is_array($in)) $in = $_POST;

$monto      = (float) ($in['monto'] ?? 0);
$numCuotas  = (int) ($in['num_cuotas'] ?? 0);
$aportes    = (float) ($in['aportes_fijos'] ?? 0);
$lineaId    = (int) ($in['linea_credito_id'] ?? 0);
$fechaInicio = trim((string) ($in['fecha_inicio'] ?? ''));
if ($fechaInicio === '') $fechaInicio = date('Y-m-d');
$primerPago = trim((string) ($in['primer_pago'] ?? ''));
if ($primerPago === '') $primerPago = date('Y-m-d', strtotime('+1 month', strtotime($fechaInicio)));

// tasas en DECIMAL (mensual); si se pasa línea, se derivan del % de credito_lineas.
$tasaInteres = null; $tasaSeguro = null; $limite = 120; $lineaNombre = null;
if ($lineaId > 0) {
    $st = $db->prepare("SELECT nombre_credito, tasa, seguro_vida, seguro_deudores, limite_meses FROM credito_lineas WHERE id=?");
    $st->execute([$lineaId]);
    $linea = $st->fetch();
    if ($linea) {
        $tasaInteres = (float) $linea['tasa'] / 100;
        $tasaSeguro  = ((float) $linea['seguro_vida'] + (float) $linea['seguro_deudores']) / 100;
        $limite      = (int) $linea['limite_meses'];
        $lineaNombre = $linea['nombre_credito'];
    }
}
if ($tasaInteres === null) $tasaInteres = (float) ($in['tasa_interes'] ?? 0);
if ($tasaSeguro === null)  $tasaSeguro  = (float) ($in['tasa_seguro'] ?? 0);

if ($monto <= 0)          jsonOut(['ok' => false, 'error' => 'El monto debe ser mayor a $0.']);
if ($numCuotas < 1 || $numCuotas > $limite) jsonOut(['ok' => false, 'error' => 'Las cuotas deben estar entre 1 y ' . $limite . '.']);
if ($tasaInteres < 0 || $tasaSeguro < 0)    jsonOut(['ok' => false, 'error' => 'Las tasas no pueden ser negativas.']);

$cuotaFija = AmortizacionService::cuotaFija($monto, $tasaInteres, $tasaSeguro, $numCuotas, $fechaInicio, $primerPago);
if ($cuotaFija <= 0) jsonOut(['ok' => false, 'error' => 'No se pudo calcular la cuota fija.']);

$plan = AmortizacionService::calcular($monto, $tasaInteres, $tasaSeguro, $cuotaFija, $aportes, $numCuotas, $fechaInicio, $primerPago);
jsonOut([
    'ok' => true,
    'tasa_interes' => $tasaInteres, 'tasa_seguro' => $tasaSeguro,
    'limite' => $limite, 'linea_nombre' => $lineaNombre,
    'cuota_fija' => $cuotaFija, 'plan' => $plan,
]);