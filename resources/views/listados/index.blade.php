@extends('layouts.panel')
@section('titulo', 'Listados de pago | FiaPro')
@use('App\Support\Moneda')
@section('contenido')
    <div class="page-head">
        <div>
            <h1>Listados de pago</h1>
            <p>Estado de cuenta de cada cliente por quincena: lo vencido (con su mora) y lo que vence en los próximos 15 días.</p>
        </div>
    </div>

    <div class="alert info">@include('partials.icono', ['n' => 'clock'])
        <div>Los listados se generan automáticamente cada quincena, los días 15 y último de cada mes (tarea programada <code>fiapro:generar-listados</code>). También puedes consultarlos en cualquier fecha y registrarlos aquí.</div>
    </div>

    <div class="grid-2">
        <div class="card">
            <div class="card-head"><h2>Clientes</h2></div>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Cliente</th><th>Plazo máx.</th><th></th></tr></thead>
                    <tbody>
                    @forelse($clientes as $c)
                        <tr>
                            <td class="cell-main">{{ $c->nombre_completo }}</td>
                            <td>{{ $c->max_meses }} meses</td>
                            <td class="right"><a class="btn ghost sm" href="/listados/{{ $c->cliente_id }}">Ver listado</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="3"><div class="empty">@include('partials.icono', ['n' => 'users'])Aún no hay clientes con crédito.</div></td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card">
            <div class="card-head"><h2>Listados registrados</h2></div>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Cliente</th><th>Fecha del listado</th><th>Incluye hasta</th><th class="right">Total</th></tr></thead>
                    <tbody>
                    @forelse($generados as $g)
                        <tr>
                            <td><a class="cell-main" style="text-decoration:none" href="/listados/{{ $g->cliente_id }}?fecha={{ \Carbon\Carbon::parse($g->fecha_corte)->format('Y-m-d') }}">{{ $g->nombre_completo }}</a></td>
                            <td class="num">{{ \Carbon\Carbon::parse($g->fecha_corte)->format('d/m/Y') }}</td>
                            <td class="num">{{ \Carbon\Carbon::parse($g->fecha_pago)->format('d/m/Y') }}</td>
                            <td class="right num cell-main">{{ Moneda::f($g->total, $g->moneda) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4"><div class="empty">@include('partials.icono', ['n' => 'list'])Aún no se registró ningún listado.</div></td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
