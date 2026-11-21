@extends('layouts.panel')
@section('titulo', 'Clientes | FiaPro')
@use('App\Support\Moneda')
@section('contenido')
    <div class="page-head">
        <div>
            <h1>Clientes</h1>
            <p>Vecinos con crédito en tu tienda y las condiciones pactadas con cada uno.</p>
        </div>
        <a class="btn" href="/clientes/nuevo">@include('partials.icono', ['n' => 'plus']) Nuevo cliente</a>
    </div>

    <div class="card">
        <div class="table-wrap">
            <table>
                <thead>
                    <tr><th>Cliente</th><th>Acceso</th><th class="right">TEA</th><th class="right">TEA mora</th><th class="right">Límite</th><th>Plazo máx.</th><th>Estado</th><th></th></tr>
                </thead>
                <tbody>
                @forelse($clientes as $c)
                    <tr>
                        <td><div class="cell-main">{{ $c->nombre_completo }}</div><div class="cell-sub">{{ $c->documento_identidad }} · {{ $c->contacto }}</div></td>
                        <td>{{ $c->correo ?? 'Sin acceso' }}</td>
                        @if($c->tasa_comp !== null)
                            <td class="right num">{{ number_format($c->tasa_comp, 2) }}%</td>
                            <td class="right num">{{ number_format($c->tasa_mora, 2) }}%</td>
                            <td class="right num">{{ Moneda::f($c->limite_credito, $c->moneda) }}</td>
                            <td>{{ $c->max_meses }} meses</td>
                        @else
                            <td colspan="4" class="muted">Sin configuración de crédito</td>
                        @endif
                        <td>
                            @if($c->estado_acceso === null)
                                <span class="muted">—</span>
                            @else
                                <span class="badge {{ $c->estado_acceso === 'activo' ? 'ok' : 'bad' }}">{{ $c->estado_acceso === 'activo' ? 'Activo' : 'De baja' }}</span>
                            @endif
                        </td>
                        <td class="right">
                            <div class="actions" style="justify-content:flex-end">
                                @if($c->tasa_comp !== null)
                                    <a class="btn ghost sm" href="/listados/{{ $c->cliente_id }}">Listado</a>
                                @endif
                                @if($c->estado_acceso !== null)
                                    <form method="POST" action="/clientes/{{ $c->cliente_id }}/estado" onsubmit="return confirm('¿Confirmas el cambio de estado de este cliente?')">
                                        @csrf
                                        <button type="submit" class="btn sm {{ $c->estado_acceso === 'activo' ? 'danger' : 'success' }}">{{ $c->estado_acceso === 'activo' ? 'Dar de baja' : 'Reactivar' }}</button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="9"><div class="empty">@include('partials.icono', ['n' => 'users'])Aún no registraste clientes.</div></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
