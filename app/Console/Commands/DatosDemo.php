<?php

namespace App\Console\Commands;

use App\Services\CuentaCorriente;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Carga datos de prueba para la panadería, la pollería y la peluquería:
 * productos, clientes, compras (pago único y en cuotas) y algunos pagos.
 * Se puede ejecutar varias veces: no duplica lo que ya existe.
 *
 *   php artisan fiapro:datos-demo
 */
class DatosDemo extends Command
{
    protected $signature = 'fiapro:datos-demo';

    protected $description = 'Carga productos, clientes, compras y pagos de prueba en las tiendas de ejemplo';

    public function handle(CuentaCorriente $cuenta): int
    {
        foreach ($this->datos() as $t) {
            $tiendaId = $this->tienda($t);
            $this->info("== {$t['nombre']}");

            $productos = [];
            foreach ($t['productos'] as $p) {
                $productos[$p[0]] = $this->producto($tiendaId, $p);
            }

            foreach ($t['clientes'] as $c) {
                [$clienteId, $nuevo] = $this->cliente($tiendaId, $c);
                $tieneCompras = DB::table('compra')->where('cliente_id', $clienteId)->exists();
                if ($tieneCompras) {
                    $this->line("   {$c['nombre']}: ya tiene compras, se omite");
                    continue;
                }
                foreach ($c['compras'] as $k) {
                    [$prod, $cant, $fecha, $modalidad, $meses] = $k;
                    $producto = DB::table('producto')->where('producto_id', $productos[$prod])->first();
                    $gracia = (int) ($k[6] ?? 0);   // quincenas de gracia total elegidas por el cliente
                    $compraId = $cuenta->registrarCompra($clienteId, $producto, $cant, Carbon::parse($fecha), $modalidad, $meses, $gracia);
                    $this->line("   {$c['nombre']}: {$prod} x{$cant} ({$modalidad}" . ($meses ? ", {$meses} meses" : '') . ($gracia ? ", {$gracia} de gracia" : '') . ')');
                    $this->bitacora($tiendaId, 'alta_compra', 'compra', $compraId, ['cliente' => $c['nombre'], 'producto' => $prod]);

                    // Pagos de las primeras cuotas (fecha real => [cuota => fecha])
                    foreach ($k[5] ?? [] as $nCuota => $fechaPago) {
                        $o = DB::table('obligacion')->where('compra_id', $compraId)->where('numero_cuota', $nCuota)->first();
                        $compra = DB::table('compra')->where('compra_id', $compraId)->first();
                        $e = $cuenta->exigible($o, Carbon::parse($fechaPago), (float) $compra->tea_mora_aplicada / 100);
                        $pagoId = $cuenta->registrarPago($o, $compra, Carbon::parse($fechaPago), $e['total'], 'Efectivo', 'Datos de prueba');
                        $this->line("      pago cuota {$nCuota} el {$fechaPago}: {$e['total']}");
                        $this->bitacora($tiendaId, 'pago', 'obligacion', $o->obligacion_id, ['cliente' => $c['nombre'], 'monto' => $e['total'], 'pago_id' => $pagoId]);
                    }
                }
            }
        }

        $this->newLine();
        $this->info('Datos de prueba cargados. Contraseñas: ver la lista en la conversación o en el informe.');

        return self::SUCCESS;
    }

    // ------------------------------------------------------------------ helpers

