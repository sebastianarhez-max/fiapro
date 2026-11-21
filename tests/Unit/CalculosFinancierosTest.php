<?php

use App\Services\CronogramaFrances;
use App\Services\CuentaCorriente;
use Carbon\Carbon;

/*
 * Verifica los juegos de datos de prueba del informe (punto 6.4).
 * Ejecutar con: php artisan test --filter=CalculosFinancieros
 */

test('Juego 1: pago único a la quincena con 5 días de mora', function () {
    $c = new CronogramaFrances();
    $compra = Carbon::parse('2026-09-15 10:00');
    $vence = $c->fechaPeriodo($compra, 1);              // 15 días después de la compra
    expect($vence->toDateString())->toBe('2026-09-30');

    $interes = CuentaCorriente::interesOrdinario(250.00, 0.24, 15);
    expect($interes)->toBe(2.25);                       // 250 × TEQ

    $total = round(250.00 + $interes, 2);
    $diasMora = $c->dias360($vence, Carbon::parse('2026-10-05'));
    expect($diasMora)->toBe(5);

    $mora = CuentaCorriente::interesMoratorio($total, 0.36, $diasMora);
    expect($mora)->toBe(1.08);
    expect(round($total + $mora, 2))->toBe(253.33);
});

test('Juego 2: 6 cuotas quincenales sin gracia, contadas desde la compra', function () {
    $r = (new CronogramaFrances())->generar(450.00, 0.24, 3, Carbon::parse('2026-09-15 10:00'), 0);

    expect(round($r['resumen']['teq'], 7))->toBe(0.0090033);
    expect($r['resumen']['n_cuotas'])->toBe(6);
    expect($r['resumen']['cuota'])->toBe(77.38);
    expect($r['resumen']['total_intereses'])->toBe(14.28);
    expect($r['resumen']['total_a_pagar'])->toBe(464.28);

    // No existe período 0: el período 1 vence 15 días después de la compra
    $p1 = $r['filas'][0];
    expect($p1['n'])->toBe(1);
    expect($p1['interes'])->toBe(4.05);          // 450 × TEQ
    expect($p1['cuota'])->toBe(77.38);
    expect($p1['amortizacion'])->toBe(73.33);

    expect(end($r['filas'])['saldo_final'])->toBe(0.0);
    $fechas = array_map(fn ($f) => $f['fecha']->format('d/m/Y'), $r['filas']);
    expect($fechas)->toBe(['30/09/2026', '15/10/2026', '30/10/2026', '15/11/2026', '30/11/2026', '15/12/2026']);
});

test('Juego 2 con 1 quincena de gracia total elegida por el cliente', function () {
    $r = (new CronogramaFrances())->generar(450.00, 0.24, 3, Carbon::parse('2026-09-15 10:00'), 1);

    $p1 = $r['filas'][0];
    expect($p1['gracia'])->toBe('T');
    expect($p1['cuota'])->toBe(0.0);
    expect($p1['interes'])->toBe(4.05);
    expect($p1['saldo_final'])->toBe(454.05);

    expect($r['resumen']['n_cuotas'])->toBe(5);
    expect($r['resumen']['cuota'])->toBe(93.28);
    expect($r['resumen']['total_intereses'])->toBe(16.38);
    expect($r['resumen']['fecha_primer_pago']->format('d/m/Y'))->toBe('15/10/2026');
    $ultima = end($r['filas']);
    expect($ultima['n'])->toBe(6);
    expect($ultima['cuota'])->toBe(93.26);
    expect($ultima['saldo_final'])->toBe(0.0);
});

test('La gracia debe dejar al menos una cuota', function () {
    expect(fn () => (new CronogramaFrances())->generar(300.00, 0.24, 1, Carbon::parse('2026-09-15'), 2))
        ->toThrow(InvalidArgumentException::class);
});

test('Las quincenas se cuentan en base 30/360 desde la compra', function () {
    $c = new CronogramaFrances();
    $compra = Carbon::parse('2026-01-31 18:00');      // el día 31 cuenta como 30

    expect($c->fechaPeriodo($compra, 1)->toDateString())->toBe('2026-02-15');
    expect($c->fechaPeriodo($compra, 2)->toDateString())->toBe('2026-02-28');   // el 30 de febrero pasa al último día
    expect($c->fechaPeriodo($compra, 3)->toDateString())->toBe('2026-03-15');
});
