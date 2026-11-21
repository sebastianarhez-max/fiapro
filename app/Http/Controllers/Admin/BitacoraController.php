<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Consulta de la bitácora de operaciones (administrador del sistema). */
class BitacoraController extends Controller
{
    public function index(Request $request)
    {
        $registros = DB::table('bitacora as b')
            ->leftJoin('users as u', 'u.id', '=', 'b.user_id')
            ->leftJoin('tienda as t', 't.tienda_id', '=', 'b.tienda_id')
            ->when($request->filled('tienda'), fn ($q) => $q->where('b.tienda_id', $request->tienda))
            ->when($request->filled('accion'), fn ($q) => $q->where('b.accion', $request->accion))
            ->orderByDesc('b.creado_en')
            ->select('b.*', 'u.name', 'u.email', 't.nombre_razon_social')
            ->paginate(25)
            ->withQueryString();
        $tiendas = DB::table('tienda')->orderBy('nombre_razon_social')->get(['tienda_id', 'nombre_razon_social']);
        $acciones = DB::table('bitacora')->distinct()->orderBy('accion')->pluck('accion');

        return view('admin.bitacora', compact('registros', 'tiendas', 'acciones'));
    }
}
