@extends('layouts.panel')
@section('titulo', 'Plan de pagos | FiaPro')
@use('App\Support\Moneda')
@php
    $soloLectura = $soloLectura ?? false;
    $mon = $compra->moneda ?? 'PEN';
    $hoy = now()->startOfDay();
    $pagadas = $obligaciones->where('estado', 'pagado')->count();
    $total = $obligaciones->count();
    $esUnico = $compra->modalidad_pago === 'UNICO';
    $siguiente = $obligaciones->firstWhere('estado', 'pendiente');
    $filasGracia = $filasGracia ?? [];
    $esAntigua = $esAntigua ?? false;
    $g = (int) ($compra->periodos_gracia ?? 0);
    $q = fn ($n) => $n . ' ' . ($n === 1 ? 'quincena' : 'quincenas');
@endphp
@section('contenido')
    <div class="page-head">
        <div>
            <div class="crumbs">
                @if($soloLectura)<a href="/mi-cuenta">Mi cuenta</a>@else<a href="/compras">Compras</a>@endif / Plan de pagos
            </div>
            <div class="head-producto">
                @include('partials.foto', ['url' => $producto->imagen_url ?? null, 'tam' => 'lg', 'alt' => $producto->descripcion ?? ''])
                <div>
            <h1>{{ $producto->descripcion ?? 'Compra' }}</h1>
            <p>{{ $cliente->nombre_completo }} · {{ \Carbon\Carbon::parse($compra->fecha_compra)->format('d/m/Y H:i') }} ·
                {{ $esUnico ? 'Pago único' : $compra->numero_meses . ' ' . ($compra->numero_meses == 1 ? 'mes' : 'meses') . ' = ' . $q($compra->numero_meses * 2) . ($g > 0 ? ' (' . $g . ' de gracia + ' . ($compra->numero_meses * 2 - $g) . ' cuotas)' : ' (' . ($compra->numero_meses * 2) . ' cuotas)') }}</p>
                </div>
            </div>
        </div>
        <div class="actions">
            <button class="btn ghost" onclick="window.print()" type="button">@include('partials.icono', ['n' => 'print']) Imprimir</button>
            @if(! $soloLectura && $siguiente)
                <a class="btn" href="/pagos/{{ $siguiente->obligacion_id }}/nuevo">@include('partials.icono', ['n' => 'wallet']) Registrar pago</a>
            @endif
        </div>
    </div>

    <section class="stats">
        <div class="stat"><div class="ico">@include('partials.icono', ['n' => 'cart'])</div><p>Monto de la compra</p><strong>{{ Moneda::f($compra->monto ?? $compra->cantidad * $compra->precio_credito, $mon) }}</strong><small>{{ rtrim(rtrim(number_format($compra->cantidad, 2), '0'), '.') }} × {{ number_format($compra->precio_credito, 2) }}</small></div>
        @if($esUnico)
            <div class="stat"><div class="ico navy">@include('partials.icono', ['n' => 'clock'])</div><p>Días hasta el pago</p><strong>{{ $compra->dias_gracia }}</strong><small>convención 30/360</small></div>
            <div class="stat"><div class="ico">@include('partials.icono', ['n' => 'chart'])</div><p>Interés ordinario</p><strong>{{ Moneda::f($obligaciones->sum('interes_compensatorio'), $mon) }}</strong><small>TEA {{ number_format($compra->tea_aplicada, 2) }}% · i = {{ number_format($tasa_gracia * 100, 7) }}%</small></div>
            @php($ob = $obligaciones->first())
            @if($ob && $ob->estado === 'pagado')
                <div class="stat"><div class="ico">@include('partials.icono', ['n' => 'check'])</div><p>Total pagado</p><strong>{{ Moneda::f($ob->monto_total + $ob->interes_moratorio, $mon) }}</strong><small>{{ number_format($ob->monto_total, 2) }} + mora {{ number_format($ob->interes_moratorio, 2) }} ({{ $ob->dias_mora }} d) · {{ \Carbon\Carbon::parse($ob->fecha_pago_real)->format('d/m/Y') }}</small></div>
            @else
                <div class="stat"><div class="ico navy">@include('partials.icono', ['n' => 'coins'])</div><p>Total a pagar</p><strong>{{ Moneda::f($compra->cuota, $mon) }}</strong><small>vence {{ \Carbon\Carbon::parse($compra->fecha_primer_pago)->format('d/m/Y') }} · sin mora</small></div>
            @endif
        @else
            @if($esAntigua)
                <div class="stat"><div class="ico navy">@include('partials.icono', ['n' => 'clock'])</div><p>Gracia total</p><strong>{{ $compra->dias_gracia }} días</strong><small>interés capitalizado {{ Moneda::f($compra->interes_gracia, $mon) }}</small></div>
            @else
                <div class="stat"><div class="ico navy">@include('partials.icono', ['n' => 'clock'])</div><p>Período de gracia</p><strong>{{ $g > 0 ? $q($g) : 'Sin gracia' }}</strong><small>{{ $g > 0 ? 'interés capitalizado ' . Moneda::f($compra->interes_gracia, $mon) : 'paga desde la primera quincena' }}</small></div>
            @endif
            <div class="stat"><div class="ico">@include('partials.icono', ['n' => 'chart'])</div><p>Cuota quincenal</p><strong>{{ Moneda::f($compra->cuota, $mon) }}</strong><small>TEQ {{ number_format($teq * 100, 7) }}%</small></div>
            <div class="stat"><div class="ico navy">@include('partials.icono', ['n' => 'check'])</div><p>Avance</p><strong>{{ $pagadas }} / {{ $total }}</strong><small>cuotas pagadas</small></div>
        @endif
    </section>

    <div class="card">
        <div class="card-head">
            <h2>{{ $esUnico ? 'Obligación de pago' : 'Cuadro de amortización (método francés vencido)' }}</h2>
            <span class="muted small">TEA {{ number_format($compra->tea_aplicada, 7) }}% · TEA moratoria {{ number_format($compra->tea_mora_aplicada, 7) }}%</span>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>N.°</th><th>Vencimiento</th><th>Gracia</th><th class="right">Saldo inicial</th><th class="right">Interés</th>
                        <th class="right">Cuota</th><th class="right">Amortización</th><th class="right">Saldo final</th><th class="right">Mora</th><th>Estado</th>@if(! $soloLectura)<th></th>@endif
                    </tr>
                </thead>
                <tbody>
                @foreach($filasGracia as $f)
                    <tr class="gracia">
                        <td>{{ $f['n'] }}</td>
                        <td class="num">{{ $f['fecha']->format('d/m/Y') }}</td>
                        <td><span class="chip">T</span></td>
                        <td class="right num">{{ number_format($f['saldo_inicial'], 2) }}</td>
                        <td class="right num">{{ number_format($f['interes'], 2) }}</td>
                        <td class="right num">0.00</td>
                        <td class="right num">0.00</td>
                        <td class="right num">{{ number_format($f['saldo_final'], 2) }}</td>
                        <td class="right num">—</td>
                        <td colspan="{{ $soloLectura ? 1 : 2 }}"><span class="badge gray">Gracia: no paga</span></td>
                    </tr>
                @endforeach
                @if($esAntigua)
                    <tr class="gracia">
                        <td>0</td>
                        <td class="num">{{ \Carbon\Carbon::parse($compra->fecha_primer_pago)->format('d/m/Y') }}</td>
                        <td><span class="chip">T</span></td>
                        <td class="right num">{{ number_format($compra->monto, 2) }}</td>
                        <td class="right num">{{ number_format($compra->interes_gracia, 2) }}</td>
                        <td class="right num">0.00</td>
                        <td class="right num">0.00</td>
                        <td class="right num">{{ number_format($compra->capital_financiado, 2) }}</td>
                        <td class="right num">—</td>
                        <td colspan="{{ $soloLectura ? 1 : 2 }}"><span class="muted small">Gracia total: i = {{ number_format($tasa_gracia * 100, 7) }}%</span></td>
                    </tr>
                @endif
                @php($saldo = $esUnico ? (float) $compra->monto : (float) $compra->capital_financiado)
                @foreach($obligaciones as $o)
                    @php($venc = \Carbon\Carbon::parse($o->fecha_vencimiento))
                    @php($vencida = $o->estado === 'pendiente' && $venc->lt($hoy))
                    <tr class="{{ $vencida ? 'vencida' : '' }}">
                        <td>{{ $o->numero_cuota }}</td>
                        <td class="num">{{ $venc->format('d/m/Y') }}</td>
                        <td><span class="chip">{{ $esUnico ? '—' : 'S' }}</span></td>
                        <td class="right num">{{ number_format($saldo, 2) }}</td>
                        <td class="right num">{{ number_format($o->interes_compensatorio, 2) }}</td>
                        <td class="right num">{{ number_format($o->monto_total, 2) }}</td>
                        <td class="right num">{{ number_format($o->capital, 2) }}</td>
                        @php($saldo = round($saldo - $o->capital, 2))
                        <td class="right num">{{ number_format($esUnico ? 0 : $saldo, 2) }}</td>
                        <td class="right num">{{ $o->estado === 'pagado' ? number_format($o->interes_moratorio, 2) : '—' }}</td>
                        <td>
                            @if($o->estado === 'pagado')
                                <span class="badge ok">Pagada {{ $o->fecha_pago_real ? \Carbon\Carbon::parse($o->fecha_pago_real)->format('d/m') : '' }}</span>
                                @if($o->interes_moratorio > 0)<div class="cell-sub">{{ $o->dias_mora }} días de atraso</div>@endif
                            @elseif($vencida)
                                <span class="badge bad">Vencida</span>
                            @else
                                <span class="badge gray">Pendiente</span>
                            @endif
                        </td>
                        @if(! $soloLectura)
                            <td class="right">
                                @if($o->estado === 'pendiente' && $siguiente && $siguiente->obligacion_id === $o->obligacion_id)
                                    <a class="btn sm" href="/pagos/{{ $o->obligacion_id }}/nuevo">Cobrar</a>
                                @endif
                            </td>
                        @endif
                    </tr>
                @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="4">Totales</td>
                        <td class="right num">{{ number_format($obligaciones->sum('interes_compensatorio') + ($esUnico ? 0 : $compra->interes_gracia), 2) }}</td>
                        <td class="right num">{{ number_format($obligaciones->sum('monto_total'), 2) }}</td>
                        <td class="right num">{{ number_format($obligaciones->sum('capital'), 2) }}</td>
                        <td></td>
                        <td class="right num">{{ number_format($obligaciones->sum('interes_moratorio'), 2) }}</td>
                        <td colspan="{{ $soloLectura ? 1 : 2 }}">@if($obligaciones->where('estado', 'pagado')->count())<span class="small muted">Pagado: {{ number_format($obligaciones->where('estado', 'pagado')->sum(fn ($x) => $x->monto_total + $x->interes_moratorio), 2) }}</span>@endif</td>
                    </tr>
                </tfoot>
            </table>
        </div>
        <p class="muted small" style="margin:14px 0 0">
            @if($esUnico)
                Interés ordinario de una quincena (TEQ): el pago único vence 15 días después de la compra. Si se paga después, se agrega interés moratorio por los días de atraso.
            @else
                @if(! $esAntigua)
                    Cada período es una quincena (15 días comerciales, base 30/360) contada desde la fecha de compra, con la misma TEQ.
                    T = quincena de gracia total (no se paga; el interés se suma al saldo). S = período con cuota. Todas las cuotas son iguales: los céntimos de redondeo los absorbe el interés de la última cuota, que deja el saldo en 0.00.
                @else
                    T = período de gracia total (el interés se capitaliza y no se paga). S = período con cuota. Todas las cuotas son iguales: los céntimos de redondeo los absorbe el interés de la última cuota, que deja el saldo en 0.00.
                @endif
            @endif
        </p>
    </div>
@endsection
