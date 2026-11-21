@extends('layouts.panel')
@section('titulo', 'Tiendas | FiaPro')
@section('contenido')
    <div class="page-head">
        <div>
            <h1>Tiendas</h1>
            <p>Alta y baja de comercios de cualquier giro y creación del usuario administrador de cada uno.</p>
        </div>
    </div>

    <section class="stats" style="grid-template-columns:repeat(3,1fr)">
        <div class="stat"><div class="ico">@include('partials.icono', ['n' => 'store'])</div><p>Tiendas registradas</p><strong>{{ count($tiendas) }}</strong></div>
        <div class="stat"><div class="ico navy">@include('partials.icono', ['n' => 'check'])</div><p>Activas</p><strong>{{ collect($tiendas)->where('estado', 'activo')->count() }}</strong></div>
        <div class="stat"><div class="ico bad">@include('partials.icono', ['n' => 'alert'])</div><p>De baja</p><strong>{{ collect($tiendas)->where('estado', '!=', 'activo')->count() }}</strong></div>
    </section>

    <div class="card">
        <h2>Registrar tienda</h2>
        <form method="POST" action="/admin/tiendas">
            @csrf
            <div class="form-grid">
                <div class="full section-title"><span class="n">1</span> Datos del comercio</div>
                <div>
                    <label for="nombre_razon_social">Nombre o razón social @include('partials.ayuda', ['t' => 'Nombre comercial de la tienda'])</label>
                    <input id="nombre_razon_social" name="nombre_razon_social" maxlength="120" value="{{ old('nombre_razon_social') }}" required>
                </div>
                <div>
                    <label for="giro">Giro del negocio @include('partials.ayuda', ['t' => 'Tipo de negocio: bodega, panadería, peluquería, etc.'])</label>
                    <input id="giro" name="giro" maxlength="80" value="{{ old('giro') }}" placeholder="Ej.: bodega, panadería, peluquería" required>
                </div>
                <div>
                    <label for="direccion">Dirección @include('partials.ayuda', ['t' => 'Dirección del local'])</label>
                    <input id="direccion" name="direccion" maxlength="200" value="{{ old('direccion') }}" required>
                </div>
                <div>
                    <label for="contacto">Teléfono o contacto @include('partials.ayuda', ['t' => 'Teléfono y/o correo de la tienda'])</label>
                    <input id="contacto" name="contacto" maxlength="120" value="{{ old('contacto') }}" required>
                </div>
                <div class="full section-title" style="margin-top:6px"><span class="n">2</span> Administrador de la tienda</div>
                <div>
                    <label for="admin_nombre">Nombre completo @include('partials.ayuda', ['t' => 'Persona que administrará la tienda'])</label>
                    <input id="admin_nombre" name="admin_nombre" maxlength="120" value="{{ old('admin_nombre') }}" required>
                </div>
                <div>
                    <label for="admin_correo">Correo (usuario de acceso) @include('partials.ayuda', ['t' => 'Será su usuario para ingresar; debe ser único'])</label>
                    <input id="admin_correo" name="admin_correo" type="email" maxlength="120" value="{{ old('admin_correo') }}" required>
                </div>
                <div>
                    <label for="admin_password">Contraseña inicial @include('partials.ayuda', ['t' => 'Mínimo 8 caracteres'])</label>
                    <input id="admin_password" name="admin_password" type="password" minlength="8" maxlength="72" required>
                </div>
                <div style="align-self:end"><button class="btn" type="submit">@include('partials.icono', ['n' => 'save']) Guardar tienda</button></div>
            </div>
        </form>
    </div>

    <div class="card">
        <div class="card-head"><h2>Tiendas registradas</h2></div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Tienda</th><th>Giro</th><th>Contacto</th><th>Administrador</th><th>Estado</th><th></th></tr></thead>
                <tbody>
                @forelse($tiendas as $t)
                    <tr>
                        <td><div class="cell-main">{{ $t->nombre_razon_social }}</div><div class="cell-sub">{{ $t->direccion }}</div></td>
                        <td><span class="chip">{{ $t->giro }}</span></td>
                        <td>{{ $t->contacto }}</td>
                        <td>{{ $t->admin_correo ?? 'Sin administrador' }}</td>
                        <td><span class="badge {{ $t->estado === 'activo' ? 'ok' : 'bad' }}">{{ $t->estado === 'activo' ? 'Activa' : 'De baja' }}</span></td>
                        <td class="right">
                            <form method="POST" action="/admin/tiendas/{{ $t->tienda_id }}/estado" onsubmit="return confirm('¿Confirmas el cambio de estado de esta tienda?')">
                                @csrf
                                <button type="submit" class="btn sm {{ $t->estado === 'activo' ? 'danger' : 'success' }}">{{ $t->estado === 'activo' ? 'Dar de baja' : 'Reactivar' }}</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6"><div class="empty">@include('partials.icono', ['n' => 'store'])Aún no hay tiendas registradas.</div></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
