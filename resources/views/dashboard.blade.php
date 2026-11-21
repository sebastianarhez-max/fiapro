@extends('layouts.panel')
@section('titulo', 'Resumen | FiaPro')
@use('App\Support\Moneda')
@section('contenido')
    <div class="page-head">
        <div>
            <h1>Hola, {{ explode(' ', auth()->user()->name)[0] }}</h1>
            <p>Así va el crédito de tu tienda al {{ $hoy->format('d/m/Y') }}.</p>
        </div>
        <div class="actions">
            <a class="btn ghost" href="/pagos">@include('partials.icono', ['n' => 'wallet']) Registrar pago</a>
            <a class="btn" href="/compras/nueva">@include('partials.icono', ['n' => 'plus']) Nueva compra</a>
        </div>
    </div>

    <section class="stats">
        <div class="stat">
            <div class="ico">@include('partials.icono', ['n' => 'users'])</div>
            <p>Clientes activos</p><strong>{{ $stats['clientes'] }}</strong>
            <small>{{ $stats['productos'] }} productos en catálogo</small>
        </div>
        <div class="stat">
            <div class="ico navy">@include('partials.icono', ['n' => 'cart'])</div>
            <p>Créditos activos</p><strong>{{ $stats['creditos'] }}</strong>
            <small>compras con saldo pendiente</small>
        </div>
        <div class="stat">
            <div class="ico">@include('partials.icono', ['n' => 'coins'])</div>
            <p>Por cobrar</p><strong>{{ Moneda::f($stats['por_cobrar']) }}</strong>
            <small>capital + interés ordinario</small>
        </div>
        <div class="stat">
            <div class="ico {{ $stats['vencidas'] ? 'bad' : 'navy' }}">@include('partials.icono', ['n' => 'alert'])</div>
            <p>Cuotas vencidas</p><strong>{{ $stats['vencidas'] }}</strong>
            <small>{{ Moneda::f($stats['monto_vencido']) }} sin mora</small>
        </div>
    </section>

    <div class="grid-3-1" style="margin-top:18px">
        <div class="card">
            <div class="card-head">
                <h2>Vencimientos de los próximos 15 días</h2>
                <a class="btn ghost sm" href="/pagos">Ver todos</a>
            </div>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Cliente</th><th>Detalle</th><th>Vence</th><th class="right">Monto</th><th></th></tr></thead>
                    <tbody>
                    @forelse($proximas as $o)
                        @php($vencida = \Carbon\Carbon::parse($o->fecha_vencimiento)->lt($hoy))
                        <tr class="{{ $vencida ? 'vencida' : '' }}">
                            <td><div class="con-foto">@include('partials.foto', ['url' => $o->imagen_url, 'alt' => $o->descripcion])<div><div class="cell-main">{{ $o->nombre_completo }}</div><div class="cell-sub">{{ $o->descripcion }}</div></div></div></td>
                            <td>{{ $o->modalidad_pago === 'UNICO' ? 'Pago único' : 'Cuota ' . $o->numero_cuota . ' de ' . ($o->numero_meses * 2) }}</td>
                            <td>
                                {{ \Carbon\Carbon::parse($o->fecha_vencimiento)->format('d/m/Y') }}
                                @if($vencida)<span class="badge bad">Vencida</span>@endif
                            </td>
                            <td class="right num">{{ Moneda::f($o->saldo, $o->moneda) }}</td>
                            <td class="right"><a class="btn sm" href="/pagos/{{ $o->obligacion_id }}/nuevo">Cobrar</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="5"><div class="empty">@include('partials.icono', ['n' => 'check'])No hay vencimientos en los próximos 15 días.</div></td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card">
            <div class="card-head"><h2>Actividad reciente</h2></div>
            <ul class="timeline">
                @forelse($actividad as $a)
                    <li>
                        <span class="dot"></span>
                        <div>
                            <div class="cell-main" style="font-size:13px">{{ \Illuminate\Support\Str::of($a->accion)->replace('_', ' ')->ucfirst() }} · {{ $a->entidad }}</div>
                            <div class="cell-sub">{{ $a->name ?? 'Sistema' }} · {{ \Carbon\Carbon::parse($a->creado_en)->format('d/m H:i') }}</div>
                        </div>
                    </li>
                @empty
                    <li class="muted">Aún no hay operaciones registradas.</li>
                @endforelse
            </ul>
        </div>
    </div>
@endsection
