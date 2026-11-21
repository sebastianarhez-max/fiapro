@extends('layouts.panel')
@section('titulo', 'Productos | FiaPro')
@section('contenido')
    <div class="page-head">
        <div>
            <h1>Productos y servicios</h1>
            <p>Catálogo de lo que tu tienda vende al contado o a crédito. Haz clic en una foto para cambiarla.</p>
        </div>
        <a class="btn" href="/productos/nuevo">@include('partials.icono', ['n' => 'plus']) Nuevo producto</a>
    </div>

    <div class="card">
        <div class="table-wrap">
            <table>
                <thead>
                    <tr><th></th><th>Producto</th><th>Unidad</th><th class="right">Contado</th><th class="right">Lista (crédito)</th><th>Modalidad</th><th>Estado</th><th></th></tr>
                </thead>
                <tbody>
                @forelse($productos as $p)
                    <tr>
                        <td style="width:76px">
                            <form method="POST" action="/productos/{{ $p->producto_id }}/imagen" enctype="multipart/form-data" class="foto-cambiar" title="Cambiar imagen">
                                @csrf
                                <label style="margin:0;cursor:pointer">
                                    @include('partials.foto', ['url' => $p->imagen_url, 'tam' => 'md', 'alt' => $p->descripcion])
                                    <input type="file" name="imagen" accept="image/png,image/jpeg,image/webp" onchange="this.form.submit()">
                                </label>
                            </form>
                        </td>
                        <td><div class="cell-main">{{ $p->descripcion }}</div><div class="cell-sub">{{ $p->marca ?? 'Sin marca' }}{{ $p->proveedor ? ' · ' . $p->proveedor : '' }}</div></td>
                        <td>{{ $p->unidad_medida }}</td>
                        <td class="right num">{{ number_format($p->precio_contado, 2) }}</td>
                        <td class="right num cell-main">{{ number_format($p->precio_lista, 2) }}</td>
                        <td><span class="chip">{{ $modalidades[$p->modalidad_venta] ?? $p->modalidad_venta }}</span></td>
                        <td><span class="badge {{ $p->estado === 'activo' ? 'ok' : 'bad' }}">{{ $p->estado === 'activo' ? 'Activo' : 'De baja' }}</span></td>
                        <td class="right">
                            <div class="actions" style="justify-content:flex-end">
                            <form method="POST" action="/productos/{{ $p->producto_id }}/imagen" enctype="multipart/form-data" class="foto-cambiar">
                                @csrf
                                <label class="btn ghost sm" style="margin:0;cursor:pointer;font-weight:600">{{ $p->imagen_url ? 'Cambiar imagen' : 'Agregar imagen' }}
                                    <input type="file" name="imagen" accept="image/png,image/jpeg,image/webp" onchange="this.form.submit()">
                                </label>
                            </form>
                            <form method="POST" action="/productos/{{ $p->producto_id }}/estado" onsubmit="return confirm('¿Confirmas el cambio de estado de este producto?')">
                                @csrf
                                <button type="submit" class="btn sm {{ $p->estado === 'activo' ? 'danger' : 'success' }}">{{ $p->estado === 'activo' ? 'Dar de baja' : 'Reactivar' }}</button>
                            </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8"><div class="empty">@include('partials.icono', ['n' => 'box'])Aún no registraste productos.</div></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
