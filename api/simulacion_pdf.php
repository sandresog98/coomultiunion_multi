<?php
declare(strict_types=1);
/**
 * Simulador de crédito (B14) — export PDF con el formato del legacy (PÚBLICO, sin auth).
 * Réplica de loan_simulator/api/descargar_pdf.php: 2 logos en header, tablas
 * "Parámetros" y de amortización vía writeHTML, archivo Amortizacion_*.pdf.
 */
error_reporting(0);
ini_set('display_errors', '0');
if (ob_get_level()) ob_end_clean();

require_once __DIR__ . '/../core/database.php';
require_once __DIR__ . '/../core/creditos/AmortizacionService.php';
require_once __DIR__ . '/../utils/vendor/tcpdf/tcpdf.php';

$logoApp  = __DIR__ . '/../assets/img/logo.png';
$logoCoop = __DIR__ . '/../assets/img/logo_coomultiunion.png';

$monto       = isset($_POST['monto'])                ? (float) $_POST['monto']                  : 2000000;
$numCuotas   = isset($_POST['num_cuotas'])           ? (int) $_POST['num_cuotas']               : 12;
$tasaInteres = isset($_POST['tasa_interes'])         ? (float) $_POST['tasa_interes']           : (isset($_POST['tasa_interes_mensual']) ? (float) $_POST['tasa_interes_mensual'] : 0.0176);
$tasaSeguro  = isset($_POST['tasa_seguro'])          ? (float) $_POST['tasa_seguro']            : (isset($_POST['tasa_seguro_mensual']) ? (float) $_POST['tasa_seguro_mensual'] : 0.0095);
$aportesFijos= isset($_POST['aportes_fijos'])        ? (float) $_POST['aportes_fijos']          : 65000;
$fechaInicio = (isset($_POST['fecha_inicio']) && $_POST['fecha_inicio'] !== '') ? $_POST['fecha_inicio'] : date('Y-m-d');
$primerPago  = (isset($_POST['primer_pago']) && $_POST['primer_pago'] !== '') ? $_POST['primer_pago'] : date('Y-m-d', strtotime('+1 month', strtotime($fechaInicio)));
$lineaNombre = isset($_POST['linea_credito_nombre']) ? $_POST['linea_credito_nombre'] : '';

if ($monto <= 0 || $numCuotas <= 0 || $tasaInteres < 0 || $tasaSeguro < 0) {
    http_response_code(400);
    die('Parámetros inválidos.');
}

$cuotaFija = AmortizacionService::cuotaFija($monto, $tasaInteres, $tasaSeguro, $numCuotas, $fechaInicio, $primerPago);
$resultado = AmortizacionService::calcular($monto, $tasaInteres, $tasaSeguro, $cuotaFija, $aportesFijos, $numCuotas, $fechaInicio, $primerPago);

$fmt = fn($n) => '$' . number_format((int) round($n), 0);
$tasaInteresPct = number_format($tasaInteres * 100, 2);
$tasaSeguroPct  = number_format($tasaSeguro * 100, 2);

class AmortizacionPDF extends TCPDF
{
    private $logoApp;
    private $logoCoop;

    public function __construct(string $logoApp, string $logoCoop)
    {
        parent::__construct('P', 'mm', 'LETTER', true, 'UTF-8', false);
        $this->logoApp  = $logoApp;
        $this->logoCoop = $logoCoop;
        $this->SetCreator('Coomultiunion');
        $this->SetAuthor('Simulador de Crédito');
        $this->SetTitle('Tabla de Amortización');
        $this->SetMargins(10, 20, 10);
        $this->SetAutoPageBreak(true, 12);
    }

    public function Header()
    {
        if (is_file($this->logoApp))  $this->Image($this->logoApp, 10, 5, 22, 0, 'PNG');
        if (is_file($this->logoCoop)) $this->Image($this->logoCoop, 184, 5, 22, 0, 'PNG');
        $this->SetY(18);
    }

    public function Footer()
    {
        $this->SetY(-15);
        $this->SetFont('helvetica', 'I', 7);
        $this->Cell(0, 10, 'Simulador de Crédito Coomultiunion — ' . date('d/m/Y H:i'), 0, 0, 'C');
    }
}

$pdf = new AmortizacionPDF($logoApp, $logoCoop);
$pdf->AddPage();

