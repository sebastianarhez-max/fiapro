<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Carga los datos de demostración (tiendas, clientes, productos, compras, pagos y listados)
 * solo si la base está vacía. Se usa en el despliegue web.
 */
class DemoDataSeeder extends Seeder
{
    private const ORDEN = ['tienda', 'cliente', 'configuracion_credito', 'producto', 'users', 'compra', 'obligacion', 'pago', 'listado_pago'];

    public function run(): void
    {
        if (DB::table('tienda')->exists()) {
            $this->command?->info('La base ya tiene datos: no se cargan los datos de demostración.');
            return;
        }

        $datos = json_decode(file_get_contents(__DIR__ . '/data/demo.json'), true);

        DB::transaction(function () use ($datos) {
            foreach (self::ORDEN as $tabla) {
                foreach (array_chunk($datos[$tabla] ?? [], 100) as $filas) {
                    DB::table($tabla)->insert($filas);
                }
            }
        });

        // Las cuotas pendientes de la demo se calculan con el cronograma vigente (7 decimales)
        app(\App\Services\CuentaCorriente::class)->recalcularCuotasPendientes();

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("SELECT setval(pg_get_serial_sequence('users', 'id'), COALESCE((SELECT MAX(id) FROM users), 1))");
        }

        $this->command?->info('Datos de demostración cargados.');
    }
}
