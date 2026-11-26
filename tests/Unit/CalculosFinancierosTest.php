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


test('Simulador: caso principal con siete decimales y dos quincenas de gracia', function () {
    $r = (new CronogramaFrances())->generar(450.00, 0.24, 3, Carbon::parse('2026-09-15'), 2, CronogramaFrances::PRECISION_SIMULADOR);
    $resumen = $r['resumen'];

    expect($resumen['teq'])->toEqualWithDelta(0.0090033, 1e-12);
    expect($resumen['capital_financiado'])->toEqualWithDelta(458.1394467, 1e-7);
    expect($resumen['cuota'])->toEqualWithDelta(117.1243939, 1e-7);
    expect($resumen['total_intereses'])->toEqualWithDelta(18.4975757, 1e-7);
    expect($resumen['total_a_pagar'])->toEqualWithDelta(468.4975757, 1e-7);
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
    'sin gracia' => [0, 450.0, 77.3810178, 464.2861066],
    'una quincena' => [1, 454.0514850, 93.2777297, 466.3886485],
    'dos quincenas' => [2, 458.1394467, 117.1243939, 468.4975757],
    'tres quincenas' => [3, 462.2642136, 156.8709629, 470.6128888],
]);

test('Simulador: tasas cero o muy pequeñas liquidan sin división por cero', function ($tea, $gracia) {
    $r = (new CronogramaFrances())->generar(450.00, $tea, 3, Carbon::parse('2026-09-15'), $gracia, CronogramaFrances::PRECISION_SIMULADOR);
    $resumen = $r['resumen'];
    $teqEsperada = round(expm1(log1p($tea) / 24), 7);
    expect($resumen['teq'])->toBe($teqEsperada);
    expect(is_finite($resumen['cuota']))->toBeTrue();
    expect(end($r['filas'])['saldo_final'])->toBe(0.0);
    expect(array_sum(array_column($r['filas'], 'amortizacion')))->toEqualWithDelta($resumen['capital_financiado'], 1e-7);
    expect($resumen['total_a_pagar'] - 450)->toEqualWithDelta($resumen['total_intereses'], 1e-7);
    if ($teqEsperada == 0.0) {
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

test('Regresión: las compras reales conservan sus cuotas y la TEQ sin redondear', function () {
    $r = (new CronogramaFrances())->generar(450.00, 0.24, 3, Carbon::parse('2026-09-15'), 2);
    expect($r['resumen']['teq'])->toBe(pow(1.24, 15 / 360) - 1);
    expect($r['resumen']['capital_financiado'])->toBe(458.14);
    expect($r['resumen']['cuota'])->toBe(117.12);
    expect($r['resumen']['total_intereses'])->toBe(18.50);
    expect($r['resumen']['total_a_pagar'])->toBe(468.50);
    expect(array_column($r['filas'], 'cuota'))->toBe([0.0, 0.0, 117.12, 117.12, 117.12, 117.14]);
    expect(end($r['filas'])['saldo_final'])->toBe(0.0);
});


test('Simulador HTTP: usa siete decimales y explica el redondeo solo cuando corresponde', function ($tea, $gracia, $mostrarNota) {
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
        $esperado = (new CronogramaFrances())->generar(450, $tea / 100, 3, Carbon::parse('2026-09-15T10:00'), $gracia, CronogramaFrances::PRECISION_SIMULADOR);

        return $resultado == $esperado;
    });
    if ($mostrarNota) {
        $respuesta->assertSee('Los cálculos usan siete decimales internos.')
            ->assertSee('468.48')->assertSee('468.50');
    } else {
        $respuesta->assertDontSee('Los cálculos usan siete decimales internos.');
    }
})->with([
    'caso principal con diferencia visible' => [24.0, 2, true],
    'tasa cero sin diferencia visible' => [0.0, 0, false],
    'tasa muy pequeña aceptada por el formulario' => [0.0000001, 2, false],
])->uses(\Tests\TestCase::class);
