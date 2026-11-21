<?php

namespace App\Http\Controllers;

use App\Services\CuentaCorriente;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PanelController extends Controller
{
    public function __construct(private CuentaCorriente $cuenta)
    {
    }

    /** Resumen del administrador de la tienda con datos reales. */
    public function tienda()
    {
        $tiendaId = Auth::user()->tienda_id;
        $hoy = now()->startOfDay();

        $base = fn () => DB::table('obligacion as o')
            ->join('compra as c', 'c.compra_id', '=', 'o.compra_id')
            ->join('cliente as cl', 'cl.cliente_id', '=', 'c.cliente_id')
            ->where('cl.tienda_id', $tiendaId)
            ->where('o.estado', 'pendiente');

        $stats = [
            'clientes' => DB::table('cliente as c')->join('users as u', 'u.cliente_id', '=', 'c.cliente_id')
                ->where('c.tienda_id', $tiendaId)->where('u.estado', 'activo')->count(),
            'productos' => DB::table('producto')->where('tienda_id', $tiendaId)->where('estado', 'activo')->count(),
            'creditos' => DB::table('compra as c')->join('cliente as cl', 'cl.cliente_id', '=', 'c.cliente_id')
                ->where('cl.tienda_id', $tiendaId)->where('c.estado', 'activa')->count(),
            'por_cobrar' => (float) $base()->sum('o.saldo'),
            'vencidas' => $base()->where('o.fecha_vencimiento', '<', $hoy->toDateString())->count(),
            'monto_vencido' => (float) $base()->where('o.fecha_vencimiento', '<', $hoy->toDateString())->sum('o.saldo'),
        ];

        $proximas = $base()
            ->join('producto as p', 'p.producto_id', '=', 'c.producto_id')
            ->where('o.fecha_vencimiento', '<=', $hoy->copy()->addDays(15)->toDateString())
            ->orderBy('o.fecha_vencimiento')
            ->select('o.*', 'c.moneda', 'c.modalidad_pago', 'c.numero_meses', 'cl.nombre_completo', 'p.descripcion', 'p.imagen_url')
            ->limit(8)
            ->get();

        $actividad = DB::table('bitacora as b')
            ->leftJoin('users as u', 'u.id', '=', 'b.user_id')
            ->where('b.tienda_id', $tiendaId)
            ->orderByDesc('b.creado_en')
            ->select('b.*', 'u.name')
            ->limit(8)
            ->get();

        return view('dashboard', compact('stats', 'proximas', 'actividad', 'hoy'));
    }

    /** Pantalla del cliente: su línea de crédito, compras, cuotas y pagos (solo lo suyo). */
    public function cliente()
    {
        $user = Auth::user();
        $cliente = DB::table('cliente')->where('cliente_id', $user->cliente_id)->first();
        $tienda = DB::table('tienda')->where('tienda_id', $user->tienda_id)->first();
        $cfg = $cliente ? $this->cuenta->configuracion($cliente->cliente_id) : null;
        $linea = $cliente ? $this->cuenta->linea($cliente->cliente_id) : null;

        $compras = collect();
        $pendientes = collect();
        $pagos = collect();
        if ($cliente) {
            $compras = DB::table('compra as c')
                ->join('producto as p', 'p.producto_id', '=', 'c.producto_id')
                ->where('c.cliente_id', $cliente->cliente_id)
                ->orderByDesc('c.fecha_compra')
                ->select('c.*', 'p.descripcion', 'p.imagen_url')
                ->get();
            $pendientes = DB::table('obligacion as o')
                ->join('compra as c', 'c.compra_id', '=', 'o.compra_id')
                ->join('producto as p', 'p.producto_id', '=', 'c.producto_id')
                ->where('c.cliente_id', $cliente->cliente_id)
                ->where('o.estado', 'pendiente')
                ->orderBy('o.fecha_vencimiento')
                ->select('o.*', 'c.moneda', 'c.modalidad_pago', 'c.numero_meses', 'c.tea_mora_aplicada', 'p.descripcion', 'p.imagen_url')
                ->get()
                ->map(function ($o) {
                    $e = $this->cuenta->exigible($o, now(), (float) $o->tea_mora_aplicada / 100);
                    $o->mora_hoy = $e['mora'];
                    $o->dias_mora_hoy = $e['dias_mora'];
                    $o->total_hoy = $e['total'];

                    return $o;
                });
            $pagos = DB::table('pago as pg')
                ->join('obligacion as o', 'o.obligacion_id', '=', 'pg.obligacion_id')
                ->join('compra as c', 'c.compra_id', '=', 'o.compra_id')
                ->join('producto as p', 'p.producto_id', '=', 'c.producto_id')
                ->where('c.cliente_id', $cliente->cliente_id)
                ->orderByDesc('pg.fecha_real_pago')
                ->select('pg.*', 'o.numero_cuota', 'c.modalidad_pago', 'p.descripcion', 'p.imagen_url')
                ->get();
        }

        return view('cliente_inicio', compact('cliente', 'tienda', 'cfg', 'linea', 'compras', 'pendientes', 'pagos'));
    }

    /** Detalle de una compra del cliente logueado. */
    public function clienteCompra(string $id)
    {
        $compra = DB::table('compra')->where('compra_id', $id)->where('cliente_id', Auth::user()->cliente_id)->first();
        abort_if(! $compra, 404);

        return view('compras.ver', app(CompraController::class)->datosCompra($compra) + ['soloLectura' => true]);
    }

    /** Listado de pago del cliente logueado. */
    public function clienteListado(Request $request)
    {
        $cliente = DB::table('cliente')->where('cliente_id', Auth::user()->cliente_id)->first();
        if (! $cliente) {
            return redirect('/mi-cuenta')->withErrors(['listado' => 'Tu usuario todavía no está vinculado a un cliente de la tienda.']);
        }
        if (! $this->cuenta->configuracion($cliente->cliente_id)) {
            return redirect('/mi-cuenta')->withErrors(['listado' => 'Aún no tienes condiciones de crédito registradas. Pide a la tienda que las configure.']);
        }

        return view('listados.ver', app(ListadoController::class)->datos($request, $cliente) + ['esTienda' => false]);
    }

    /** Catálogo de la tienda visto por el cliente: productos activos con imagen y precios. */
    public function clienteCatalogo()
    {
        $user = Auth::user();
        $tienda = DB::table('tienda')->where('tienda_id', $user->tienda_id)->first();
        $productos = DB::table('producto')->where('tienda_id', $user->tienda_id)->where('estado', 'activo')->orderBy('descripcion')->get();
        $cfg = $user->cliente_id ? $this->cuenta->configuracion($user->cliente_id) : null;
        $linea = $user->cliente_id ? $this->cuenta->linea($user->cliente_id) : null;

        return view('catalogo', compact('tienda', 'productos', 'cfg', 'linea'));
    }
}
