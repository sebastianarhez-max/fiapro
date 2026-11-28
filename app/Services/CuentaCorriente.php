<?php

namespace App\Services;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Lógica de la cuenta corriente del cliente: crédito utilizado, registro de compras,
 * listado de pago por quincena y registro de pagos con mora.
 *
 * Convenciones (Anexo A del enunciado): base 360, meses de 30 días, montos con 2 decimales,
 * tasas con 7 o más decimales y prelación de pagos: mora, interés ordinario y capital.
 */
class CuentaCorriente
{
    public const BASE_ANUAL = 360;

    public function __construct(private CronogramaFrances $cronograma)
    {
    }

    // ------------------------------------------------------------------ fórmulas

    /** Fórmula 2: tasa equivalente para d días a partir de una TEA (decimal). */
    public static function tasaDias(float $tea, int $dias): float
    {
        if ($dias <= 0) {
            return 0.0;
        }

        return pow(1 + $tea, $dias / self::BASE_ANUAL) - 1;
    }

    /** Fórmula 4: interés ordinario de un pago único. */
    public static function interesOrdinario(float $capital, float $tea, int $dias): float
    {
        return round($capital * self::tasaDias($tea, $dias), 2);
    }

    /** Fórmula 10: interés moratorio sobre el monto vencido. */
    public static function interesMoratorio(float $montoVencido, float $teaMora, int $diasMora): float
    {
        return round($montoVencido * self::tasaDias($teaMora, $diasMora), 2);
    }

    // ------------------------------------------------------------------ consultas

    /** Configuración de crédito vigente del cliente. */
    public function configuracion(string $clienteId): ?object
    {
        return DB::table('configuracion_credito')
            ->where('cliente_id', $clienteId)
            ->where('activo', 1)
            ->orderByDesc('vigente_desde')
            ->first();
    }

    /** Crédito utilizado (CU): capital pendiente de todas las obligaciones no pagadas. */
    public function creditoUtilizado(string $clienteId): float
    {
        $cu = DB::table('obligacion as o')
            ->join('compra as c', 'c.compra_id', '=', 'o.compra_id')
            ->where('c.cliente_id', $clienteId)
            ->where('o.estado', 'pendiente')
            ->sum('o.capital');

        return round((float) $cu, 2);
    }

    /** Resumen de la línea de crédito: LC, CU y CD = LC − CU. */
    public function linea(string $clienteId): array
    {
        $cfg = $this->configuracion($clienteId);
        $lc = $cfg ? (float) $cfg->limite_credito : 0.0;
        $cu = $this->creditoUtilizado($clienteId);

        return [
            'limite' => $lc,
            'utilizado' => $cu,
            'disponible' => round(max(0, $lc - $cu), 2),
            'moneda' => $cfg->moneda ?? 'PEN',
        ];
    }

    // ------------------------------------------------------------------ compras

