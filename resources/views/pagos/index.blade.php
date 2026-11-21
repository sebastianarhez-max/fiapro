@extends('layouts.panel')
@section('titulo', 'Cobros y pagos | FiaPro')
@use('App\Support\Moneda')
@section('contenido')
    <div class="page-head">
        <div>
            <h1>Cobros y pagos</h1>
            <p>Cuotas pendientes de tus clientes. Elige una para registrar su pago; la mora se calcula sola.</p>
        </div>
    </div>

    <div class="card">
        <form method="GET" class="filters" style="margin-bottom:16px">
            <div>
                <label for="cliente">Cliente</label>
                <select id="cliente" name="cliente">
                    <option value="">Todos</option>
                    @foreach($clientes as $c)
                        <option value="{{ $c->cliente_id }}" @selected(request('cliente') === $c->cliente_id)>{{ $c->nombre_completo }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="vista">Mostrar</label>
                <select id="vista" name="vista">
                    <option value="">Todas las pendientes</option>
                    <option value="vencidas" @selected(request('vista') === 'vencidas')>Solo vencidas</option>
                </select>
            </div>
            <div style="min-width:auto"><button class="btn ghost" type="submit">Filtrar</button></div>
        </form>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Vencimiento</th><th>Cliente</th><th>Producto</th><th>Detalle</th><th class="right">Capital</th><th class="right">Interés</th><th class="right">Total</th><th>Estado</th><th></th></tr></thead>
                <tbody>
                @forelse($pendientes as $o)
                    @php($venc = \Carbon\Carbon::parse($o->fecha_vencimiento))
                    @php($vencida = $venc->lt($hoy))
                    <tr class="{{ $vencida ? 'vencida' : '' }}">
                        <td class="num">{{ $venc->format('d/m/Y') }}</td>
                        <td class="cell-main">{{ $o->nombre_completo }}</td>
                        <td><div class="con-foto">@include('partials.foto', ['url' => $o->imagen_url, 'alt' => $o->descripcion])<span>{{ $o->descripcion }}</span></div></td>
                        <td>{{ $o->modalidad_pago === 'UNICO' ? 'Pago único' : 'Cuota ' . $o->numero_cuota . ' de ' . ($o->numero_meses * 2) }}</td>
                        <td class="right num">{{ number_format($o->capital, 2) }}</td>
                        <td class="right num">{{ number_format($o->interes_compensatorio, 2) }}</td>
                        <td class="right num cell-main">{{ Moneda::f($o->saldo, $o->moneda) }}</td>
                        <td>
                            @if($vencida)
                                <span class="badge bad">Vencida · {{ (int) $venc->diffInDays($hoy) }} d</span>
                            @elseif((int) $hoy->diffInDays($venc) <= 3)
                                <span class="badge warn">Por vencer</span>
                            @else
                                <span class="badge gray">Pendiente</span>
                            @endif
                        </td>
                        <td class="right"><a class="btn sm" href="/pagos/{{ $o->obligacion_id }}/nuevo">Cobrar</a></td>
                    </tr>
                @empty
                    <tr><td colspan="9"><div class="empty">@include('partials.icono', ['n' => 'check'])No hay cuotas pendientes.</div></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <div class="card-head"><h2>Últimos pagos registrados</h2></div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Fecha</th><th>Cliente</th><th>Detalle</th><th>Medio</th><th class="right">Mora</th><th class="right">Interés</th><th class="right">Capital</th><th class="right">Total</th></tr></thead>
                <tbody>
                @forelse($pagos as $p)
                    <tr>
                        <td class="num">{{ \Carbon\Carbon::parse($p->fecha_real_pago)->format('d/m/Y') }}</td>
                        <td class="cell-main">{{ $p->nombre_completo }}</td>
                        <td>{{ $p->modalidad_pago === 'UNICO' ? 'Pago único' : 'Cuota ' . $p->numero_cuota }}</td>
                        <td>{{ $p->medio_pago }}</td>
                        <td class="right num">{{ number_format($p->monto_interes_mora, 2) }}</td>
                        <td class="right num">{{ number_format($p->monto_interes_comp, 2) }}</td>
                        <td class="right num">{{ number_format($p->monto_capital, 2) }}</td>
                        <td class="right num cell-main">{{ Moneda::f($p->monto_pagado, $p->moneda) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="8"><div class="empty">@include('partials.icono', ['n' => 'wallet'])Aún no hay pagos registrados.</div></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