$primary = '#1882CD';
$html = '<style>
    h2 { color: ' . $primary . '; font-size: 13pt; margin: 0 0 4px 0; text-align: center; }
    .params { width: 100%; border-collapse: collapse; margin-bottom: 8px; table-layout: fixed; border: 1px solid #999; }
    .params th { background-color: ' . $primary . '; color: #fff; font-size: 9pt; padding: 5px; text-align: center; border: 1px solid #999; }
    .params td { font-size: 8.5pt; padding: 3px 6px; border: 1px solid #999; }
    .params td.v { text-align: left; }
    .params .c1 { width: 27%; font-weight: bold; }
    .params .c1w { width: 25%; font-weight: bold; }
    .params .c2 { width: 23%; }
    .params .c3 { width: 27%; font-weight: bold; }
    .params .c4 { width: 23%; }
    .amort { width: 100%; border-collapse: collapse; font-size: 6pt; table-layout: fixed; border: 1px solid #999; }
    .amort th { background-color: ' . $primary . '; color: #fff; font-weight: bold; padding: 2px; text-align: center; border: 1px solid #999; }
    .amort td { padding: 1.5px 2px; border: 1px solid #999; text-align: right; }
    .amort td.l { text-align: left; }
    .amort .w1{width:4%}.amort .w2{width:11%}.amort .w3{width:5%}.amort .w4{width:12%}.amort .w5{width:10%}
    .amort .w6{width:9%}.amort .w7{width:9%}.amort .w8{width:9%}.amort .w9{width:9%}.amort .w10{width:11%}.amort .w11{width:11%}
    .amort tr.last td { font-weight: bold; background-color: #d4edda; }
</style>';

$html .= '<h2>Simulador de Crédito Coomultiunion</h2>';

$html .= '<table class="params">';
$html .= '<tr><th colspan="4">Parámetros del crédito</th></tr>';
if ($lineaNombre !== '') {
    $html .= '<tr><td class="c1w">Línea de crédito</td><td class="v" colspan="3">' . htmlspecialchars($lineaNombre, ENT_QUOTES, 'UTF-8') . '</td></tr>';
}
$html .= '<tr><td class="c1">Monto</td><td class="v c2">' . $fmt($monto) . '</td><td class="c3">Aportes fijos</td><td class="v c4">' . $fmt($aportesFijos) . '</td></tr>';
$html .= '<tr><td class="c1">Nº cuotas</td><td class="v c2">' . $numCuotas . '</td><td class="c3">Fecha inicio</td><td class="v c4">' . $fechaInicio . '</td></tr>';
$html .= '<tr><td class="c1">Tasa interés mensual</td><td class="v c2">' . $tasaInteresPct . '%</td><td class="c3">Primer pago</td><td class="v c4">' . $primerPago . '</td></tr>';
$html .= '<tr><td class="c1">Tasa seguro mensual</td><td class="v c2">' . $tasaSeguroPct . '%</td><td class="c3">Cuota fija</td><td class="v c4">' . $fmt($cuotaFija) . '</td></tr>';
$html .= '</table>';

$html .= '<table class="amort">';
$html .= '<tr><th class="w1">#</th><th class="w2">Fecha pago</th><th class="w3">Días</th><th class="w4">Saldo inicial</th><th class="w5">Interés</th><th class="w6">Seguro</th><th class="w7">Abono</th><th class="w8">Cuota</th><th class="w9">Aportes</th><th class="w10">Total</th><th class="w11">Saldo final</th></tr>';
foreach ($resultado['tabla'] as $fila) {
    $cls = $fila['saldo_final'] === 0 ? ' class="last"' : '';
    $html .= '<tr' . $cls . '>';
    $html .= '<td class="l w1">' . $fila['cuota'] . '</td>';
    $html .= '<td class="l w2">' . $fila['fecha_pago'] . '</td>';
    $html .= '<td class="w3">' . $fila['dias'] . '</td>';
    $html .= '<td class="w4">' . $fmt($fila['saldo_inicial']) . '</td>';
    $html .= '<td class="w5">' . $fmt($fila['interes']) . '</td>';
    $html .= '<td class="w6">' . $fmt($fila['seguro']) . '</td>';
    $html .= '<td class="w7">' . $fmt($fila['abono_capital']) . '</td>';
    $html .= '<td class="w8">' . $fmt($fila['cuota_fija']) . '</td>';
    $html .= '<td class="w9">' . $fmt($fila['aportes']) . '</td>';
    $html .= '<td class="w10">' . $fmt($fila['cuota_total']) . '</td>';
    $html .= '<td class="w11">' . $fmt($fila['saldo_final']) . '</td>';
    $html .= '</tr>';
}
$html .= '</table>';

$pdf->writeHTML($html, true, false, true, false, '');

$pdf->Output('Amortizacion_' . date('Ymd_His') . '.pdf', 'D');