    /**
     * Registra una compra a crédito y genera sus obligaciones.
     * - Pago único: una obligación que vence a la quincena (compra + 15 días) con el interés de una quincena.
     * - En cuotas: cronograma francés con una cuota cada 15 días contados desde la fecha de compra;
     *   el cliente elige de 0 a 3 quincenas de gracia total (dentro del plazo).
     *
     * @return string compra_id
     */
    public function registrarCompra(
        string $clienteId,
        object $producto,
        float $cantidad,
        CarbonInterface $fechaCompra,
        string $modalidad,
        ?int $meses,
        int $gracia = 0
    ): string {
        $cfg = $this->configuracion($clienteId);
        if (! $cfg) {
            throw new InvalidArgumentException('El cliente no tiene una configuración de crédito activa.');
        }

        $monto = round($cantidad * (float) $producto->precio_lista, 2);
        $linea = $this->linea($clienteId);
        if ($linea['utilizado'] + $monto > $linea['limite'] + 0.000001) {
            throw new InvalidArgumentException(sprintf(
                'La compra (%s) supera el crédito disponible (%s). No se puede registrar.',
                number_format($monto, 2),
                number_format($linea['disponible'], 2)
            ));
        }

        $tea = (float) $cfg->tasa_comp / 100;
        $teaMora = (float) $cfg->tasa_mora / 100;
        $compraId = (string) Str::uuid();

        DB::transaction(function () use ($compraId, $clienteId, $producto, $cantidad, $fechaCompra, $modalidad, $meses, $gracia, $monto, $tea, $teaMora, $cfg) {
            $base = [
                'compra_id' => $compraId,
                'cliente_id' => $clienteId,
                'producto_id' => $producto->producto_id,
                'cantidad' => $cantidad,
                'precio_credito' => $producto->precio_lista,
                'monto' => $monto,
                'fecha_compra' => $fechaCompra->format('Y-m-d H:i:s'),
                'modalidad_pago' => $modalidad,
                'numero_meses' => $modalidad === 'CUOTAS' ? $meses : null,
                'estado' => 'activa',
                'tea_aplicada' => $cfg->tasa_comp,
                'tea_mora_aplicada' => $cfg->tasa_mora,
                'moneda' => $cfg->moneda,
                'creado_en' => now(),
            ];

            if ($modalidad === 'UNICO') {
                // Pago único: se paga a la quincena (15 días comerciales después de la compra)
                $fechaPago = $this->cronograma->fechaPeriodo($fechaCompra, 1);
                $dias = CronogramaFrances::DIAS_QUINCENA;
                $interes = self::interesOrdinario($monto, $tea, $dias);

                DB::table('compra')->insert($base + [
                    'fecha_primer_pago' => $fechaPago->toDateString(),
                    'dias_gracia' => $dias,
                    'interes_gracia' => null,
                    'capital_financiado' => $monto,
                    'cuota' => round($monto + $interes, 2),
                ]);
                DB::table('obligacion')->insert([
                    'obligacion_id' => (string) Str::uuid(),
                    'compra_id' => $compraId,
                    'numero_cuota' => 1,
                    'fecha_vencimiento' => $fechaPago->toDateString(),
                    'capital' => $monto,
                    'interes_compensatorio' => $interes,
                    'monto_total' => round($monto + $interes, 2),
                    'saldo' => round($monto + $interes, 2),
                    'estado' => 'pendiente',
                ]);

                return;
            }

            $r = $this->cronograma->generar($monto, $tea, (int) $meses, $fechaCompra, $gracia);
            $res = $r['resumen'];

            DB::table('compra')->insert($base + [
                'fecha_primer_pago' => $res['fecha_primer_pago']->toDateString(),  // vencimiento de la primera cuota
                'periodos_gracia' => $gracia,
                'dias_gracia' => $gracia * CronogramaFrances::DIAS_QUINCENA,       // días de gracia total (quincenas × 15)
                'interes_gracia' => $res['interes_gracia'],
                'capital_financiado' => $res['capital_financiado'],
                'cuota' => $res['cuota'],
            ]);

            foreach ($r['filas'] as $f) {
                if ($f['gracia'] === 'T') {
                    continue; // los períodos de gracia total no generan obligación de pago
                }
                DB::table('obligacion')->insert([
                    'obligacion_id' => (string) Str::uuid(),
                    'compra_id' => $compraId,
                    'numero_cuota' => $f['n'],
                    'fecha_vencimiento' => $f['fecha']->toDateString(),
                    'capital' => $f['amortizacion'],
                    'interes_compensatorio' => $f['interes'],
                    'monto_total' => $f['cuota'],
                    'saldo' => $f['cuota'],
                    'estado' => 'pendiente',
                ]);
            }
        });

        return $compraId;
    }

    // ------------------------------------------------------------------ pagos

    /**
     * Fórmula 10 aplicada a una obligación: total exigible a una fecha de pago real,
     * con sus componentes en orden de prelación (mora, interés ordinario y capital).
     */
    public function exigible(object $obligacion, CarbonInterface $fechaPago, float $teaMora): array
    {
        $venc = Carbon::parse($obligacion->fecha_vencimiento)->startOfDay();
        $diasMora = $fechaPago->copy()->startOfDay()->gt($venc)
            ? max(0, $this->cronograma->dias360($venc, $fechaPago->copy()->startOfDay()))
            : 0;
        $vencido = (float) $obligacion->saldo;              // capital + interés ordinario de la obligación
        $mora = self::interesMoratorio($vencido, $teaMora, $diasMora);

        return [
            'dias_mora' => $diasMora,
            'tasa_mora_periodo' => self::tasaDias($teaMora, $diasMora),
            'mora' => $mora,
            'interes' => (float) $obligacion->interes_compensatorio,
            'capital' => (float) $obligacion->capital,
            'monto_vencido' => $vencido,
            'total' => round($vencido + $mora, 2),
        ];
    }

