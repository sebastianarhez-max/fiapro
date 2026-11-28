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
    // Se calcula con 7 decimales y se muestra redondeado a 2
    expect($r['resumen']['cuota'])->toEqualWithDelta(77.3810076, 1e-7);
    expect(round($r['resumen']['cuota'], 2))->toBe(77.38);
    expect(round($r['resumen']['total_intereses'], 2))->toBe(14.29);
    expect(round($r['resumen']['total_a_pagar'], 2))->toBe(464.29);

    // No existe período 0: el período 1 vence 15 días después de la compra
    $p1 = $r['filas'][0];
    expect($p1['n'])->toBe(1);
    expect(round($p1['interes'], 2))->toBe(4.05);          // 450 × TEQ
    expect(round($p1['cuota'], 2))->toBe(77.38);
    expect(round($p1['amortizacion'], 2))->toBe(73.33);

    expect(end($r['filas'])['saldo_final'])->toBe(0.0);
    $fechas = array_map(fn ($f) => $f['fecha']->format('d/m/Y'), $r['filas']);
    expect($fechas)->toBe(['30/09/2026', '15/10/2026', '30/10/2026', '15/11/2026', '30/11/2026', '15/12/2026']);
});

test('Juego 2 con 1 quincena de gracia total elegida por el cliente', function () {
    $r = (new CronogramaFrances())->generar(450.00, 0.24, 3, Carbon::parse('2026-09-15 10:00'), 1);

    $p1 = $r['filas'][0];
    expect($p1['gracia'])->toBe('T');
    expect($p1['cuota'])->toBe(0.0);
    expect(round($p1['interes'], 2))->toBe(4.05);
    expect(round($p1['saldo_final'], 2))->toBe(454.05);

    expect($r['resumen']['n_cuotas'])->toBe(5);
    expect($r['resumen']['cuota'])->toEqualWithDelta(93.2777157, 1e-7);
    expect(round($r['resumen']['total_intereses'], 2))->toBe(16.39);
    expect(round($r['resumen']['total_a_pagar'], 2))->toBe(466.39);
    expect($r['resumen']['fecha_primer_pago']->format('d/m/Y'))->toBe('15/10/2026');

    // Con 7 decimales todas las cuotas son iguales; al redondear a 2 todas son 93.28
    $cuotas = array_map(fn ($f) => round($f['cuota'], 2), array_slice($r['filas'], 1));
    expect($cuotas)->toBe([93.28, 93.28, 93.28, 93.28, 93.28]);
    $ultima = end($r['filas']);
    expect($ultima['n'])->toBe(6);
    expect(round($ultima['amortizacion'], 2))->toBe(92.45);
    expect(round($ultima['interes'], 2))->toBe(0.83);
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


test('Simulador: caso principal con siete decimales y dos quincenas de gracia', function () {
    $r = (new CronogramaFrances())->generar(450.00, 0.24, 3, Carbon::parse('2026-09-15'), 2, CronogramaFrances::PRECISION_SIMULADOR);
    $resumen = $r['resumen'];

    expect($resumen['teq'])->toEqualWithDelta(pow(1.24, 15 / 360) - 1, 1e-15);
    expect($resumen['capital_financiado'])->toEqualWithDelta(458.1394121, 1e-7);
    expect($resumen['cuota'])->toEqualWithDelta(117.1243741, 1e-7);
    expect($resumen['total_intereses'])->toEqualWithDelta(18.4974964, 1e-7);
    expect($resumen['total_a_pagar'])->toEqualWithDelta(468.4974964, 1e-7);
    expect(end($r['filas'])['saldo_final'])->toBe(0.0);

    $pagos = array_slice($r['filas'], 2);
    expect(array_map(fn ($f) => number_format($f['cuota'], 2), $pagos))
        ->toBe(['117.12', '117.12', '117.12', '117.12']);
    expect(number_format(array_sum(array_column($pagos, 'cuota')), 2))->toBe('468.50');
    expect(array_sum(array_map(fn ($f) => (int) round($f['cuota'] * 100), $pagos)))->toBe(46848);
    expect(abs(end($pagos)['cuota'] - $resumen['cuota']))->toBeLessThanOrEqual(1.1e-7);
});

test('Simulador: gracia, fechas, amortizaciones y totales mantienen sus identidades', function ($gracia, $capitalFinanciado, $cuota, $total) {
    $servicio = new CronogramaFrances();
    $compra = Carbon::parse('2026-09-15');
    $r = $servicio->generar(450.00, 0.24, 3, $compra, $gracia, CronogramaFrances::PRECISION_SIMULADOR);
    $resumen = $r['resumen'];

    expect($resumen['n_cuotas'])->toBe(6 - $gracia);
    expect($resumen['capital_financiado'])->toEqualWithDelta($capitalFinanciado, 1e-7);
    expect($resumen['cuota'])->toEqualWithDelta($cuota, 1e-7);
    expect($resumen['total_a_pagar'])->toEqualWithDelta($total, 1e-7);
    expect($resumen['fecha_primer_pago']->toDateString())->toBe($servicio->fechaPeriodo($compra, $gracia + 1)->toDateString());
    expect(array_map(fn ($f) => $f['fecha']->format('d/m/Y'), $r['filas']))
        ->toBe(['30/09/2026', '15/10/2026', '30/10/2026', '15/11/2026', '30/11/2026', '15/12/2026']);

    $saldo = 450.0;
    foreach ($r['filas'] as $indice => $fila) {
        expect($fila['saldo_inicial'])->toEqualWithDelta($saldo, 1e-7);
        if ($indice < $gracia) {
            expect($fila['gracia'])->toBe('T');
            expect($fila['cuota'])->toBe(0.0);
            expect($fila['amortizacion'])->toBe(0.0);
            expect($fila['saldo_final'])->toEqualWithDelta($saldo + $fila['interes'], 1e-7);
        } else {
            expect($fila['gracia'])->toBe('S');
            expect($fila['cuota'])->toEqualWithDelta($fila['amortizacion'] + $fila['interes'], 1e-7);
            expect($fila['saldo_final'])->toEqualWithDelta($saldo - $fila['amortizacion'], 1e-7);
            expect(abs($fila['cuota'] - $cuota))->toBeLessThanOrEqual(6e-7);
        }
        foreach (['saldo_inicial', 'interes', 'cuota', 'amortizacion', 'saldo_final'] as $campo) {
            expect($fila[$campo])->toEqualWithDelta(round($fila[$campo], CronogramaFrances::PRECISION_SIMULADOR), 1e-12);
        }
        $saldo = $fila['saldo_final'];
    }

    expect($saldo)->toBe(0.0);
    expect(array_sum(array_column($r['filas'], 'amortizacion')))->toEqualWithDelta($capitalFinanciado, 1e-7);
    expect(array_sum(array_column($r['filas'], 'interes')))->toEqualWithDelta($resumen['total_intereses'], 1e-7);
    expect(array_sum(array_column($r['filas'], 'cuota')))->toEqualWithDelta($total, 1e-7);
    expect($total - $resumen['capital'])->toEqualWithDelta($resumen['total_intereses'], 1e-7);
})->with([
    'sin gracia' => [0, 450.0, 77.3810076, 464.2860456],
    'una quincena' => [1, 454.0514678, 93.2777157, 466.3885785],
    'dos quincenas' => [2, 458.1394121, 117.1243741, 468.4974964],
    'tres quincenas' => [3, 462.2641612, 156.8709333, 470.6127999],
]);

test('Simulador: tasas cero o muy pequeñas liquidan sin división por cero', function ($tea, $gracia) {
    $r = (new CronogramaFrances())->generar(450.00, $tea, 3, Carbon::parse('2026-09-15'), $gracia, CronogramaFrances::PRECISION_SIMULADOR);
    $resumen = $r['resumen'];
    expect($resumen['teq'])->toEqualWithDelta(expm1(log1p($tea) / 24), 1e-15);
    expect(is_finite($resumen['cuota']))->toBeTrue();
    expect(end($r['filas'])['saldo_final'])->toBe(0.0);
    expect(array_sum(array_column($r['filas'], 'amortizacion')))->toEqualWithDelta($resumen['capital_financiado'], 1e-7);
    expect($resumen['total_a_pagar'] - 450)->toEqualWithDelta($resumen['total_intereses'], 1e-7);
    if (round($resumen['total_intereses'], 7) == 0.0) {
        expect($resumen['total_intereses'])->toBe(0.0);
        expect($resumen['total_a_pagar'])->toBe(450.0);
        expect($resumen['cuota'])->toEqualWithDelta(450 / (6 - $gracia), 1e-7);
    } else {
        expect($resumen['total_intereses'])->toBeGreaterThan(0.0);
        expect($resumen['cuota'])->toBeGreaterThan(450 / (6 - $gracia));
    }
})->with([
    'cero sin gracia' => [0.0, 0],
    'cero con gracia' => [0.0, 2],
    'positiva que redondea a cero' => [1e-12, 2],
    'positiva mínima representable sin gracia' => [0.0000024, 0],
    'positiva mínima representable con gracia' => [0.0000024, 3],
]);

test('Simulador: una única cuota efectiva liquida todo el saldo capitalizado', function ($meses, $gracia, $tea) {
    $r = (new CronogramaFrances())->generar(450.00, $tea, $meses, Carbon::parse('2026-09-15'), $gracia, CronogramaFrances::PRECISION_SIMULADOR);
    $ultima = end($r['filas']);
    expect($r['resumen']['n_cuotas'])->toBe(1);
    expect($ultima['amortizacion'])->toBe($r['resumen']['capital_financiado']);
    expect($ultima['saldo_final'])->toBe(0.0);
    expect($ultima['cuota'])->toEqualWithDelta($r['resumen']['cuota'], 1e-7);
    expect($ultima['cuota'])->toBe($r['resumen']['total_a_pagar']);
})->with([
    [1, 1, 0.24],
    [2, 3, 0.24],
    [1, 1, 0.0],
]);

test('Compras reales: se calcula con 7 decimales y al redondear todas las cuotas son iguales', function () {
    $r = (new CronogramaFrances())->generar(450.00, 0.24, 3, Carbon::parse('2026-09-15'), 2);
    expect($r['resumen']['teq'])->toBe(pow(1.24, 15 / 360) - 1);
    expect($r['resumen']['capital_financiado'])->toEqualWithDelta(458.1394121, 1e-7);
    expect(round($r['resumen']['cuota'], 2))->toBe(117.12);
    expect(round($r['resumen']['total_intereses'], 2))->toBe(18.50);
    expect(round($r['resumen']['total_a_pagar'], 2))->toBe(468.50);
    expect(array_map(fn ($f) => round($f['cuota'], 2), $r['filas']))->toBe([0.0, 0.0, 117.12, 117.12, 117.12, 117.12]);
    expect(round(end($r['filas'])['interes'], 2))->toBe(1.05);
    expect(end($r['filas'])['saldo_final'])->toBe(0.0);
});


test('Simulador HTTP: usa el mismo cálculo que las compras (7 decimales, se muestra con 2)', function ($tea, $gracia, $totalEsperado) {
    // Usuario solo en memoria: esta prueba no guarda registros ni usa RefreshDatabase.
    $usuario = (new \App\Models\User())->forceFill([
        'id' => 1,
        'name' => 'Prueba simulador',
        'rol' => 'cliente',
        'estado' => 'activo',
        'tienda_id' => null,
    ]);

    $respuesta = $this->actingAs($usuario)->post('/cronograma', [
        'capital' => 450,
        'tea' => $tea,
        'meses' => 3,
        'gracia' => $gracia,
        'fecha_hora_compra' => '2026-09-15T10:00',
    ]);

    $respuesta->assertOk()->assertViewHas('resultado', function ($resultado) use ($tea, $gracia) {
        $esperado = (new CronogramaFrances())->generar(450, $tea / 100, 3, Carbon::parse('2026-09-15T10:00'), $gracia);

        return $resultado == $esperado;
    });
    $respuesta->assertSee($totalEsperado);
})->with([
    'caso principal con dos quincenas de gracia' => [24.0, 2, '468.50'],
    'tasa cero' => [0.0, 0, '450.00'],
    'tasa muy pequeña aceptada por el formulario' => [0.0000001, 2, '450.00'],
])->uses(\Tests\TestCase::class);
