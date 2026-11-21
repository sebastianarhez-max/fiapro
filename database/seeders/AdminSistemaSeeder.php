<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Crea 3 usuarios de prueba (uno por rol). Contraseña de desarrollo: Fiapro2026!
 * CÁMBIALA antes de cualquier demo pública.
 */
class AdminSistemaSeeder extends Seeder
{
    public function run(): void
    {
        $this->usuario('Administrador FiaPro', 'admin@fiapro.test', 'admin_sistema', null, null);

        $tienda = DB::table('tienda')->orderBy('creado_en')->first();
        if ($tienda) {
            $this->usuario('Admin ' . $tienda->nombre_razon_social, 'tienda@fiapro.test', 'admin_tienda', $tienda->tienda_id, null);
        }

        $cliente = DB::table('cliente')->orderBy('creado_en')->first();
        if ($cliente) {
            $this->usuario($cliente->nombre_completo, 'cliente@fiapro.test', 'cliente', $cliente->tienda_id, $cliente->cliente_id);
        }
    }

    private function usuario(string $nombre, string $correo, string $rol, ?string $tiendaId, ?string $clienteId): void
    {
        DB::table('users')->updateOrInsert(['email' => $correo], [
            'name' => $nombre,
            'password' => Hash::make('Fiapro2026!'),
            'email_verified_at' => now(),
            'rol' => $rol,
            'estado' => 'activo',
            'tienda_id' => $tiendaId,
            'cliente_id' => $clienteId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
