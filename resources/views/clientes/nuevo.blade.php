@extends('layouts.panel')
@section('titulo', 'Registrar cliente | FiaPro')
@section('contenido')
    <div class="page-head">
        <div>
            <div class="crumbs"><a href="/clientes">Clientes</a> / Nuevo</div>
            <h1>Registrar cliente</h1>
            <p>Datos del cliente, su acceso para consultar su cuenta y las condiciones de crédito pactadas.</p>
        </div>
    </div>

    <form method="POST" action="/clientes">
        @csrf
        <div class="card">
            <div class="form-grid">
                <div class="full section-title"><span class="n">1</span> Datos del cliente</div>
                <div>
                    <label for="nombre_completo">Nombre completo @include('partials.ayuda', ['t' => 'Nombres y apellidos del cliente'])</label>
                    <input id="nombre_completo" name="nombre_completo" maxlength="120" value="{{ old('nombre_completo') }}" required>
                </div>
                <div>
                    <label for="documento_identidad">DNI o carné de extranjería @include('partials.ayuda', ['t' => 'Entre 8 y 12 letras o números; no puede repetirse en tu tienda'])</label>
                    <input id="documento_identidad" name="documento_identidad" maxlength="12" value="{{ old('documento_identidad') }}" required>
                </div>
                <div class="full">
                    <label for="contacto">Teléfono o contacto @include('partials.ayuda', ['t' => 'Teléfono y/o correo de contacto'])</label>
                    <input id="contacto" name="contacto" maxlength="120" value="{{ old('contacto') }}" required>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="form-grid">
                <div class="full section-title"><span class="n">2</span> Acceso del cliente</div>
                <div>
                    <label for="correo">Correo (usuario) @include('partials.ayuda', ['t' => 'Con este correo el cliente ingresará a consultar su cuenta; debe ser único'])</label>
                    <input id="correo" name="correo" type="email" maxlength="120" value="{{ old('correo') }}" required>
                </div>
                <div>
                    <label for="password">Contraseña inicial @include('partials.ayuda', ['t' => 'Mínimo 8 caracteres'])</label>
                    <input id="password" name="password" type="password" minlength="8" maxlength="72" required>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="form-grid cols-4">
                <div class="full section-title"><span class="n">3</span> Condiciones de crédito</div>
                <div>
                    <label for="moneda">Moneda @include('partials.ayuda', ['t' => 'Moneda en que se otorga el crédito. No hay conversión automática'])</label>
                    <select id="moneda" name="moneda">
                        <option value="PEN" @selected(old('moneda', 'PEN') === 'PEN')>Soles (PEN)</option>
                        <option value="USD" @selected(old('moneda') === 'USD')>Dólares (USD)</option>
                    </select>
                </div>
                <div>
                    <label for="limite_credito">Límite de crédito @include('partials.ayuda', ['t' => 'Máximo de capital pendiente que se le permite mantener. Entre ' . config('fiapro.limite_min') . ' y ' . config('fiapro.limite_max') . '. No se aceptan compras que lo excedan'])</label>
                    <input id="limite_credito" name="limite_credito" type="number" step="0.01" min="{{ config('fiapro.limite_min') }}" max="{{ config('fiapro.limite_max') }}" value="{{ old('limite_credito') }}" required>
                    <div class="hint">De {{ config('fiapro.limite_min') }} a {{ config('fiapro.limite_max') }}</div>
                </div>
                <div>
                    <label for="tasa_comp">TEA ordinaria (%) @include('partials.ayuda', ['t' => 'Tasa efectiva anual pactada. Se convierte a quincenal o diaria con base 360. Máximo ' . config('fiapro.tea_max_ordinaria') . '%'])</label>
                    <input id="tasa_comp" name="tasa_comp" type="number" step="0.0000001" min="0.0000001" max="{{ config('fiapro.tea_max_ordinaria') }}" value="{{ old('tasa_comp') }}" required>
                    <div class="hint">Máx. {{ config('fiapro.tea_max_ordinaria') }}%</div>
                </div>
                <div>
                    <label for="tasa_mora">TEA moratoria (%) @include('partials.ayuda', ['t' => 'Tasa efectiva anual que se aplica por los días de atraso. Máximo ' . config('fiapro.tea_max_mora') . '%'])</label>
                    <input id="tasa_mora" name="tasa_mora" type="number" step="0.0000001" min="0" max="{{ config('fiapro.tea_max_mora') }}" value="{{ old('tasa_mora') }}" required>
                    <div class="hint">Máx. {{ config('fiapro.tea_max_mora') }}%</div>
                </div>
                <div>
                    <label for="max_meses">Máximo de meses @include('partials.ayuda', ['t' => 'Plazo máximo para financiar. Las cuotas son quincenales: cada mes equivale a 2 cuotas'])</label>
                    <input id="max_meses" name="max_meses" type="number" min="1" max="{{ config('fiapro.meses_max') }}" value="{{ old('max_meses', config('fiapro.meses_defecto')) }}" required>
                    <div class="hint">De 1 a {{ config('fiapro.meses_max') }}</div>
                </div>
            </div>
            <div class="actions" style="margin-top:20px">
                <button class="btn" type="submit">@include('partials.icono', ['n' => 'save']) Guardar cliente</button>
                <a class="btn ghost" href="/clientes">Cancelar</a>
            </div>
        </div>
    </form>
@endsection
