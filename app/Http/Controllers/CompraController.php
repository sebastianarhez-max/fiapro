<?php

namespace App\Http\Controllers;

use App\Services\Bitacora;
use App\Services\CronogramaFrances;
use App\Services\CuentaCorriente;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/** Compras a crédito de la tienda del usuario logueado. */
class CompraController extends Controller
{
    public function __construct(private CuentaCorriente $cuenta)
    {
    }

    public function index(Request $request)
    {
        $tiendaId = Auth::user()->tienda_id;
        $compras = DB::table('compra as c')
            ->join('cliente as cl', 'cl.cliente_id', '=', 'c.cliente_id')
            ->join('producto as p', 'p.producto_id', '=', 'c.producto_id')
            ->where('cl.tienda_id', $tiendaId)
            ->when($request->filled('cliente'), fn ($q) => $q->where('c.cliente_id', $request->cliente))
            ->when($request->filled('estado'), fn ($q) => $q->where('c.estado', $request->estado))
            ->orderByDesc('c.fecha_compra')
            ->select('c.*', 'cl.nombre_completo', 'p.descripcion', 'p.imagen_url')
            ->paginate(15)
            ->withQueryString();
        $clientes = DB::table('cliente')->where('tienda_id', $tiendaId)->orderBy('nombre_completo')->get(['cliente_id', 'nombre_completo']);

        return view('compras.index', compact('compras', 'clientes'));
    }

    public function formulario()
    {
        $tiendaId = Auth::user()->tienda_id;
        $clientes = DB::table('cliente as c')
            ->join('configuracion_credito as cc', function ($j) {
                $j->on('cc.cliente_id', '=', 'c.cliente_id')->where('cc.activo', 1);
            })
            ->join('users as u', 'u.cliente_id', '=', 'c.cliente_id')
            ->where('c.tienda_id', $tiendaId)
            ->where('u.estado', 'activo')
            ->orderBy('c.nombre_completo')
            ->select('c.cliente_id', 'c.nombre_completo', 'c.documento_identidad', 'cc.moneda', 'cc.max_meses', 'cc.tasa_comp',  'cc.limite_credito')
            ->get()
            ->map(function ($c) {
                $l = $this->cuenta->linea($c->cliente_id);
                $c->utilizado = $l['utilizado'];
                $c->disponible = $l['disponible'];

                return $c;
            });
        $productos = DB::table('producto')->where('tienda_id', $tiendaId)->where('estado', 'activo')->orderBy('descripcion')->get();

        return view('compras.nueva', compact('clientes', 'productos'));
    }

