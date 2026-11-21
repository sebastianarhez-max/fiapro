<?php

namespace App\Http\Controllers;

use App\Services\Bitacora;
use App\Services\CuentaCorriente;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/** Cuotas por cobrar y registro de pagos (con mora) de la tienda del usuario logueado. */
class PagoController extends Controller
{
    public function __construct(private CuentaCorriente $cuenta)
    {
    }

    public function index(Request $request)
    {
        $tiendaId = Auth::user()->tienda_id;
        $hoy = now()->startOfDay();

        $pendientes = DB::table('obligacion as o')
            ->join('compra as c', 'c.compra_id', '=', 'o.compra_id')
            ->join('cliente as cl', 'cl.cliente_id', '=', 'c.cliente_id')
            ->join('producto as p', 'p.producto_id', '=', 'c.producto_id')
            ->where('cl.tienda_id', $tiendaId)
            ->where('o.estado', 'pendiente')
            ->when($request->filled('cliente'), fn ($q) => $q->where('c.cliente_id', $request->cliente))
            ->when($request->vista === 'vencidas', fn ($q) => $q->where('o.fecha_vencimiento', '<', $hoy->toDateString()))
            ->orderBy('o.fecha_vencimiento')
            ->orderBy('c.fecha_compra')
            ->select('o.*', 'c.moneda', 'c.modalidad_pago', 'c.numero_meses', 'cl.nombre_completo', 'p.descripcion', 'p.imagen_url')
            ->limit(200)
            ->get();

        $pagos = DB::table('pago as pg')
            ->join('obligacion as o', 'o.obligacion_id', '=', 'pg.obligacion_id')
            ->join('compra as c', 'c.compra_id', '=', 'o.compra_id')
            ->join('cliente as cl', 'cl.cliente_id', '=', 'c.cliente_id')
            ->where('cl.tienda_id', $tiendaId)
            ->when($request->filled('cliente'), fn ($q) => $q->where('c.cliente_id', $request->cliente))
            ->orderByDesc('pg.creado_en')
            ->select('pg.*', 'o.numero_cuota', 'c.modalidad_pago', 'cl.nombre_completo')
            ->limit(30)
            ->get();

        $clientes = DB::table('cliente')->where('tienda_id', $tiendaId)->orderBy('nombre_completo')->get(['cliente_id', 'nombre_completo']);

        return view('pagos.index', compact('pendientes', 'pagos', 'clientes', 'hoy'));
    }

    public function formulario(Request $request, string $obligacionId)
    {
        [$o, $compra, $cliente] = $this->obligacionDeMiTienda($obligacionId);
        if ($o->estado !== 'pendiente') {
            return redirect('/pagos')->withErrors(['pago' => 'Esa cuota ya fue pagada.']);
        }
        $fecha = $request->filled('fecha') ? Carbon::parse($request->fecha) : now();
        $exigible = $this->cuenta->exigible($o, $fecha, (float) $compra->tea_mora_aplicada / 100);
        $producto = DB::table('producto')->where('producto_id', $compra->producto_id)->value('descripcion');

        return view('pagos.nuevo', compact('o', 'compra', 'cliente', 'fecha', 'exigible', 'producto'));
    }

    public function guardar(Request $request, string $obligacionId)
    {
        [$o, $compra, $cliente] = $this->obligacionDeMiTienda($obligacionId);
        $d = $request->validate([
            'fecha_real_pago' => 'required|date|before_or_equal:' . now()->addDay()->toDateString(),
            'monto_pagado' => 'required|numeric|gt:0',
            'moneda' => 'required|in:PEN,USD',
            'medio_pago' => 'required|in:Efectivo,Yape / Plin,Transferencia,Tarjeta,Otro',
            'referencia' => 'nullable|string|max:200',
        ], [
            'fecha_real_pago.before_or_equal' => 'La fecha de pago no puede ser futura.',
        ]);
        if ($d['moneda'] !== ($compra->moneda ?? 'PEN')) {
            return back()->withInput()->withErrors(['moneda' => 'La moneda del pago debe ser la misma del crédito. No hay conversión automática.']);
        }

        try {
            $pagoId = $this->cuenta->registrarPago($o, $compra, Carbon::parse($d['fecha_real_pago']), (float) $d['monto_pagado'], $d['medio_pago'], $d['referencia'] ?? null);
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->withErrors(['monto_pagado' => $e->getMessage()]);
        }

        Bitacora::registrar('pago', 'obligacion', $o->obligacion_id, [
            'cliente' => $cliente->nombre_completo,
            'cuota' => $o->numero_cuota,
            'monto' => $d['monto_pagado'],
            'pago_id' => $pagoId,
        ]);

        return redirect('/compras/' . $compra->compra_id)->with('success', 'Pago registrado correctamente.');
    }

    private function obligacionDeMiTienda(string $id): array
    {
        $o = DB::table('obligacion')->where('obligacion_id', $id)->first();
        abort_if(! $o, 404);
        $compra = DB::table('compra')->where('compra_id', $o->compra_id)->first();
        $cliente = DB::table('cliente')->where('cliente_id', $compra->cliente_id)->where('tienda_id', Auth::user()->tienda_id)->first();
        abort_if(! $cliente, 404);

        return [$o, $compra, $cliente];
    }
}
