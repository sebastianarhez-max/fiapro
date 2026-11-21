<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Bitacora;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/** Pantalla del administrador del sistema: alta/baja de tiendas y su usuario administrador. */
class TiendaController extends Controller
{
    public function index()
    {
        $tiendas = DB::table('tienda')
            ->leftJoin('users', function ($j) {
                $j->on('users.tienda_id', '=', 'tienda.tienda_id')->where('users.rol', 'admin_tienda');
            })
            ->select('tienda.*', 'users.email as admin_correo')
            ->orderByDesc('tienda.creado_en')
            ->get();

        return view('admin.tiendas', compact('tiendas'));
    }

    public function store(Request $request)
    {
        $d = $request->validate([
            'nombre_razon_social' => 'required|string|max:120',
            'giro' => 'required|string|max:80',
            'direccion' => 'required|string|max:200',
            'contacto' => 'required|string|max:120',
            'admin_nombre' => 'required|string|max:120',
            'admin_correo' => 'required|email|max:120|unique:users,email',
            'admin_password' => 'required|string|min:8|max:72',
        ]);

        $tiendaId = (string) Str::uuid();

        DB::transaction(function () use ($d, $tiendaId) {
            DB::table('tienda')->insert([
                'tienda_id' => $tiendaId,
                'nombre_razon_social' => $d['nombre_razon_social'],
                'giro' => $d['giro'],
                'direccion' => $d['direccion'],
                'contacto' => $d['contacto'],
                'estado' => 'activo',
                'creado_en' => now(),
            ]);

            DB::table('users')->insert([
                'name' => $d['admin_nombre'],
                'email' => $d['admin_correo'],
                'password' => Hash::make($d['admin_password']),
                'email_verified_at' => now(),
                'rol' => 'admin_tienda',
                'estado' => 'activo',
                'tienda_id' => $tiendaId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        Bitacora::registrar('alta_tienda', 'tienda', $tiendaId, ['nombre' => $d['nombre_razon_social'], 'admin' => $d['admin_correo']]);

        return redirect('/admin/tiendas')->with('success', 'Tienda y administrador creados correctamente.');
    }

    public function cambiarEstado(string $id)
    {
        $tienda = DB::table('tienda')->where('tienda_id', $id)->first();
        abort_if(! $tienda, 404);

        $nuevo = $tienda->estado === 'activo' ? 'inactivo' : 'activo';
        DB::table('tienda')->where('tienda_id', $id)->update(['estado' => $nuevo]);

        Bitacora::registrar($nuevo === 'activo' ? 'alta_tienda' : 'baja_tienda', 'tienda', $id, ['nombre' => $tienda->nombre_razon_social]);

        return back()->with('success', $nuevo === 'activo' ? 'Tienda reactivada.' : 'Tienda dada de baja. Sus usuarios ya no pueden ingresar.');
    }
}
