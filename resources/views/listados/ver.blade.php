@extends('layouts.panel')
@section('titulo', 'Listado de pago | FiaPro')
@use('App\Support\Moneda')
@php
    $l = $listado;
    $mon = $l['moneda'];
    $base = $esTienda ? '/listados/' . $cliente->cliente_id : '/mi-cuenta/listado';
@endphp
@section('contenido')
    <div class="page-head">
        <div>
            <div class="crumbs">
                @if($esTienda)<a href="/listados">Listados de pago</a>@else<a href="/mi-cuenta">Mi cuenta</a>@endif / {{ $cliente->nombre_completo }}
            </div>
            <h1>Listado de pago</h1>
            <p>Estado de cuenta al {{ $l['fecha']->format('d/m/Y') }}: lo vencido (con mora a esa fecha) y lo que vence hasta el <strong>{{ $l['hasta']->format('d/m/Y') }}</strong> (próxima quincena)</p>
        </div>
        <div class="actions">
            <form method="GET" action="{{ $base }}" class="actions no-print">
                <input type="date" name="fecha" value="{{ $fecha }}" style="width:auto" title="Fecha del listado">
                <button class="btn ghost" type="submit">Ver</button>
            </form>
            <button class="btn ghost" type="button" onclick="window.print()">@include('partials.icono', ['n' => 'print']) Imprimir</button>
            @if($esTienda)
                <form method="POST" action="{{ $base }}/guardar?fecha={{ $fecha }}" class="no-print">@csrf
                    <button class="btn" type="submit">@include('partials.icono', ['n' => 'save']) Registrar listado</button>
                </form>
            @endif
        </div>
    </div>

    <section class="stats">
        <div class="stat"><div class="ico">@include('partials.icono', ['n' => 'coins'])</div><p>Capital</p><strong>{{ Moneda::f($l['totales']['capital'], $mon) }}</strong></div>
        <div class="stat"><div class="ico navy">@include('partials.icono', ['n' => 'chart'])</div><p>Interés ordinario</p><strong>{{ Moneda::f($l['totales']['interes'], $mon) }}</strong><small>TEA {{ number_format($l['tea'], 2) }}%</small></div>
        <div class="stat"><div class="ico {{ $l['totales']['mora'] > 0 ? 'bad' : 'navy' }}">@include('partials.icono', ['n' => 'clock'])</div><p>Interés por mora</p><strong>{{ Moneda::f($l['totales']['mora'], $mon) }}</strong><small>TEA moratoria {{ number_format($l['tea_mora'], 2) }}%</small></div>
        <div class="stat" style="background:var(--teal-500);border-color:var(--teal-500)"><div class="ico" style="background:rgba(255,255,255,.18);color:#fff">@include('partials.icono', ['n' => 'wallet'])</div><p style="color:rgba(255,255,255,.85)">Total a pagar</p><strong style="color:#fff">{{ Moneda::f($l['totales']['total'], $mon) }}</strong></div>
    </section>

    <div class="card">
        <div class="card-head">
            <h2>Compras ordenadas por fecha</h2>
            <span class="muted small">Incluye lo vencido y lo que vence hasta el {{ $l['hasta']->format('d/m/Y') }}.</span>
        </div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Fecha de compra</th><th>Producto</th><th>Detalle</th><th>Vence</th><th class="right">Capital</th><th class="right">Interés</th><th class="right">Mora</th><th class="right">Total</th></tr></thead>
                <tbody>
                @forelse($l['items'] as $i)
                    <tr class="{{ $i['vencida'] ? 'vencida' : '' }}">
                        <td class="num">{{ $i['fecha_compra'] }}</td>
                        <td><div class="con-foto">@include('partials.foto', ['url' => $i['imagen'] ?? null, 'alt' => $i['descripcion']])<span class="cell-main">{{ $i['descripcion'] }}</span></div></td>
                        <td>{{ $i['detalle'] }}</td>
                        <td class="num">{{ $i['vencimiento'] }} @if($i['vencida'])<span class="badge bad">{{ $i['dias_mora'] }} d mora</span>@endif</td>
                        <td class="right num">{{ number_format($i['capital'], 2) }}</td>
                        <td class="right num">{{ number_format($i['interes'], 2) }}</td>
                        <td class="right num">{{ number_format($i['mora'], 2) }}</td>
                        <td class="right num cell-main">{{ number_format($i['total'], 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="8"><div class="empty">@include('partials.icono', ['n' => 'check'])No hay montos por pagar en esta quincena.</div></td></tr>
                @endforelse
                </tbody>
                @if(count($l['items']))
                    <tfoot>
                        <tr>
                            <td colspan="4">Total de la quincena (cada cuota en su fecha de vencimiento)</td>
                            <td class="right num">{{ number_format($l['totales']['capital'], 2) }}</td>
                            <td class="right num">{{ number_format($l['totales']['interes'], 2) }}</td>
                            <td class="right num">{{ number_format($l['totales']['mora'], 2) }}</td>
                            <td class="right num">{{ number_format($l['totales']['total'], 2) }}</td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
        <p class="muted small" style="margin:14px 0 0">
            Los intereses ordinarios se calculan con la TEA convertida a la tasa quincenal (TEQ, base 30/360); cada cuota vence 15 días después de la anterior, contando desde la compra.
            Si el pago se realiza después de la fecha pactada, se agrega un ítem de interés por mora con la TEA moratoria por los días de atraso.
        </p>
    </div>
@endsection
