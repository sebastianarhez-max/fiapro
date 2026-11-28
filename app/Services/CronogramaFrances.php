<?php

namespace App\Services;

use Carbon\CarbonInterface;
use InvalidArgumentException;

/**
 * Genera el cronograma de pagos: método francés vencido con cuotas quincenales,
 * base comercial 30/360 (cada período dura 15 días y todos usan la misma TEQ).
 *
 * - Los períodos se cuentan desde la fecha de compra: el período k vence en compra + 15·k días.
 *   (No hay día de corte ni día de pago: todo es quincenal, por indicación del docente.)
 * - Los períodos se numeran desde 1 (no hay período 0).
 * - El cliente puede pedir de 0 a 3 quincenas de gracia total (T) dentro del plazo:
 *   no paga y el interés se suma al saldo. Luego vienen N − g cuotas (S), N = meses × 2.
 * - Todos los cálculos se hacen con 7 decimales (indicación del docente) y recién al mostrarlos
 *   se redondean a 2 decimales. Así todas las cuotas son iguales y la última deja el saldo en 0.
 */
class CronogramaFrances
{
    public const PRECISION_MONETARIA = 2;
    public const PRECISION_SIMULADOR = 7;

    public const BASE_ANUAL = 360;
    public const DIAS_QUINCENA = 15;
    public const GRACIA_MAX = 3;

    /**
     * @param float           $capital Monto financiado (precio a crédito)
     * @param float           $tea     TEA en decimal (24% => 0.24)
     * @param int             $meses   Plazo en meses (períodos = meses * 2)
     * @param CarbonInterface $compra  Fecha de la compra (inicio del período 1)
     * @param int             $gracia  Quincenas de gracia total elegidas por el cliente (0 a 3)
     * @param int             $precision Decimales del cálculo (7 por defecto; los montos se muestran redondeados a 2).
     */
    public function generar(float $capital, float $tea, int $meses, CarbonInterface $compra, int $gracia = 0, int $precision = self::PRECISION_SIMULADOR): array
    {
        if ($precision < self::PRECISION_MONETARIA || $precision > self::PRECISION_SIMULADOR) {
            throw new InvalidArgumentException('La precisión debe estar entre 2 y 7 decimales.');
        }
        if ($capital <= 0) {
            throw new InvalidArgumentException('El capital debe ser mayor a 0.');
        }
        if ($tea < 0) {
            throw new InvalidArgumentException('La TEA no puede ser negativa.');
        }
        $max = 4;
        try {
            $max = (int) config('fiapro.meses_max', 4);
        } catch (\Throwable) {
            // fuera de Laravel (pruebas unitarias) se usa el máximo por defecto
        }
        if ($meses < 1 || $meses > $max) {
            throw new InvalidArgumentException("El plazo debe estar entre 1 y {$max} meses.");
        }
        $total = $meses * 2;                                  // N: períodos quincenales del plazo
        if ($gracia < 0 || $gracia > self::GRACIA_MAX) {
            throw new InvalidArgumentException('Los períodos de gracia deben estar entre 0 y ' . self::GRACIA_MAX . '.');
        }
        if ($gracia >= $total) {
            throw new InvalidArgumentException("Con {$meses} " . ($meses === 1 ? 'mes' : 'meses') . " ({$total} quincenas) la gracia puede ser como máximo de " . ($total - 1) . ' ' . ($total - 1 === 1 ? 'quincena' : 'quincenas') . '. Debe quedar al menos una cuota.');
        }

        $teq = self::teq($tea);                               // TEQ sin redondear (se muestra con 7 decimales en %)
        $n = $total - $gracia;                                // cuotas con pago
        $filas = [];
        $saldo = round($capital, $precision);
        $interesGracia = 0.0;

        // 1) Quincenas de gracia total: no paga, el interés se suma al saldo
        for ($k = 1; $k <= $gracia; $k++) {
            $interes = round($saldo * $teq, $precision);
            $final = round($saldo + $interes, $precision);
            $filas[] = [
                'n' => $k,
                'fecha' => $this->fechaPeriodo($compra, $k),
                'gracia' => 'T',
                'saldo_inicial' => $saldo,
                'interes' => $interes,
                'cuota' => 0.0,
                'amortizacion' => 0.0,
                'saldo_final' => $final,
            ];
            $interesGracia = round($interesGracia + $interes, $precision);
            $saldo = $final;
        }
        $saldoCuotas = $saldo;                                // saldo con el que se calcula la cuota

        // 2) Cuota fija (método francés) sobre el saldo después de la gracia y las cuotas que faltan
        if ($teq == 0.0) {
            $cuota = round($saldoCuotas / $n, $precision);
        } elseif ($precision > self::PRECISION_MONETARIA) {
            // Forma equivalente de la fórmula francesa, estable cerca de tasa cero.
            $cuota = round($saldoCuotas * $teq / -expm1(-$n * log1p($teq)), $precision);
        } else {
            $f = pow(1 + $teq, $n);
            $cuota = round($saldoCuotas * $teq * $f / ($f - 1), $precision);
        }
        if ($teq > 0.0) {
            // Con tasas muy bajas, el redondeo de la cuota podría dejar negativo el interés
            // de la última cuota (que absorbe la diferencia). En ese caso se sube la cuota
            // la mínima unidad (un céntimo) hasta que el último interés sea mayor o igual a cero.
            $unidad = 10 ** -$precision;
            while ($cuota - $this->saldoAntesDeUltimaCuota($saldoCuotas, $teq, $cuota, $n, $precision) < 0) {
                $cuota = round($cuota + $unidad, $precision);
            }
        }

        // 3) Quincenas con cuota
        $totalIntereses = $interesGracia;
        $totalCuotas = 0.0;
        for ($k = $gracia + 1; $k <= $total; $k++) {
            if ($k < $total) {
                $interes = round($saldo * $teq, $precision);
                $amort = round($cuota - $interes, $precision);
            } elseif ($teq > 0.0) {
                // Última cuota: se amortiza todo el saldo pendiente y la cuota es la misma que las
                // anteriores (con 7 decimales la diferencia de redondeo es de una diezmillonésima).
                $amort = $saldo;
                $interes = round($cuota - $saldo, $precision);
            } else {
                // Tasa cero (solo en el simulador): sin interés, la última cuota salda el saldo.
                $amort = $saldo;
                $interes = 0.0;
            }
            $cuotaK = round($amort + $interes, $precision);
            $final = round($saldo - $amort, $precision);

            $filas[] = [
                'n' => $k,
                'fecha' => $this->fechaPeriodo($compra, $k),
                'gracia' => 'S',
                'saldo_inicial' => $saldo,
                'interes' => $interes,
                'cuota' => $cuotaK,
                'amortizacion' => $amort,
                'saldo_final' => $final,
            ];
            $totalIntereses = round($totalIntereses + $interes, $precision);
            $totalCuotas = round($totalCuotas + $cuotaK, $precision);
            $saldo = $final;
        }

        return [
            'resumen' => [
                'capital' => round($capital, $precision),
                'tea' => $tea,
                'teq' => $teq,
                'n_periodos' => $total,
                'periodos_gracia' => $gracia,
                'n_cuotas' => $n,
                'interes_gracia' => round($interesGracia, $precision),
                'capital_financiado' => $saldoCuotas,
                'cuota' => $cuota,
                'fecha_primer_pago' => $this->fechaPeriodo($compra, $gracia + 1),
                'total_intereses' => round($totalIntereses, $precision),
                'total_a_pagar' => round($totalCuotas, $precision),
            ],
            'filas' => $filas,
        ];
    }

