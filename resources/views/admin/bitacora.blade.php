@extends('layouts.panel')
@section('titulo', 'Bitácora | FiaPro')
@section('contenido')
    <div class="page-head">
        <div>
            <h1>Bitácora de operaciones</h1>
            <p>Registro de todas las operaciones realizadas en la plataforma: quién, qué, cuándo y desde dónde.</p>
        </div>
    </div>

    <div class="card">
        <form method="GET" class="filters" style="margin-bottom:16px">
            <div>
                <label for="tienda">Tienda</label>
                <select id="tienda" name="tienda">
                    <option value="">Todas</option>
                    @foreach($tiendas as $t)
                        <option value="{{ $t->tienda_id }}" @selected(request('tienda') === $t->tienda_id)>{{ $t->nombre_razon_social }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="accion">Acción</label>
                <select id="accion" name="accion">
                    <option value="">Todas</option>
                    @foreach($acciones as $a)
                        <option value="{{ $a }}" @selected(request('accion') === $a)>{{ str_replace('_', ' ', $a) }}</option>
                    @endforeach
                </select>
            </div>
            <div style="min-width:auto"><button class="btn ghost" type="submit">Filtrar</button></div>
        </form>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Fecha y hora</th><th>Usuario</th><th>Tienda</th><th>Acción</th><th>Entidad</th><th>Detalle</th><th>IP</th></tr></thead>
                <tbody>
                @forelse($registros as $r)
                    <tr>
                        <td class="num">{{ \Carbon\Carbon::parse($r->creado_en)->format('d/m/Y H:i:s') }}</td>
                        <td><div class="cell-main">{{ $r->name ?? '—' }}</div><div class="cell-sub">{{ $r->email }}</div></td>
                        <td>{{ $r->nombre_razon_social ?? '—' }}</td>
                        <td><span class="badge {{ str_starts_with($r->accion, 'baja') || $r->accion === 'login_fallido' ? 'bad' : (str_starts_with($r->accion, 'alta') || $r->accion === 'pago' ? 'ok' : 'gray') }}">{{ str_replace('_', ' ', $r->accion) }}</span></td>
                        <td>{{ $r->entidad }}</td>
                        <td style="white-space:normal;max-width:340px" class="cell-sub">
                            @if($r->detalle)
                                @foreach(json_decode($r->detalle, true) ?? [] as $k => $v)
                                    <span class="chip" style="margin:1px 2px 1px 0">{{ $k }}: {{ is_scalar($v) ? $v : json_encode($v) }}</span>
                                @endforeach
                            @endif
                        </td>
                        <td class="cell-sub">{{ $r->ip }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7"><div class="empty">@include('partials.icono', ['n' => 'log'])Sin registros.</div></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @include('partials.paginacion', ['p' => $registros])
    </div>
@endsection
