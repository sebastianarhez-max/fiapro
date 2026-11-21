<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Borra las compras, cuotas, pagos y listados (se conservan tiendas, usuarios, clientes y productos)
 * y vuelve a cargar las compras de prueba con el cálculo vigente del cronograma.
 *
 *   php artisan fiapro:reiniciar-compras
 */
class ReiniciarCompras extends Command
{
    protected $signature = 'fiapro:reiniciar-compras {--force : No pedir confirmación}';

    protected $description = 'Borra compras, cuotas, pagos y listados y recarga las compras de prueba';

    public function handle(): int
    {
        if (! $this->option('force') && ! $this->confirm('Se borrarán TODAS las compras, cuotas, pagos y listados. ¿Continuar?')) {
            return self::FAILURE;
        }

        DB::transaction(function () {
            DB::table('pago')->delete();
            DB::table('listado_pago')->delete();
            DB::table('obligacion')->delete();
            DB::table('compra')->delete();
        });
        $this->info('Compras, cuotas, pagos y listados borrados.');

        return $this->call('fiapro:datos-demo');
    }
}