    /** Saldo que queda antes de la última cuota si se paga la cuota indicada en las anteriores. */
    private function saldoAntesDeUltimaCuota(float $saldo, float $teq, float $cuota, int $n, int $precision): float
    {
        for ($k = 1; $k < $n; $k++) {
            $interes = round($saldo * $teq, $precision);
            $saldo = round($saldo - round($cuota - $interes, $precision), $precision);
        }

        return $saldo;
    }

    /** TEQ = (1 + TEA)^(15/360) − 1 */
    public static function teq(float $tea, ?int $precision = null): float
    {
        if ($precision !== null) {
            return round(expm1(log1p($tea) * self::DIAS_QUINCENA / self::BASE_ANUAL), $precision);
        }

        return pow(1 + $tea, self::DIAS_QUINCENA / self::BASE_ANUAL) - 1;
    }

    /** Días comerciales 30/360: d = 360(A2-A1) + 30(M2-M1) + (D2-D1). El último día del mes cuenta como 30. */
    public function dias360(CarbonInterface $a, CarbonInterface $b): int
    {
        $d1 = $a->day === $a->daysInMonth ? 30 : $a->day;
        $d2 = $b->day === $b->daysInMonth ? 30 : $b->day;

        return 360 * ($b->year - $a->year) + 30 * ($b->month - $a->month) + ($d2 - $d1);
    }

    /** Vencimiento del período k: fecha de compra + 15·k días comerciales (30/360). */
    public function fechaPeriodo(CarbonInterface $compra, int $k): CarbonInterface
    {
        $dia = $compra->day === $compra->daysInMonth ? 30 : min($compra->day, 30);
        $total = $dia + self::DIAS_QUINCENA * $k;
        $mesesExtra = intdiv($total - 1, 30);
        $d = ($total - 1) % 30 + 1;

        $base = $compra->copy()->startOfMonth()->addMonthsNoOverflow($mesesExtra);

        return $this->fechaEnMes($base, $d)->startOfDay();
    }

    /** Fecha con el día pedido dentro del mes de $base (si no existe, usa el último día). */
    private function fechaEnMes(CarbonInterface $base, int $dia): CarbonInterface
    {
        $f = $base->copy()->startOfMonth();

        return $f->day(min($dia, $f->daysInMonth));
    }
}