    public function guardar(Request $request)
    {
        $tiendaId = Auth::user()->tienda_id;
        $d = $request->validate([
            'cliente_id' => 'required|string',
            'producto_id' => 'required|string',
            'cantidad' => 'required|numeric|gt:0|max:9999',
            'fecha_compra' => 'required|date|before_or_equal:' . now()->addDay()->toDateString(),
            'modalidad_pago' => 'required|in:UNICO,CUOTAS',
            'numero_meses' => 'nullable|required_if:modalidad_pago,CUOTAS|integer|between:1,' . config('fiapro.meses_max'),
            'periodos_gracia' => 'nullable|integer|between:0,' . CronogramaFrances::GRACIA_MAX,
        ], [
            'numero_meses.required_if' => 'Indica en cuántos meses se financia la compra.',
            'fecha_compra.before_or_equal' => 'La fecha de compra no puede ser futura.',
        ]);

        $cliente = DB::table('cliente')->where('cliente_id', $d['cliente_id'])->where('tienda_id', $tiendaId)->first();
        $producto = DB::table('producto')->where('producto_id', $d['producto_id'])->where('tienda_id', $tiendaId)->where('estado', 'activo')->first();
        abort_if(! $cliente || ! $producto, 404);

        $activo = DB::table('users')->where('cliente_id', $cliente->cliente_id)->value('estado') === 'activo';
        if (! $activo) {
            return back()->withInput()->withErrors(['cliente_id' => 'El cliente está dado de baja y no puede comprar a crédito.']);
        }
        if ($producto->modalidad_venta !== 'AMBOS' && $producto->modalidad_venta !== $d['modalidad_pago']) {
            return back()->withInput()->withErrors(['modalidad_pago' => 'Este producto solo se vende ' . ($producto->modalidad_venta === 'UNICO' ? 'en pago único.' : 'en cuotas.')]);
        }
        $cfg = $this->cuenta->configuracion($cliente->cliente_id);
        if ($d['modalidad_pago'] === 'CUOTAS' && $cfg && (int) $d['numero_meses'] > (int) $cfg->max_meses) {
            return back()->withInput()->withErrors(['numero_meses' => "A este cliente se le puede financiar como máximo {$cfg->max_meses} meses."]);
        }

        $gracia = $d['modalidad_pago'] === 'CUOTAS' ? (int) ($d['periodos_gracia'] ?? 0) : 0;
        if ($gracia > 0 && $gracia >= (int) $d['numero_meses'] * 2) {
            return back()->withInput()->withErrors(['periodos_gracia' => 'La gracia debe ser menor que el número de quincenas del plazo (' . ((int) $d['numero_meses'] * 2) . '). Debe quedar al menos una cuota.']);
        }

        try {
            $id = $this->cuenta->registrarCompra(
                $cliente->cliente_id,
                $producto,
                (float) $d['cantidad'],
                Carbon::parse($d['fecha_compra']),
                $d['modalidad_pago'],
                $d['modalidad_pago'] === 'CUOTAS' ? (int) $d['numero_meses'] : null,
                $gracia
            );
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->withErrors(['cantidad' => $e->getMessage()]);
        }

        Bitacora::registrar('alta_compra', 'compra', $id, [
            'cliente' => $cliente->nombre_completo,
            'producto' => $producto->descripcion,
            'monto' => round($d['cantidad'] * $producto->precio_lista, 2),
            'modalidad' => $d['modalidad_pago'],
            'periodos_gracia' => $gracia,
        ]);

        return redirect("/compras/{$id}")->with('success', 'Compra registrada. Se generó su plan de pagos.');
    }

    public function ver(string $id)
    {
        $compra = $this->compraDeMiTienda($id);

        return view('compras.ver', $this->datosCompra($compra));
    }

    /** Datos de una compra con sus obligaciones (se reutiliza en la vista del cliente). */
    public function datosCompra(object $compra): array
    {
        $obligaciones = DB::table('obligacion')->where('compra_id', $compra->compra_id)->orderBy('numero_cuota')->get();
        $cliente = DB::table('cliente')->where('cliente_id', $compra->cliente_id)->first();
        $producto = DB::table('producto')->where('producto_id', $compra->producto_id)->first();
        $tea = (float) $compra->tea_aplicada / 100;

        // Filas de los períodos de gracia total (no son obligaciones de pago, se reconstruyen del cronograma)
        $filasGracia = [];
        if ($compra->modalidad_pago === 'CUOTAS' && (int) $compra->periodos_gracia > 0) {
            $r = app(CronogramaFrances::class)->generar((float) $compra->monto, $tea, (int) $compra->numero_meses,
                Carbon::parse($compra->fecha_compra), (int) $compra->periodos_gracia);
            $filasGracia = array_values(array_filter($r['filas'], fn ($f) => $f['gracia'] === 'T'));
        }

        return [
            'compra' => $compra,
            'cliente' => $cliente,
            'producto' => $producto,
            'obligaciones' => $obligaciones,
            'teq' => CronogramaFrances::teq($tea),
            'filasGracia' => $filasGracia,
            'esAntigua' => $compra->modalidad_pago === 'CUOTAS' && $compra->periodos_gracia === null,
            'tasa_gracia' => CuentaCorriente::tasaDias($tea, (int) $compra->dias_gracia),
        ];
    }

    private function compraDeMiTienda(string $id): object
    {
        $compra = DB::table('compra as c')
            ->join('cliente as cl', 'cl.cliente_id', '=', 'c.cliente_id')
            ->where('c.compra_id', $id)
            ->where('cl.tienda_id', Auth::user()->tienda_id)
            ->select('c.*')
            ->first();
        abort_if(! $compra, 404);

        return $compra;
    }
}
