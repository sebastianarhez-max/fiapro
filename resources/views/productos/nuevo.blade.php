@extends('layouts.panel')
@section('titulo', 'Registrar producto | FiaPro')
@section('contenido')
    <div class="page-head">
        <div>
            <div class="crumbs"><a href="/productos">Productos</a> / Nuevo</div>
            <h1>Registrar producto o servicio</h1>
            <p>Completa la ficha del producto con sus precios al contado y a crédito.</p>
        </div>
    </div>

    <form method="POST" action="/productos" enctype="multipart/form-data">
        @csrf
        <div class="card">
            <div class="form-grid">
                <div class="full">
                    <label for="descripcion">Descripción @include('partials.ayuda', ['t' => 'Nombre o descripción del producto o servicio'])</label>
                    <input id="descripcion" name="descripcion" maxlength="200" value="{{ old('descripcion') }}" required placeholder="Ej.: Arroz extra 5 kg">
                </div>
                <div>
                    <label for="marca">Marca (opcional) @include('partials.ayuda', ['t' => 'Marca del producto, si corresponde'])</label>
                    <input id="marca" name="marca" maxlength="80" value="{{ old('marca') }}">
                </div>
                <div>
                    <label for="proveedor">Proveedor (opcional) @include('partials.ayuda', ['t' => 'Proveedor del producto, si corresponde'])</label>
                    <input id="proveedor" name="proveedor" maxlength="120" value="{{ old('proveedor') }}">
                </div>
                <div>
                    <label for="unidad_medida">Unidad de medida @include('partials.ayuda', ['t' => 'Ej.: unidad, kg, litro, servicio'])</label>
                    <input id="unidad_medida" name="unidad_medida" maxlength="30" value="{{ old('unidad_medida', 'Unidad') }}" required>
                </div>
                <div>
                    <label for="modalidad_venta">Modalidad de venta @include('partials.ayuda', ['t' => 'Pago único: se paga a los 15 días de la compra. En cuotas: se financia. Ambos: el cliente elige'])</label>
                    <select id="modalidad_venta" name="modalidad_venta">
                        @foreach($modalidades as $valor => $texto)
                            <option value="{{ $valor }}" @selected(old('modalidad_venta', 'UNICO') === $valor)>{{ $texto }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="precio_contado">Precio al contado @include('partials.ayuda', ['t' => 'Precio si el cliente paga en el momento. Mayor a 0'])</label>
                    <input id="precio_contado" name="precio_contado" type="number" step="0.01" min="0.01" value="{{ old('precio_contado') }}" required>
                </div>
                <div>
                    <label for="precio_lista">Precio de lista (crédito) @include('partials.ayuda', ['t' => 'Precio cuando se vende a crédito. No puede ser menor al precio al contado'])</label>
                    <input id="precio_lista" name="precio_lista" type="number" step="0.01" min="0.01" value="{{ old('precio_lista') }}" required>
                </div>
                <div class="full">
                    <label for="imagen">Imagen (opcional) @include('partials.ayuda', ['t' => 'Foto del producto: JPG, PNG o WEBP de hasta 2 MB'])</label>
                    <input id="imagen" name="imagen" type="file" accept="image/png,image/jpeg,image/webp">
                </div>
            </div>
            <div class="actions" style="margin-top:20px">
                <button class="btn" type="submit">@include('partials.icono', ['n' => 'save']) Guardar producto</button>
                <a class="btn ghost" href="/productos">Cancelar</a>
            </div>
        </div>
    </form>
@endsection
