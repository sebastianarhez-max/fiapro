@extends('layouts.panel')
@section('titulo', 'Compras | FiaPro')
@use('App\Support\Moneda')
@section('contenido')
    <div class="page-head">
        <div>
            <h1>Compras a crédito</h1>
            <p>Todas las ventas al fiado de tu tienda, con su plan de pagos.</p>
        </div>
        <a class="btn" href="/compras/nueva">@include('partials.icono', ['n' => 'plus']) Nueva compra</a>
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
                <label for="estado">Estado</label>
                <select id="estado" name="estado">
                    <option value="">Todos</option>
                    <option value="activa" @selected(request('estado') === 'activa')>Con saldo</option>
                    <option value="cancelada" @selected(request('estado') === 'cancelada')>Cancelada</option>
                </select>
            </div>
            <div style="min-width:auto"><button class="btn ghost" type="submit">Filtrar</button></div>
        </form>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr><th>Fecha y hora</th><th>Cliente</th><th>Producto</th><th>Modalidad</th><th class="right">Monto</th><th class="right">Cuota / total</th><th>Estado</th><th></th></tr>
                </thead>
                <tbody>
                @forelse($compras as $c)
                    <tr>
                        <td class="num">{{ \Carbon\Carbon::parse($c->fecha_compra)->format('d/m/Y H:i') }}</td>
                        <td class="cell-main">{{ $c->nombre_completo }}</td>
                        <td><div class="con-foto">@include('partials.foto', ['url' => $c->imagen_url, 'alt' => $c->descripcion])<span>{{ $c->descripcion }} <span class="cell-sub">× {{ rtrim(rtrim(number_format($c->cantidad, 2), '0'), '.') }}</span></span></div></td>
                        <td>
                            @if($c->modalidad_pago === 'UNICO')
                                <span class="chip">Pago único</span>
                            @else
                                <span class="chip">{{ $c->numero_meses }} {{ $c->numero_meses == 1 ? 'mes' : 'meses' }} · {{ $c->numero_meses * 2 - (int) $c->periodos_gracia }} cuotas{{ (int) $c->periodos_gracia > 0 ? ' · ' . $c->periodos_gracia . ' gracia' : '' }}</span>
                            @endif
                        </td>
                        <td class="right num">{{ Moneda::f($c->monto ?? $c->cantidad * $c->precio_credito, $c->moneda ?? 'PEN') }}</td>
                        <td class="right num">{{ Moneda::f($c->cuota, $c->moneda ?? 'PEN') }}</td>
                        <td>
                            @if($c->estado === 'cancelada')
                                <span class="badge ok">Cancelada</span>
                            @else
                                <span class="badge info">Con saldo</span>
                            @endif
                        </td>
                        <td class="right"><a class="btn ghost sm" href="/compras/{{ $c->compra_id }}">Ver plan</a></td>
                    </tr>
                @empty
                    <tr><td colspan="8"><div class="empty">@include('partials.icono', ['n' => 'cart'])Aún no hay compras registradas.</div></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @include('partials.paginacion', ['p' => $compras])
    </div>
@endsection
