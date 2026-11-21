@extends('layouts.panel')
@section('titulo', 'Registrar pago | FiaPro')
@use('App\Support\Moneda')
@php
    $mon = $compra->moneda ?? 'PEN';
    $venc = \Carbon\Carbon::parse($o->fecha_vencimiento);
@endphp
@section('contenido')
    <div class="page-head">
        <div>
            <div class="crumbs"><a href="/pagos">Cobros y pagos</a> / Registrar pago</div>
            <h1>Registrar pago</h1>
            <p>{{ $cliente->nombre_completo }} · {{ $producto }} ·
                {{ $compra->modalidad_pago === 'UNICO' ? 'Pago único' : 'Cuota ' . $o->numero_cuota . ' de ' . ($compra->numero_meses * 2) }}
                · vence {{ $venc->format('d/m/Y') }}</p>
        </div>
        <a class="btn ghost" href="/compras/{{ $compra->compra_id }}">Ver plan de pagos</a>
    </div>

    <div class="grid-3-1">
        <div class="card">
            <form method="POST" action="/pagos/{{ $o->obligacion_id }}">
                @csrf
                <div class="form-grid">
                    <div>
                        <label for="fecha_real_pago">Fecha real de pago @include('partials.ayuda', ['t' => 'Día en que el cliente paga. Si es posterior al vencimiento, se calcula el interés moratorio por los días de atraso (30/360)'])</label>
                        <input id="fecha_real_pago" name="fecha_real_pago" type="date" value="{{ old('fecha_real_pago', $fecha->toDateString()) }}" required>
                        <div class="hint">Al cambiar la fecha se recalcula el total.</div>
                    </div>
                    <div>
                        <label for="monto_pagado">Monto pagado @include('partials.ayuda', ['t' => 'No se aceptan pagos parciales: debe ser exactamente el total exigible a la fecha de pago'])</label>
                        <input id="monto_pagado" name="monto_pagado" type="number" step="0.01" value="{{ old('monto_pagado', number_format($exigible['total'], 2, '.', '')) }}" required readonly>
                        <div class="hint">Igual al total exigible (sin pagos parciales).</div>
                    </div>
                    <div>
                        <label for="moneda">Moneda @include('partials.ayuda', ['t' => 'Debe coincidir con la moneda del crédito. No hay conversión automática'])</label>
                        <select id="moneda" name="moneda">
                            <option value="PEN" @selected($mon === 'PEN')>Soles (PEN)</option>
                            <option value="USD" @selected($mon === 'USD')>Dólares (USD)</option>
                        </select>
                    </div>
                    <div>
                        <label for="medio_pago">Medio de pago @include('partials.ayuda', ['t' => 'Cómo pagó el cliente'])</label>
                        <select id="medio_pago" name="medio_pago" required>
                            @foreach(['Efectivo', 'Yape / Plin', 'Transferencia', 'Tarjeta', 'Otro'] as $m)
                                <option value="{{ $m }}" @selected(old('medio_pago') === $m)>{{ $m }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="full">
                        <label for="referencia">Referencia u observación (opcional) @include('partials.ayuda', ['t' => 'Número de operación, comentario u otra referencia'])</label>
                        <input id="referencia" name="referencia" maxlength="200" value="{{ old('referencia') }}" placeholder="Ej.: operación Yape 123456">
                    </div>
                </div>
                <div class="actions" style="margin-top:20px">
                    <button class="btn" type="submit">@include('partials.icono', ['n' => 'check']) Confirmar pago de {{ Moneda::f($exigible['total'], $mon) }}</button>
                    <a class="btn ghost" href="/pagos">Cancelar</a>
                </div>
            </form>
        </div>

        <div class="card">
            <h2>Total exigible al {{ $fecha->format('d/m/Y') }}</h2>
            <div class="total-box">
                <p>Monto a pagar</p>
                <strong>{{ Moneda::f($exigible['total'], $mon) }}</strong>
            </div>
            @if($exigible['dias_mora'] > 0)
                <div class="alert warn" style="margin:14px 0 0">@include('partials.icono', ['n' => 'clock'])
                    <div>{{ $exigible['dias_mora'] }} días de atraso. Tasa moratoria del período: {{ number_format($exigible['tasa_mora_periodo'] * 100, 7) }}% sobre {{ Moneda::f($exigible['monto_vencido'], $mon) }}.</div>
                </div>
            @else
                <div class="alert ok" style="margin:14px 0 0">@include('partials.icono', ['n' => 'check'])<div>Pago dentro del plazo: sin mora.</div></div>
            @endif
            <h3 style="margin-top:18px">Imputación del pago (orden de prelación)</h3>
            <div class="prelacion">
                <div><small>1. Mora</small><strong class="num">{{ number_format($exigible['mora'], 2) }}</strong></div>
                <div><small>2. Interés</small><strong class="num">{{ number_format($exigible['interes'], 2) }}</strong></div>
                <div><small>3. Capital</small><strong class="num">{{ number_format($exigible['capital'], 2) }}</strong></div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    document.getElementById('fecha_real_pago').addEventListener('change', function () {
        window.location = '/pagos/{{ $o->obligacion_id }}/nuevo?fecha=' + this.value;
    });
</script>
@endpush
