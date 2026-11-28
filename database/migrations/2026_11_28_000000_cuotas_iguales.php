<?php

use App\Services\CronogramaFrances;
use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Por indicación del docente, todas las cuotas de una compra deben ser iguales.
 * Antes la última cuota absorbía el redondeo (por ejemplo, 93.26 en vez de 93.28);
 * ahora la cuota es la misma y la diferencia la absorbe el interés de la última cuota.
 *
 * Esta migración recalcula las cuotas PENDIENTES de las compras ya registradas
 * (las cuotas pagadas no se tocan). Solo cambia la última cuota de cada compra.
 */
return new class extends Migration
{
    public function up(): void
    {
        $servicio = app(CronogramaFrances::class);

        $compras = DB::table('compra')->where('modalidad_pago', 'CUOTAS')->get();

        foreach ($compras as $compra) {
            $r = $servicio->generar(
                (float) $compra->monto,
                (float) $compra->tea_aplicada / 100,
                (int) $compra->numero_meses,
                Carbon::parse($compra->fecha_compra),
                (int) ($compra->periodos_gracia ?? 0)
            );

            $filas = [];
            foreach ($r['filas'] as $f) {
                $filas[$f['n']] = $f;
            }

            $obligaciones = DB::table('obligacion')
                ->where('compra_id', $compra->compra_id)
                ->where('estado', 'pendiente')
                ->get();

            foreach ($obligaciones as $o) {
                $f = $filas[(int) $o->numero_cuota] ?? null;
                if (! $f || abs((float) $o->monto_total - $f['cuota']) < 0.001
                    && abs((float) $o->interes_compensatorio - $f['interes']) < 0.001) {
                    continue;
                }

                DB::table('obligacion')->where('obligacion_id', $o->obligacion_id)->update([
                    'capital' => $f['amortizacion'],
                    'interes_compensatorio' => $f['interes'],
                    'monto_total' => $f['cuota'],
                    'saldo' => $f['cuota'],
                ]);
            }

            if (abs((float) $compra->cuota - $r['resumen']['cuota']) >= 0.001) {
                DB::table('compra')->where('compra_id', $compra->compra_id)->update(['cuota' => $r['resumen']['cuota']]);
            }
        }
    }

    public function down(): void
    {
        // Sin reversión: el cálculo anterior dejaba la última cuota distinta.
    }
};
