<?php

namespace App\Console\Commands;

use App\Services\CuentaCorriente;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Genera el listado de pago (estado de cuenta quincenal) de cada cliente con crédito activo.
 * Se programa a diario y solo genera los días 15 y último de cada mes (una vez por quincena),
 * salvo que se indique --hoy para generarlo en la fecha actual.
 */
class GenerarListados extends Command
{
    protected $signature = 'fiapro:generar-listados {--hoy : Generar hoy aunque no sea fin de quincena}';

    protected $description = 'Genera los listados de pago quincenales de los clientes';

    public function handle(CuentaCorriente $cuenta): int
    {
        $hoy = now()->startOfDay();
        $finDeQuincena = $hoy->day === 15 || $hoy->day === $hoy->daysInMonth;
        if (! $finDeQuincena && ! $this->option('hoy')) {
            $this->info('Hoy no es fin de quincena (15 o último día del mes). No se generan listados.');

            return self::SUCCESS;
        }

        $generados = 0;
        $clientes = DB::table('cliente as c')
            ->join('configuracion_credito as cc', function ($j) {
                $j->on('cc.cliente_id', '=', 'c.cliente_id')->where('cc.activo', 1);
            })
            ->select('c.cliente_id', 'c.tienda_id', 'c.nombre_completo')
            ->get();

        foreach ($clientes as $c) {
            $existe = DB::table('listado_pago')
                ->where('cliente_id', $c->cliente_id)
                ->where('fecha_corte', $hoy->format('Y-m-d H:i:s'))
                ->exists();
            if ($existe) {
                continue;
            }
            $listado = $cuenta->listado($c->cliente_id, $hoy);
            $cuenta->guardarListado($c->cliente_id, $listado);
            DB::table('bitacora')->insert([
                'user_id' => null,
                'tienda_id' => $c->tienda_id,
                'accion' => 'listado_pago',
                'entidad' => 'cliente',
                'entidad_id' => $c->cliente_id,
                'detalle' => json_encode(['fecha' => $hoy->toDateString(), 'total' => $listado['totales']['total'], 'origen' => 'automatico'], JSON_UNESCAPED_UNICODE),
                'ip' => null,
                'creado_en' => now(),
            ]);
            $generados++;
            $this->line("Listado generado: {$c->nombre_completo} ({$hoy->format('d/m/Y')})");
        }

        $this->info("Listados generados: {$generados}");

        return self::SUCCESS;
    }
}
