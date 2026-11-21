<?php

namespace App\Http\Controllers;

use App\Services\Bitacora;
use App\Services\CuentaCorriente;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/** Listado de pago (estado de cuenta quincenal) de cada cliente. */
class ListadoController extends Controller
{
    public function __construct(private CuentaCorriente $cuenta)
    {
    }

    public function index()
    {
        $tiendaId = Auth::user()->tienda_id;
        $clientes = DB::table('cliente as c')
            ->join('configuracion_credito as cc', function ($j) {
                $j->on('cc.cliente_id', '=', 'c.cliente_id')->where('cc.activo', 1);
            })
            ->where('c.tienda_id', $tiendaId)
            ->orderBy('c.nombre_completo')
            ->select('c.cliente_id', 'c.nombre_completo', 'cc.moneda', 'cc.max_meses')
            ->get();
        $generados = DB::table('listado_pago as l')
            ->join('cliente as c', 'c.cliente_id', '=', 'l.cliente_id')
            ->where('c.tienda_id', $tiendaId)
            ->orderByDesc('l.fecha_corte')
            ->select('l.*', 'c.nombre_completo')
            ->limit(30)
            ->get();

        return view('listados.index', compact('clientes', 'generados'));
    }

    public function ver(Request $request, string $clienteId)
    {
        $cliente = DB::table('cliente')->where('cliente_id', $clienteId)->where('tienda_id', Auth::user()->tienda_id)->first();
        abort_if(! $cliente, 404);
        if (! $this->cuenta->configuracion($clienteId)) {
            return redirect('/listados')->withErrors(['listado' => "{$cliente->nombre_completo} no tiene condiciones de crédito activas (está dado de baja o se registró sin configuración)."]);
        }

        return view('listados.ver', $this->datos($request, $cliente) + ['esTienda' => true]);
    }

    public function guardar(Request $request, string $clienteId)
    {
        $cliente = DB::table('cliente')->where('cliente_id', $clienteId)->where('tienda_id', Auth::user()->tienda_id)->first();
        abort_if(! $cliente, 404);
        $datos = $this->datos($request, $cliente);
        $this->cuenta->guardarListado($clienteId, $datos['listado']);
        Bitacora::registrar('listado_pago', 'cliente', $clienteId, [
            'fecha' => $datos['listado']['fecha']->toDateString(),
            'total' => $datos['listado']['totales']['total'],
        ]);

        return back()->with('success', 'Listado de pago registrado.');
    }

    /** Datos del listado; también lo usa la pantalla del cliente. */
    public function datos(Request $request, object $cliente): array
    {
        $cfg = $this->cuenta->configuracion($cliente->cliente_id);
        abort_if(! $cfg, 404, 'El cliente no tiene configuración de crédito.');
        $fecha = $request->filled('fecha') ? Carbon::parse($request->fecha) : now();
        $listado = $this->cuenta->listado($cliente->cliente_id, $fecha);

        return [
            'cliente' => $cliente,
            'cfg' => $cfg,
            'listado' => $listado,
            'fecha' => $listado['fecha']->toDateString(),
            'linea' => $this->cuenta->linea($cliente->cliente_id),
        ];
    }
}
