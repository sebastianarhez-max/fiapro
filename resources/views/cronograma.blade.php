@extends('layouts.panel')
@section('titulo', 'Simulador de cronograma | FiaPro')
@section('contenido')
    <div class="page-head">
        <div>
            <h1>Simulador de cronograma</h1>
            <p>Método francés vencido con cuotas quincenales: cada período dura 15 días comerciales (base 30/360) contados desde la fecha de compra y todos usan la misma TEQ. La gracia total va dentro del plazo. No registra ninguna compra.</p>
        </div>
        @if($resultado)
            <button class="btn ghost" type="button" onclick="window.print()">@include('partials.icono', ['n' => 'print']) Imprimir</button>
        @endif
    </div>

    <div class="card no-print">
        <form method="POST" action="/cronograma">
            @csrf
            <div class="form-grid cols-3">
                <div>
                    <label for="capital">Monto de la compra @include('partials.ayuda', ['t' => 'Precio a crédito total de la compra. Máximo ' . config('fiapro.limite_max')])</label>
                    <input id="capital" name="capital" type="number" step="0.01" value="{{ old('capital', $datos['capital']) }}" required>
                </div>
                <div>
                    <label for="tea">TEA pactada (%) @include('partials.ayuda', ['t' => 'Tasa efectiva anual pactada con el cliente. Máximo ' . config('fiapro.tea_max_ordinaria') . '%'])</label>
                    <input id="tea" name="tea" type="number" step="0.0000001" value="{{ old('tea', $datos['tea']) }}" required>
                </div>
                <div>
                    <label for="meses">Plazo en meses @include('partials.ayuda', ['t' => 'Cada mes equivale a 2 cuotas quincenales. Máximo ' . config('fiapro.meses_max') . ' meses'])</label>
                    <input id="meses" name="meses" type="number" min="1" max="{{ config('fiapro.meses_max') }}" value="{{ old('meses', $datos['meses']) }}" required>
                </div>
                <div>
                    <label for="fecha_hora_compra">Fecha de compra @include('partials.ayuda', ['t' => 'Inicio del período 1. Cada cuota vence 15 días comerciales después de la anterior'])</label>
                    <input id="fecha_hora_compra" name="fecha_hora_compra" type="datetime-local" value="{{ old('fecha_hora_compra', $datos['fecha_hora_compra']) }}" required>
                </div>
                <div>
                    <label for="gracia">Período de gracia @include('partials.ayuda', ['t' => 'Quincenas al inicio en que el cliente no paga (gracia total): el interés se suma al saldo. Van dentro del plazo, así que debe quedar al menos una cuota'])</label>
                    <select id="gracia" name="gracia">
                        @for($i = 0; $i <= \App\Services\CronogramaFrances::GRACIA_MAX; $i++)
                            <option value="{{ $i }}" @selected((int) old('gracia', $datos['gracia']) === $i)>{{ $i === 0 ? 'Sin período de gracia' : $i . ' ' . ($i === 1 ? 'quincena' : 'quincenas') . ' de gracia' }}</option>
                        @endfor
                    </select>
                </div>
                <div style="align-self:end"><button class="btn" type="submit" style="width:100%">@include('partials.icono', ['n' => 'calc']) Generar cronograma</button></div>
            </div>
        </form>
    </div>

    @if($resultado)
        @php
            $r = $resultado['resumen'];
        @endphp
        <section class="stats" style="margin-top:18px">
            <div class="stat"><div class="ico">@include('partials.icono', ['n' => 'cart'])</div><p>Capital</p><strong>S/ {{ number_format($r['capital'], 2) }}</strong><small>TEA {{ number_format($r['tea'] * 100, \App\Services\CronogramaFrances::PRECISION_SIMULADOR) }}%</small></div>
            <div class="stat"><div class="ico navy">@include('partials.icono', ['n' => 'clock'])</div><p>Período de gracia</p><strong>{{ $r['periodos_gracia'] ? $r['periodos_gracia'] . ' ' . ($r['periodos_gracia'] === 1 ? 'quincena' : 'quincenas') : 'Sin gracia' }}</strong><small>{{ $r['periodos_gracia'] ? 'interés capitalizado S/ ' . number_format($r['interes_gracia'], 2) . ' · saldo ' . number_format($r['capital_financiado'], 2) : 'paga desde la primera quincena' }}</small></div>
            <div class="stat"><div class="ico">@include('partials.icono', ['n' => 'chart'])</div><p>Cuota quincenal ({{ $r['n_cuotas'] }} de {{ $r['n_periodos'] }} períodos)</p><strong>S/ {{ number_format($r['cuota'], 2) }}</strong><small>TEQ {{ number_format($r['teq'] * 100, \App\Services\CronogramaFrances::PRECISION_SIMULADOR) }}% · 1.ª cuota {{ $r['fecha_primer_pago']->format('d/m/Y') }}</small></div>
            <div class="stat"><div class="ico navy">@include('partials.icono', ['n' => 'coins'])</div><p>Total a pagar</p><strong>S/ {{ number_format($r['total_a_pagar'], 2) }}</strong><small>intereses S/ {{ number_format($r['total_intereses'], 2) }}</small></div>
        </section>

        <div class="card">
            <div class="card-head"><h2>Cuadro de flujo de pagos</h2><span class="muted small">T = quincena de gracia total · S = período con cuota</span></div>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr><th>N.°</th><th>Fecha</th><th class="right">TEA</th><th class="right">TEQ</th><th>Gracia</th><th class="right">Saldo inicial</th><th class="right">Interés</th><th class="right">Cuota</th><th class="right">Amortización</th><th class="right">Saldo final</th></tr>
                    </thead>
                    <tbody>
                    @foreach($resultado['filas'] as $f)
                        <tr class="{{ $f['gracia'] === 'T' ? 'gracia' : '' }}">
                            <td>{{ $f['n'] }}</td>
                            <td class="num">{{ $f['fecha']->format('d/m/Y') }}</td>
                            <td class="right num">{{ number_format($r['tea'] * 100, \App\Services\CronogramaFrances::PRECISION_SIMULADOR) }}%</td>
                            <td class="right num">{{ number_format($r['teq'] * 100, \App\Services\CronogramaFrances::PRECISION_SIMULADOR) }}%</td>
                            <td><span class="chip">{{ $f['gracia'] }}</span></td>
                            <td class="right num">{{ number_format($f['saldo_inicial'], 2) }}</td>
                            <td class="right num">{{ number_format($f['interes'], 2) }}</td>
                            <td class="right num">{{ number_format($f['cuota'], 2) }}</td>
                            <td class="right num">{{ number_format($f['amortizacion'], 2) }}</td>
                            <td class="right num">{{ number_format($f['saldo_final'], 2) }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="6">Totales</td>
                            <td class="right num">{{ number_format($r['total_intereses'], 2) }}</td>
                            <td class="right num">{{ number_format($r['total_a_pagar'], 2) }}</td>
                            <td class="right num">{{ number_format($r['capital_financiado'], 2) }}</td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            @php
                $sumaCuotasMostradas = array_sum(array_map(
                    fn ($fila) => (int) round($fila['cuota'] * 100), $resultado['filas']
                ));
                $totalMostrado = (int) round($r['total_a_pagar'] * 100);
            @endphp
            @if($sumaCuotasMostradas !== $totalMostrado)
                <p class="muted small">Los cálculos usan siete decimales internos. La suma de las cuotas mostradas (S/ {{ number_format($sumaCuotasMostradas / 100, 2) }}) puede diferir del total por el redondeo de cada importe a dos decimales.</p>
            @endif
        </div>
    @endif
@endsection
