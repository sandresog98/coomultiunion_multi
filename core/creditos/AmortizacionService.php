<?php
declare(strict_types=1);

/**
 * Cálculo de amortización para créditos (port de ui/utils/amortizacion_calculo.php).
 * Cuota fija (búsqueda binaria), interés/seguro/abono por cuota, plan de pagos.
 */
final class AmortizacionService
{
    /** Plan de pagos completo. */
    public static function calcular(float $monto, float $tasaInteres, float $tasaSeguro, float $cuotaFija, float $aportesFijos, int $totalCuotas, string $fechaInicio, string $primerPago): array
    {
        $tabla = [];
        $saldoCapital = $monto;
        $interesAcumulado = 0;
        $fechaCorte = new DateTime($fechaInicio);
        $fechaBase  = new DateTime($primerPago);
        $diaBase    = (int) $fechaBase->format('d');
        $totalInteres = 0; $totalSeguro = 0; $totalCapital = 0; $totalCuotaTotal = 0;

        for ($cuotaNum = 1; $cuotaNum <= $totalCuotas && ($saldoCapital > 0 || $interesAcumulado > 0); $cuotaNum++) {
            $fechaPago = clone $fechaBase;
            $fechaPago->modify('+' . ($cuotaNum - 1) . ' months');
            if ((int) $fechaPago->format('d') !== $diaBase) $fechaPago->modify('last day of last month');
            $dias = (int) $fechaCorte->diff($fechaPago)->days;
            if ($dias <= 0) {
                $fechaPago = clone $fechaBase;
                $fechaPago->modify('+' . $cuotaNum . ' months');
                if ((int) $fechaPago->format('d') !== $diaBase) $fechaPago->modify('last day of last month');
                $dias = (int) $fechaCorte->diff($fechaPago)->days;
            }
            $saldoInicial = (int) round($saldoCapital + $interesAcumulado);
            $interes = (int) round($saldoCapital * ($tasaInteres / 30) * $dias);
            $seguro  = (int) round($saldoCapital * ($tasaSeguro  / 30) * $dias);
            $abonoVisual = (int) round($cuotaFija) - $interes - $seguro;

            if ($cuotaNum === $totalCuotas) {
                $abonoCapital = (int) round($saldoCapital + $interesAcumulado);
                $saldoCapital = 0;
                $interesAcumulado = 0;
                $cuotaReal = (int) round($abonoCapital + $interes + $seguro);
            } else {
                if ($abonoVisual < 0) {
                    $interesAcumulado += abs($abonoVisual);
                } else {
                    $pagoAcumulado = min($interesAcumulado, $abonoVisual);
                    $interesAcumulado -= $pagoAcumulado;
                    $abonoReal = $abonoVisual - $pagoAcumulado;
                    $saldoCapital -= $abonoReal;
                }
                $abonoCapital = $abonoVisual;
                $cuotaReal = $cuotaFija;
            }
            $saldoFinal = (int) round($saldoCapital + $interesAcumulado);
            $cuotaTotal = $cuotaReal + (int) round($aportesFijos);
            $tabla[] = [
                'cuota' => $cuotaNum, 'fecha_pago' => $fechaPago->format('Y-m-d'), 'dias' => $dias,
                'saldo_inicial' => $saldoInicial, 'interes' => $interes, 'seguro' => $seguro,
                'abono_capital' => $abonoCapital, 'cuota_fija' => $cuotaReal, 'aportes' => (int) round($aportesFijos),
                'cuota_total' => $cuotaTotal, 'saldo_final' => $saldoFinal,
            ];
            $totalInteres += $interes; $totalSeguro += $seguro; $totalCapital += $abonoCapital; $totalCuotaTotal += $cuotaTotal;
            if ($saldoCapital <= 0 && $interesAcumulado <= 0) break;
            $fechaCorte = clone $fechaPago;
        }
        $n = count($tabla);
        return [
            'tabla' => $tabla, 'num_cuotas' => $n,
            'total_interes' => $totalInteres, 'total_seguro' => $totalSeguro, 'total_capital' => $totalCapital,
            'total_cuotas' => $totalCuotaTotal, 'total_aportes' => (int) round($aportesFijos * $n),
            'total_general' => $totalCuotaTotal + (int) round($aportesFijos * $n),
            'fecha_fin' => $fechaPago->format('Y-m-d'),
        ];
    }

    private static function simularSaldoFinal(float $monto, float $tasaInteres, float $tasaSeguro, float $cuotaPrueba, int $totalCuotas, string $fechaInicio, string $primerPago): int
    {
        $saldoCapital = $monto; $interesAcumulado = 0; $fechaCorte = new DateTime($fechaInicio); $fechaBase = new DateTime($primerPago); $diaBase = (int) $fechaBase->format('d');
        for ($n = 1; $n <= $totalCuotas && ($saldoCapital > 0 || $interesAcumulado > 0); $n++) {
            $fechaPago = clone $fechaBase;
            $fechaPago->modify('+' . ($n - 1) . ' months');
            if ((int) $fechaPago->format('d') !== $diaBase) $fechaPago->modify('last day of last month');
            $dias = (int) $fechaCorte->diff($fechaPago)->days;
            if ($dias <= 0) {
                $fechaPago = clone $fechaBase; $fechaPago->modify('+' . $n . ' months');
                if ((int) $fechaPago->format('d') !== $diaBase) $fechaPago->modify('last day of last month');
                $dias = (int) $fechaCorte->diff($fechaPago)->days;
            }
            $interes = (int) round($saldoCapital * ($tasaInteres / 30) * $dias);
            $seguro  = (int) round($saldoCapital * ($tasaSeguro  / 30) * $dias);
            $abonoVisual = (int) round($cuotaPrueba) - $interes - $seguro;

            if ($abonoVisual < 0) {
                $interesAcumulado += abs($abonoVisual);
            } else {
                $pagoAcumulado = min($interesAcumulado, $abonoVisual);
                $interesAcumulado -= $pagoAcumulado;
                $abonoReal = $abonoVisual - $pagoAcumulado;
                $saldoCapital -= $abonoReal;
            }

            $fechaCorte = clone $fechaPago;
        }
        return (int) round($saldoCapital + $interesAcumulado);
    }

    public static function cuotaFija(float $monto, float $tasaInteres, float $tasaSeguro, int $numCuotas, string $fechaInicio, string $primerPago): int
    {
        $low = 0; $high = (int) round($monto); $bestCuota = 0; $bestSaldo = PHP_INT_MAX;
        for ($i = 0; $i < 200 && $low <= $high; $i++) {
            $mid = (int) round(($low + $high) / 2);
            if ($mid <= 0) { $low = 1; continue; }
            $sf = self::simularSaldoFinal($monto, $tasaInteres, $tasaSeguro, $mid, $numCuotas, $fechaInicio, $primerPago);
            if (abs($sf) < abs($bestSaldo)) { $bestCuota = $mid; $bestSaldo = $sf; }
            if ($sf >= -1 && $sf <= 1) return $mid;
            $sf > 0 ? $low = $mid + 1 : $high = $mid - 1;
        }
        return $bestCuota > 0 ? $bestCuota : (int) round($low);
    }

    /** Valor a desembolsar = monto_aprobado − (base_aportes + fianza + prestamos_recoger + dto_papeleria). */
    public static function valorDesembolsar(float $aprobado, float $baseAportes, float $fianza, float $prestamos, float $papeleria): float
    {
        return max(0.0, $aprobado - ($baseAportes + $fianza + $prestamos + $papeleria));
    }
}