    /**
     * Registra el pago total de una obligación. No se aceptan pagos parciales:
     * el monto debe ser exactamente el total exigible a la fecha real de pago.
     */
    public function registrarPago(object $obligacion, object $compra, CarbonInterface $fechaPago, float $monto, string $medio, ?string $referencia): string
    {
        if ($obligacion->estado !== 'pendiente') {
            throw new InvalidArgumentException('Esta cuota ya fue pagada.');
        }
        if ($fechaPago->copy()->startOfDay()->lt(Carbon::parse($compra->fecha_compra)->startOfDay())) {
            throw new InvalidArgumentException('La fecha de pago no puede ser anterior a la fecha de compra.');
        }
        $anterior = DB::table('obligacion')
            ->where('compra_id', $obligacion->compra_id)
            ->where('estado', 'pendiente')
            ->where('numero_cuota', '<', $obligacion->numero_cuota)
            ->exists();
        if ($anterior) {
            throw new InvalidArgumentException('Primero debe pagarse la cuota anterior de esta compra.');
        }

        $teaMora = (float) ($compra->tea_mora_aplicada ?? 0) / 100;
        $e = $this->exigible($obligacion, $fechaPago, $teaMora);
        if (abs(round($monto, 2) - $e['total']) > 0.004) {
            throw new InvalidArgumentException(sprintf(
                'No se aceptan pagos parciales: el monto debe ser exactamente %s.',
                number_format($e['total'], 2)
            ));
        }

        $pagoId = (string) Str::uuid();
        DB::transaction(function () use ($pagoId, $obligacion, $compra, $fechaPago, $e, $medio, $referencia) {
            DB::table('pago')->insert([
                'pago_id' => $pagoId,
                'obligacion_id' => $obligacion->obligacion_id,
                'fecha_real_pago' => $fechaPago->toDateString(),
                'monto_pagado' => $e['total'],
                'monto_interes_mora' => $e['mora'],
                'monto_interes_comp' => round($e['interes'], 2),   // la obligación guarda 7 decimales; el pago, céntimos
                'monto_capital' => round($e['capital'], 2),
                'moneda' => $compra->moneda ?? 'PEN',
                'medio_pago' => $medio,
                'referencia' => $referencia,
                'creado_en' => now(),
            ]);
            DB::table('obligacion')->where('obligacion_id', $obligacion->obligacion_id)->update([
                'saldo' => 0,
                'estado' => 'pagado',
                'dias_mora' => $e['dias_mora'],
                'interes_moratorio' => $e['mora'],
                'fecha_pago_real' => $fechaPago->toDateString(),
            ]);
            $quedan = DB::table('obligacion')->where('compra_id', $obligacion->compra_id)->where('estado', 'pendiente')->exists();
            if (! $quedan) {
                DB::table('compra')->where('compra_id', $obligacion->compra_id)->update(['estado' => 'cancelada']);
            }
        });

        return $pagoId;
    }

    /**
     * Recalcula con el cronograma vigente (7 decimales) las cuotas PENDIENTES de las compras en cuotas.
     * Las cuotas pagadas no se tocan. Se usa al actualizar la base y al cargar los datos de demostración.
     */
    public function recalcularCuotasPendientes(): int
    {
        $cambios = 0;
        $compras = DB::table('compra')->where('modalidad_pago', 'CUOTAS')->get();
        foreach ($compras as $compra) {
            $r = $this->cronograma->generar(
                (float) $compra->monto,
                (float) $compra->tea_aplicada / 100,
                (int) $compra->numero_meses,
                Carbon::parse($compra->fecha_compra),
                (int) ($compra->periodos_gracia ?? 0)
            );
            $filas = [];
            foreach ($r['filas'] as $f) {
                $filas[$f['n']] = $f;
            }
            $pendientes = DB::table('obligacion')
                ->where('compra_id', $compra->compra_id)
                ->where('estado', 'pendiente')
                ->get();
            foreach ($pendientes as $o) {
                $f = $filas[(int) $o->numero_cuota] ?? null;
                if (! $f) {
                    continue;
                }
                DB::table('obligacion')->where('obligacion_id', $o->obligacion_id)->update([
                    'capital' => $f['amortizacion'],
                    'interes_compensatorio' => $f['interes'],
                    'monto_total' => $f['cuota'],
                    'saldo' => $f['cuota'],
                ]);
                $cambios++;
            }
            $datos = ['cuota' => $r['resumen']['cuota']];
            if ($compra->periodos_gracia !== null) {          // compras con el esquema quincenal vigente
                $datos['interes_gracia'] = $r['resumen']['interes_gracia'];
                $datos['capital_financiado'] = $r['resumen']['capital_financiado'];
            }
            DB::table('compra')->where('compra_id', $compra->compra_id)->update($datos);
        }

        return $cambios;
    }

    // ------------------------------------------------------------------ listado de pago

