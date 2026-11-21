@extends('layouts.panel')
@section('titulo', 'Mi cuenta | FiaPro')
@use('App\Support\Moneda')
@section('contenido')
    <div class="page-head">
        <div>
            <h1>Mi cuenta</h1>
            <p>{{ $tienda->nombre_razon_social ?? 'Tienda' }} · {{ $cliente->nombre_completo ?? auth()->user()->name }}</p>
        </div>
        @if($cliente)
            <div class="actions">
                <a class="btn ghost" href="/mi-cuenta/catalogo">@include('partials.icono', ['n' => 'box']) Ver catálogo</a>
                <a class="btn" href="/mi-cuenta/listado">@include('partials.icono', ['n' => 'list']) Ver mi listado de pago</a>
            </div>
        @endif
    </div>

    @if(! $cliente)
        <div class="alert warn">@include('partials.icono', ['n' => 'alert'])<div>Tu usuario todavía no está vinculado a un cliente de la tienda.</div></div>
    @else
        @php($mon = $linea['moneda'])
        @php($pct = $linea['limite'] > 0 ? min(100, $linea['utilizado'] / $linea['limite'] * 100) : 0)
        <section class="stats">
            <div class="stat">
                <div class="ico navy">@include('partials.icono', ['n' => 'shield'])</div>
                <p>Límite de crédito</p><strong>{{ Moneda::f($linea['limite'], $mon) }}</strong>
                <div class="meter {{ $pct > 85 ? 'alto' : '' }}"><span style="width:{{ $pct }}%"></span></div>
                <small>{{ number_format($pct, 0) }}% utilizado</small>
            </div>
            <div class="stat"><div class="ico">@include('partials.icono', ['n' => 'coins'])</div><p>Crédito disponible</p><strong>{{ Moneda::f($linea['disponible'], $mon) }}</strong><small>usado {{ Moneda::f($linea['utilizado'], $mon) }}</small></div>
            <div class="stat"><div class="ico navy">@include('partials.icono', ['n' => 'wallet'])</div><p>Deuda a hoy</p><strong>{{ Moneda::f($pendientes->sum('total_hoy'), $mon) }}</strong><small>{{ $pendientes->count() }} {{ $pendientes->count() === 1 ? 'cuota pendiente' : 'cuotas pendientes' }}</small></div>
            <div class="stat"><div class="ico {{ $pendientes->sum('mora_hoy') > 0 ? 'bad' : 'navy' }}">@include('partials.icono', ['n' => 'clock'])</div><p>Mora acumulada</p><strong>{{ Moneda::f($pendientes->sum('mora_hoy'), $mon) }}</strong><small>si pagas hoy</small></div>
        </section>

        <div class="grid-3-1" style="margin-top:18px">
            <div class="card">
                <div class="card-head"><h2>Mis próximas cuotas</h2></div>
                <div class="table-wrap">
                    <table>
                        <thead><tr><th>Vence</th><th>Producto</th><th>Detalle</th><th class="right">Monto</th><th class="right">Mora hoy</th><th class="right">Total hoy</th></tr></thead>
                        <tbody>
                        @forelse($pendientes as $o)
                            <tr class="{{ $o->dias_mora_hoy > 0 ? 'vencida' : '' }}">
                                <td class="num">{{ \Carbon\Carbon::parse($o->fecha_vencimiento)->format('d/m/Y') }}
                                    @if($o->dias_mora_hoy > 0)<span class="badge bad">{{ $o->dias_mora_hoy }} d</span>@endif</td>
                                <td><div class="con-foto">@include('partials.foto', ['url' => $o->imagen_url, 'alt' => $o->descripcion])<span class="cell-main">{{ $o->descripcion }}</span></div></td>
                                <td>{{ $o->modalidad_pago === 'UNICO' ? 'Pago único' : 'Cuota ' . $o->numero_cuota . ' de ' . ($o->numero_meses * 2) }}</td>
                                <td class="right num">{{ number_format($o->saldo, 2) }}</td>
                                <td class="right num">{{ number_format($o->mora_hoy, 2) }}</td>
                                <td class="right num cell-main">{{ Moneda::f($o->total_hoy, $o->moneda) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6"><div class="empty">@include('partials.icono', ['n' => 'check'])No tienes cuotas pendientes. ¡Estás al día!</div></td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card">
                <h2>Mis condiciones</h2>
                @if($cfg)
                    <dl class="kv">
                        <dt>Moneda</dt><dd>{{ $cfg->moneda }}</dd>
                        <dt>TEA ordinaria</dt><dd>{{ number_format($cfg->tasa_comp, 2) }}%</dd>
                        <dt>TEA moratoria</dt><dd>{{ number_format($cfg->tasa_mora, 2) }}%</dd>
                        <dt>Plazo máximo</dt><dd>{{ $cfg->max_meses }} meses</dd>
                        <dt>Pago de cuotas</dt><dd>cada 15 días desde la compra</dd>
                    </dl>
                    <p class="muted small" style="margin:14px 0 0">Todo se paga por quincenas: el pago único y cada cuota vencen 15 días después (contando desde la compra).</p>
                @endif
            </div>
        </div>

        <div class="card">
            <div class="card-head"><h2>Mis compras</h2></div>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Fecha</th><th>Producto</th><th>Modalidad</th><th class="right">Monto</th><th>Estado</th><th></th></tr></thead>
                    <tbody>
                    @forelse($compras as $c)
                        <tr>
                            <td class="num">{{ \Carbon\Carbon::parse($c->fecha_compra)->format('d/m/Y H:i') }}</td>
                            <td><div class="con-foto">@include('partials.foto', ['url' => $c->imagen_url, 'tam' => 'md', 'alt' => $c->descripcion])<span class="cell-main">{{ $c->descripcion }}</span></div></td>
                            <td><span class="chip">{{ $c->modalidad_pago === 'UNICO' ? 'Pago único' : $c->numero_meses . ' meses' }}</span></td>
                            <td class="right num">{{ Moneda::f($c->monto, $c->moneda) }}</td>
                            <td>@if($c->estado === 'cancelada')<span class="badge ok">Cancelada</span>@else<span class="badge info">Con saldo</span>@endif</td>
                            <td class="right"><a class="btn ghost sm" href="/mi-cuenta/compras/{{ $c->compra_id }}">Ver plan</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><div class="empty">@include('partials.icono', ['n' => 'cart'])Aún no tienes compras a crédito.</div></td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card">
            <div class="card-head"><h2>Mis pagos</h2></div>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Fecha</th><th>Producto</th><th>Detalle</th><th>Medio</th><th class="right">Mora</th><th class="right">Total</th></tr></thead>
                    <tbody>
                    @forelse($pagos as $p)
                        <tr>
                            <td class="num">{{ \Carbon\Carbon::parse($p->fecha_real_pago)->format('d/m/Y') }}</td>
                            <td><div class="con-foto">@include('partials.foto', ['url' => $p->imagen_url, 'alt' => $p->descripcion])<span class="cell-main">{{ $p->descripcion }}</span></div></td>
                            <td>{{ $p->modalidad_pago === 'UNICO' ? 'Pago único' : 'Cuota ' . $p->numero_cuota }}</td>
                            <td>{{ $p->medio_pago }}</td>
                            <td class="right num">{{ number_format($p->monto_interes_mora, 2) }}</td>
                            <td class="right num cell-main">{{ Moneda::f($p->monto_pagado, $p->moneda) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><div class="empty">@include('partials.icono', ['n' => 'wallet'])Aún no registras pagos.</div></td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif
@endsection