    private function tienda(array $t): string
    {
        $adminId = DB::table('users')->where('email', $t['admin_correo'])->value('tienda_id');
        if ($adminId) {
            return $adminId;
        }
        $id = (string) Str::uuid();
        DB::table('tienda')->insert([
            'tienda_id' => $id, 'nombre_razon_social' => $t['nombre'], 'giro' => $t['giro'],
            'direccion' => $t['direccion'], 'contacto' => $t['contacto'], 'estado' => 'activo', 'creado_en' => now(),
        ]);
        DB::table('users')->insert([
            'name' => $t['admin_nombre'], 'email' => $t['admin_correo'], 'password' => Hash::make($t['admin_clave']),
            'email_verified_at' => now(), 'rol' => 'admin_tienda', 'estado' => 'activo', 'tienda_id' => $id,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->bitacora(null, 'alta_tienda', 'tienda', $id, ['nombre' => $t['nombre']]);

        return $id;
    }

    private function producto(string $tiendaId, array $p): string
    {
        [$desc, $marca, $unidad, $modalidad, $contado, $lista] = $p;
        $id = DB::table('producto')->where('tienda_id', $tiendaId)->where('descripcion', $desc)->value('producto_id');
        if ($id) {
            return $id;
        }
        $id = (string) Str::uuid();
        DB::table('producto')->insert([
            'producto_id' => $id, 'tienda_id' => $tiendaId, 'proveedor' => null, 'marca' => $marca,
            'descripcion' => $desc, 'unidad_medida' => $unidad, 'imagen_url' => null,
            'precio_contado' => $contado, 'precio_lista' => $lista, 'modalidad_venta' => $modalidad, 'estado' => 'activo',
        ]);
        $this->bitacora($tiendaId, 'alta_producto', 'producto', $id, ['descripcion' => $desc]);

        return $id;
    }

    private function cliente(string $tiendaId, array $c): array
    {
        $existente = DB::table('users')->where('email', $c['correo'])->value('cliente_id');
        if ($existente) {
            return [$existente, false];
        }
        $id = (string) Str::uuid();
        DB::table('cliente')->insert([
            'cliente_id' => $id, 'tienda_id' => $tiendaId, 'nombre_completo' => $c['nombre'],
            'documento_identidad' => $c['dni'], 'contacto' => $c['telefono'], 'creado_en' => now(),
        ]);
        DB::table('configuracion_credito')->insert([
            'config_id' => (string) Str::uuid(), 'cliente_id' => $id, 'moneda' => 'PEN',
            'tipo_tasa_comp' => 'TEA', 'tasa_comp' => $c['tea'], 'capitalizacion' => null,
            'tipo_tasa_mora' => 'TEA', 'tasa_mora' => $c['mora'], 'limite_credito' => $c['limite'],
            'max_meses' => $c['meses'], 'fecha_corte' => null, 'hora_corte' => null,
            'fecha_pago' => null, 'vigente_desde' => now(), 'activo' => 1,
        ]);
        DB::table('users')->insert([
            'name' => $c['nombre'], 'email' => $c['correo'], 'password' => Hash::make($c['clave']),
            'email_verified_at' => now(), 'rol' => 'cliente', 'estado' => 'activo',
            'tienda_id' => $tiendaId, 'cliente_id' => $id, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->bitacora($tiendaId, 'alta_cliente', 'cliente', $id, ['nombre' => $c['nombre']]);

        return [$id, true];
    }

    private function bitacora(?string $tiendaId, string $accion, string $entidad, ?string $entidadId, array $detalle): void
    {
        DB::table('bitacora')->insert([
            'user_id' => null, 'tienda_id' => $tiendaId, 'accion' => $accion, 'entidad' => $entidad,
            'entidad_id' => $entidadId, 'detalle' => json_encode($detalle + ['origen' => 'datos de prueba'], JSON_UNESCAPED_UNICODE),
            'ip' => null, 'creado_en' => now(),
        ]);
    }

    // ------------------------------------------------------------------ datos

    private function datos(): array
    {
        return [
            [
                'nombre' => 'Bodega Fiapro', 'giro' => 'Bodega', 'direccion' => 'Av. Ejemplo 123',
                'contacto' => '999999999', 'admin_nombre' => 'Admin Bodega Fiapro', 'admin_correo' => 'tienda@fiapro.test', 'admin_clave' => 'Fiapro2026!',
                'productos' => [
                    ['Canasta de abarrotes', 'Surtido', 'Canasta', 'AMBOS', 240.00, 250.00],
                    ['Arroz extra 5 kg', 'Costeño', 'Bolsa', 'UNICO', 22.00, 23.50],
                    ['Aceite vegetal 1 L', 'Primor', 'Botella', 'UNICO', 9.50, 10.00],
                    ['Leche evaporada x6', 'Gloria', 'Paquete', 'UNICO', 21.00, 22.00],
                ],
                'clientes' => [
                    [
                        'nombre' => 'Pedro Castillo Huamán', 'dni' => '41236587', 'telefono' => '977 555 444',
                        'correo' => 'pedro@correo.pe', 'clave' => 'Pedro2026!', 'limite' => 300, 'tea' => 30, 'mora' => 40,
                        'meses' => 2, 'compras' => [
                            ['Arroz extra 5 kg', 2, '2026-10-02 18:00', 'UNICO', null],
                            ['Leche evaporada x6', 1, '2026-10-14 19:00', 'UNICO', null],
                        ],
                    ],
                    [
                        'nombre' => 'Elena Vargas Soto', 'dni' => '42587413', 'telefono' => '966 321 789',
                        'correo' => 'elena@correo.pe', 'clave' => 'Elena2026!', 'limite' => 350, 'tea' => 22, 'mora' => 32,
                        'meses' => 3, 'compras' => [
                            ['Canasta de abarrotes', 1, '2026-10-01 09:30', 'CUOTAS', 2],
                            ['Aceite vegetal 1 L', 3, '2026-10-18 17:00', 'UNICO', null],
                        ],
                    ],
                ],
            ],
            [
                'nombre' => 'Panadería San Martín', 'giro' => 'Panadería', 'direccion' => 'Jr. Los Pinos 245, San Martín de Porres',
                'contacto' => '987 321 654', 'admin_nombre' => 'Carlos Ramírez Soto', 'admin_correo' => 'carlos@panaderia.pe', 'admin_clave' => 'Panaderia2026!',
                'productos' => [
                    // descripción, marca, unidad, modalidad, contado, lista
                    ['Pan francés', null, 'Unidad', 'UNICO', 0.30, 0.35],
                    ['Pan de yema', null, 'Unidad', 'UNICO', 0.40, 0.45],
                    ['Torta de quinceañero (3 pisos)', 'San Martín', 'Unidad', 'AMBOS', 340.00, 360.00],
                    ['Torta de bodas personalizada', 'San Martín', 'Unidad', 'AMBOS', 400.00, 420.00],
                    ['Bocaditos para fiesta (100 und.)', 'San Martín', 'Pedido', 'AMBOS', 170.00, 180.00],
                    ['Caja de panetones x6', 'Donofrio', 'Caja', 'AMBOS', 150.00, 160.00],
                ],
                'clientes' => [
                    [
                        'nombre' => 'Rosa Quispe Mamani', 'dni' => '43215678', 'telefono' => '945 112 233',
                        'correo' => 'rosa@correo.pe', 'clave' => 'Rosa2026!', 'limite' => 400, 'tea' => 20, 'mora' => 30,
                        'meses' => 2, 'compras' => [
                            // producto, cantidad, fecha y hora, modalidad, meses, [pagos: cuota => fecha], quincenas de gracia
                            ['Torta de quinceañero (3 pisos)', 1, '2026-10-05 10:00', 'CUOTAS', 2],
                            ['Pan francés', 20, '2026-10-10 07:30', 'UNICO', null],
                            ['Pan francés', 15, '2026-10-17 07:30', 'UNICO', null],
                        ],
                    ],
                    [
                        'nombre' => 'Jorge Huamán Ríos', 'dni' => '48521736', 'telefono' => '934 556 778',
                        'correo' => 'jorge@correo.pe', 'clave' => 'Jorge2026!', 'limite' => 250, 'tea' => 18, 'mora' => 28,
                        'meses' => 2, 'compras' => [
                            ['Bocaditos para fiesta (100 und.)', 1, '2026-10-12 18:00', 'UNICO', null],
                        ],
                    ],
                    [
                        'nombre' => 'Miguel Sánchez Pérez', 'dni' => '40123658', 'telefono' => '944 785 236',
                        'correo' => 'miguel@correo.pe', 'clave' => 'Miguel2026!', 'limite' => 300, 'tea' => 18, 'mora' => 28,
                        'meses' => 2, 'compras' => [
                            ['Caja de panetones x6', 1, '2026-10-06 17:00', 'CUOTAS', 2, [], 2],
                            ['Pan de yema', 30, '2026-10-20 07:00', 'UNICO', null],
                        ],
                    ],
                    [
                        'nombre' => 'Teresa Rojas Lima', 'dni' => '43698521', 'telefono' => '955 147 258',
                        'correo' => 'teresa@correo.pe', 'clave' => 'Teresa2026!', 'limite' => 450, 'tea' => 20, 'mora' => 30,
                        'meses' => 2, 'compras' => [
                            ['Torta de bodas personalizada', 1, '2026-09-10 12:00', 'CUOTAS', 2, [1 => '2026-10-15']],
                        ],
                    ],
                ],
            ],
            [
                'nombre' => 'Pollería El Buen Sabor', 'giro' => 'Pollería', 'direccion' => 'Av. Perú 1820, San Martín de Porres',
                'contacto' => '956 741 852', 'admin_nombre' => 'Lucía Fernández Vega', 'admin_correo' => 'lucia@polleria.pe', 'admin_clave' => 'Polleria2026!',
                'productos' => [
                    ['Pollo a la brasa entero', null, 'Unidad', 'UNICO', 62.00, 65.00],
                    ['1/4 de pollo con papas', null, 'Porción', 'UNICO', 18.00, 19.00],
                    ['Combo familiar parrillero', null, 'Combo', 'AMBOS', 120.00, 128.00],
                    ['Pedido para fiesta: 5 pollos a la brasa', null, 'Pedido', 'AMBOS', 310.00, 330.00],
                ],
                'clientes' => [
                    [
                        'nombre' => 'Luis Torres Paredes', 'dni' => '46547891', 'telefono' => '923 654 987',
                        'correo' => 'luis@correo.pe', 'clave' => 'Luis2026!', 'limite' => 450, 'tea' => 22, 'mora' => 32,
                        'meses' => 2, 'compras' => [
                            ['Pedido para fiesta: 5 pollos a la brasa', 1, '2026-10-08 15:00', 'CUOTAS', 2, [], 1],
                            ['Pollo a la brasa entero', 1, '2026-10-15 19:00', 'UNICO', null],
                        ],
                    ],
                    [
                        'nombre' => 'Ana Ramos Vega', 'dni' => '45896321', 'telefono' => '912 334 556',
                        'correo' => 'ana@correo.pe', 'clave' => 'Ana2026!', 'limite' => 200, 'tea' => 25, 'mora' => 35,
                        'meses' => 1, 'compras' => [
                            ['1/4 de pollo con papas', 3, '2026-10-20 13:00', 'UNICO', null],
                        ],
                    ],
                    [
                        'nombre' => 'Ricardo Flores Díaz', 'dni' => '41785236', 'telefono' => '933 852 741',
                        'correo' => 'ricardo@correo.pe', 'clave' => 'Ricardo2026!', 'limite' => 300, 'tea' => 22, 'mora' => 32,
                        'meses' => 2, 'compras' => [
                            ['Combo familiar parrillero', 2, '2026-09-12 20:00', 'CUOTAS', 2, [1 => '2026-10-23']],
                        ],
                    ],
                    [
                        'nombre' => 'Sofía Chávez León', 'dni' => '46325874', 'telefono' => '922 963 852',
                        'correo' => 'sofia@correo.pe', 'clave' => 'Sofia2026!', 'limite' => 200, 'tea' => 25, 'mora' => 35,
                        'meses' => 1, 'compras' => [
                            ['Pollo a la brasa entero', 1, '2026-09-20 13:00', 'UNICO', null, [1 => '2026-10-05']],
                            ['1/4 de pollo con papas', 2, '2026-10-22 14:00', 'UNICO', null],
                        ],
                    ],
                ],
            ],
            [
                'nombre' => 'Peluquería Estilo & Belleza', 'giro' => 'Peluquería', 'direccion' => 'Calle Las Flores 310, Los Olivos',
                'contacto' => '912 456 789', 'admin_nombre' => 'Andrea Salazar Ruiz', 'admin_correo' => 'andrea@peluqueria.pe', 'admin_clave' => 'Peluqueria2026!',
                'productos' => [
                    ['Corte de cabello', null, 'Servicio', 'UNICO', 25.00, 27.00],
                    ['Tinte completo', 'L\'Oréal', 'Servicio', 'AMBOS', 120.00, 130.00],
                    ['Alisado keratina', 'Kerastase', 'Servicio', 'AMBOS', 220.00, 240.00],
                    ['Paquete novia: peinado + maquillaje + alisado', null, 'Servicio', 'AMBOS', 360.00, 380.00],
                ],
                'clientes' => [
                    [
                        'nombre' => 'Carmen Díaz Flores', 'dni' => '44781236', 'telefono' => '987 123 456',
                        'correo' => 'carmen@correo.pe', 'clave' => 'Carmen2026!', 'limite' => 450, 'tea' => 26, 'mora' => 38,
                        'meses' => 4, 'compras' => [
                            // Cuota 1 pagada a tiempo; la cuota 2 queda vencida (con mora a la fecha)
                            ['Paquete novia: peinado + maquillaje + alisado', 1, '2026-09-05 11:00', 'CUOTAS', 3, [1 => '2026-09-30']],
                        ],
                    ],
                    [
                        'nombre' => 'Patricia Mendoza Cruz', 'dni' => '47123659', 'telefono' => '956 789 123',
                        'correo' => 'patricia@correo.pe', 'clave' => 'Patricia2026!', 'limite' => 300, 'tea' => 28, 'mora' => 38,
                        'meses' => 3, 'compras' => [
                            ['Corte de cabello', 1, '2026-10-03 10:00', 'UNICO', null],
                            ['Alisado keratina', 1, '2026-10-12 16:00', 'CUOTAS', 2],
                        ],
                    ],
                    [
                        'nombre' => 'Gabriela Torres Ruiz', 'dni' => '45213698', 'telefono' => '911 258 369',
                        'correo' => 'gabriela@correo.pe', 'clave' => 'Gabriela2026!', 'limite' => 300, 'tea' => 26, 'mora' => 38,
                        'meses' => 3, 'compras' => [
                            ['Tinte completo', 1, '2026-09-20 15:00', 'CUOTAS', 2, [], 1],
                            ['Corte de cabello', 1, '2026-10-05 11:00', 'UNICO', null],
                        ],
                    ],
                    [
                        'nombre' => 'Milagros Huerta Salas', 'dni' => '48963214', 'telefono' => '988 369 147',
                        'correo' => 'milagros@correo.pe', 'clave' => 'Milagros2026!', 'limite' => 250, 'tea' => 24, 'mora' => 34,
                        'meses' => 2, 'compras' => [
                            ['Corte de cabello', 2, '2026-09-08 10:00', 'UNICO', null, [1 => '2026-09-15']],
                        ],
                    ],
                ],
            ],
        ];
    }
}
