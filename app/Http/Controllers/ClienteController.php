<?php

namespace App\Http\Controllers;

use App\Services\Bitacora;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/** Clientes de la tienda del usuario logueado: cada tienda solo ve y modifica los suyos. */
class ClienteController extends Controller
{
    public function index()
    {
        $clientes = DB::table('cliente')
            ->leftJoin('users', 'users.cliente_id', '=', 'cliente.cliente_id')
            ->leftJoin('configuracion_credito as cc', function ($j) {
                $j->on('cc.cliente_id', '=', 'cliente.cliente_id')->where('cc.activo', 1);
            })
            ->where('cliente.tienda_id', Auth::user()->tienda_id)
            ->select('cliente.*', 'users.email as correo', 'users.estado as estado_acceso',
                'cc.moneda', 'cc.tasa_comp', 'cc.tasa_mora', 'cc.limite_credito', 'cc.max_meses')
            ->orderByDesc('cliente.creado_en')
            ->get();

        return view('clientes.index', compact('clientes'));
    }

    public function formulario()
    {
        return view('clientes.nuevo');
    }

    public function guardar(Request $request)
    {
        $tiendaId = Auth::user()->tienda_id;

        $d = $request->validate([
            'nombre_completo' => 'required|string|max:120',
            'documento_identidad' => ['required', 'regex:/^[0-9A-Za-z]{8,12}$/',
                Rule::unique('cliente', 'documento_identidad')->where('tienda_id', $tiendaId)],
            'contacto' => 'required|string|max:120',

            'correo' => 'required|email|max:120|unique:users,email',
            'password' => 'required|string|min:8|max:72',

            'moneda' => 'required|in:PEN,USD',
            'tasa_comp' => 'required|numeric|gt:0|max:' . config('fiapro.tea_max_ordinaria'),
            'tasa_mora' => 'required|numeric|gte:0|max:' . config('fiapro.tea_max_mora'),
            'limite_credito' => 'required|numeric|between:' . config('fiapro.limite_min') . ',' . config('fiapro.limite_max'),
            'max_meses' => 'required|integer|between:1,' . config('fiapro.meses_max'),
        ], [
            'documento_identidad.regex' => 'El documento debe tener entre 8 y 12 letras o números (DNI o CE).',
            'limite_credito.between' => 'El límite de crédito debe estar entre ' . config('fiapro.limite_min') . ' y ' . config('fiapro.limite_max') . '.',
            'tasa_comp.max' => 'La TEA ordinaria no puede superar ' . config('fiapro.tea_max_ordinaria') . '%.',
            'tasa_mora.max' => 'La TEA moratoria no puede superar ' . config('fiapro.tea_max_mora') . '%.',
        ]);

        $clienteId = (string) Str::uuid();

        DB::transaction(function () use ($d, $tiendaId, $clienteId) {
            DB::table('cliente')->insert([
                'cliente_id' => $clienteId,
                'tienda_id' => $tiendaId,
                'nombre_completo' => $d['nombre_completo'],
                'documento_identidad' => $d['documento_identidad'],
                'contacto' => $d['contacto'],
                'creado_en' => now(),
            ]);

            DB::table('configuracion_credito')->insert([
                'config_id' => (string) Str::uuid(),
                'cliente_id' => $clienteId,
                'moneda' => $d['moneda'],
                'tipo_tasa_comp' => 'TEA',
                'tasa_comp' => $d['tasa_comp'],
                'capitalizacion' => null,
                'tipo_tasa_mora' => 'TEA',
                'tasa_mora' => $d['tasa_mora'],
                'limite_credito' => $d['limite_credito'],
                'max_meses' => $d['max_meses'],
                // Sin día de corte ni día de pago: todo vence cada 15 días desde la compra (indicación del docente)
                'fecha_corte' => null,
                'hora_corte' => null,
                'fecha_pago' => null,
                'vigente_desde' => now(),
                'activo' => 1,
            ]);

            DB::table('users')->insert([
                'name' => $d['nombre_completo'],
                'email' => $d['correo'],
                'password' => Hash::make($d['password']),
                'email_verified_at' => now(),
                'rol' => 'cliente',
                'estado' => 'activo',
                'tienda_id' => $tiendaId,
                'cliente_id' => $clienteId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        Bitacora::registrar('alta_cliente', 'cliente', $clienteId, [
            'nombre' => $d['nombre_completo'], 'correo' => $d['correo'],
            'tea' => $d['tasa_comp'], 'limite' => $d['limite_credito'],
        ]);

        return redirect('/clientes')->with('success', 'Cliente registrado con su acceso y su configuración de crédito.');
    }

    /** Baja / alta del cliente: bloquea o habilita su acceso y su línea de crédito. */
    public function cambiarEstado(string $id)
    {
        $cliente = DB::table('cliente')->where('cliente_id', $id)->where('tienda_id', Auth::user()->tienda_id)->first();
        abort_if(! $cliente, 404);

        $usuario = DB::table('users')->where('cliente_id', $id)->first();
        if (! $usuario) {
            return back()->withErrors(['estado' => 'Este cliente no tiene usuario de acceso.']);
        }

        $nuevo = $usuario->estado === 'activo' ? 'inactivo' : 'activo';

        DB::transaction(function () use ($id, $nuevo) {
            DB::table('users')->where('cliente_id', $id)->update(['estado' => $nuevo, 'updated_at' => now()]);
            DB::table('configuracion_credito')->where('cliente_id', $id)->update(['activo' => $nuevo === 'activo' ? 1 : 0]);
        });

        Bitacora::registrar($nuevo === 'activo' ? 'alta_cliente' : 'baja_cliente', 'cliente', $id, ['nombre' => $cliente->nombre_completo]);

        return back()->with('success', $nuevo === 'activo' ? 'Cliente reactivado.' : 'Cliente dado de baja.');
    }
}
