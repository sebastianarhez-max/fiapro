<?php

namespace App\Http\Controllers;

use App\Services\CronogramaFrances;
use Carbon\Carbon;
use Illuminate\Http\Request;
use InvalidArgumentException;

/** Simulador del cronograma de pagos en cuotas (no registra la compra). */
class CronogramaController extends Controller
{
    public function formulario()
    {
        return view('cronograma', [
            'resultado' => null,
            // Valores del Juego de Prueba N.° 2 del informe
            'datos' => [
                'capital' => 450,
                'tea' => 24,
                'meses' => 3,
                'fecha_hora_compra' => '2026-09-15T10:00',
                'gracia' => 0,
            ],
        ]);
    }

    public function calcular(Request $request, CronogramaFrances $servicio)
    {
        $datos = $request->validate([
            'capital' => 'required|numeric|gt:0|max:' . config('fiapro.limite_max'),
            'tea' => 'required|numeric|min:0|max:' . config('fiapro.tea_max_ordinaria'),
            'meses' => 'required|integer|between:1,' . config('fiapro.meses_max'),
            'gracia' => 'required|integer|between:0,' . CronogramaFrances::GRACIA_MAX,
            'fecha_hora_compra' => 'required|date',
        ], [
            'capital.max' => 'El monto no puede superar el límite máximo de crédito (' . config('fiapro.limite_max') . ').',
            'tea.max' => 'La TEA no puede superar ' . config('fiapro.tea_max_ordinaria') . '%.',
        ]);

        try {
            $resultado = $servicio->generar(
                (float) $datos['capital'],
                (float) $datos['tea'] / 100,
                (int) $datos['meses'],
                Carbon::parse($datos['fecha_hora_compra']),
                (int) $datos['gracia'],
                CronogramaFrances::PRECISION_SIMULADOR
            );
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->withErrors(['gracia' => $e->getMessage()]);
        }

        return view('cronograma', compact('resultado', 'datos'));
    }
}