    /**
     * Listado de pago de un cliente a una fecha (estado de cuenta quincenal): cuotas pendientes ya vencidas
     * (con la mora calculada hasta esa fecha) y las que vencen en los próximos 15 días,
     * ordenadas por fecha y hora de compra.
     */
    public function listado(string $clienteId, CarbonInterface $fecha): array
    {
        $cfg = $this->configuracion($clienteId);
        if (! $cfg) {
            throw new InvalidArgumentException('El cliente no tiene configuración de crédito.');
        }
        $fecha = Carbon::instance($fecha)->copy()->startOfDay();
        $hasta = $fecha->copy()->addDays(CronogramaFrances::DIAS_QUINCENA);

        $filas = DB::table('obligacion as o')
            ->join('compra as c', 'c.compra_id', '=', 'o.compra_id')
            ->join('producto as p', 'p.producto_id', '=', 'c.producto_id')
            ->where('c.cliente_id', $clienteId)
            ->where('o.estado', 'pendiente')
            ->where('c.fecha_compra', '<', $fecha->copy()->addDay()->format('Y-m-d H:i:s'))
            ->where('o.fecha_vencimiento', '<=', $hasta->toDateString())
            ->orderBy('c.fecha_compra')
            ->orderBy('o.numero_cuota')
            ->select('o.*', 'c.fecha_compra', 'c.modalidad_pago', 'c.numero_meses', 'c.cantidad', 'c.tea_mora_aplicada', 'p.descripcion', 'p.imagen_url')
            ->get();

        $items = [];
        $tot = ['capital' => 0.0, 'interes' => 0.0, 'mora' => 0.0, 'total' => 0.0];
        foreach ($filas as $o) {
            $venc = Carbon::parse($o->fecha_vencimiento);
            $vencida = $venc->lt($fecha);
            // Si la cuota ya venció, la mora se calcula hasta la fecha del listado
            $e = $this->exigible($o, $vencida ? $fecha : $venc, (float) $o->tea_mora_aplicada / 100);
            $items[] = [
                'obligacion_id' => $o->obligacion_id,
                'fecha_compra' => Carbon::parse($o->fecha_compra)->format('d/m/Y H:i'),
                'descripcion' => $o->descripcion,
                'imagen' => $o->imagen_url,
                'cantidad' => (float) $o->cantidad,
                'detalle' => $o->modalidad_pago === 'UNICO' ? 'Pago único' : "Cuota {$o->numero_cuota} de " . ($o->numero_meses * 2),
                'vencimiento' => $venc->format('d/m/Y'),
                'vencida' => $vencida,
                'capital' => (float) $o->capital,
                'interes' => (float) $o->interes_compensatorio,
                'dias_mora' => $e['dias_mora'],
                'mora' => $e['mora'],
                'total' => $e['total'],
            ];
            $tot['capital'] += (float) $o->capital;
            $tot['interes'] += (float) $o->interes_compensatorio;
            $tot['mora'] += $e['mora'];
            $tot['total'] += $e['total'];
        }

        return [
            'fecha' => $fecha,
            'hasta' => $hasta,
            'moneda' => $cfg->moneda,
            'tea' => (float) $cfg->tasa_comp,
            'tea_mora' => (float) $cfg->tasa_mora,
            'items' => $items,
            'totales' => array_map(fn ($v) => round($v, 2), $tot),
        ];
    }

    /** Guarda (o actualiza) el listado de una fecha en la tabla listado_pago. */
    public function guardarListado(string $clienteId, array $l): string
    {
        $existente = DB::table('listado_pago')
            ->where('cliente_id', $clienteId)
            ->where('fecha_corte', $l['fecha']->format('Y-m-d H:i:s'))
            ->value('listado_id');
        $id = $existente ?: (string) Str::uuid();
        $datos = [
            'fecha_pago' => $l['hasta']->toDateString(),
            'total_capital' => $l['totales']['capital'],
            'total_interes' => $l['totales']['interes'],
            'total_mora' => $l['totales']['mora'],
            'total' => $l['totales']['total'],
            'moneda' => $l['moneda'],
            'detalle' => json_encode($l['items'], JSON_UNESCAPED_UNICODE),
            'generado_en' => now(),
        ];
        if ($existente) {
            DB::table('listado_pago')->where('listado_id', $id)->update($datos);
        } else {
            DB::table('listado_pago')->insert($datos + [
                'listado_id' => $id,
                'cliente_id' => $clienteId,
                'fecha_corte' => $l['fecha']->format('Y-m-d H:i:s'),   // fecha del listado
            ]);
        }

        return $id;
    }
}
