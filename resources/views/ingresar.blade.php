<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ingresar | FiaPro</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/fiapro.css') }}?v=4">
</head>
<body>
<div class="auth">
    <section class="auth-hero">
        <div class="brand" style="margin:0">
            <span class="brand-logo">F</span>
            <span class="brand-name">Fia<span>Pro</span></span>
        </div>
        <div>
            <h2>El cuaderno del fiado, ahora digital y en orden.</h2>
            <p>Controla los créditos que tu bodega, panadería o negocio de barrio otorga a sus vecinos: compras, cuotas, intereses y moras en un solo lugar.</p>
            <div class="auth-points">
                <div>@include('partials.icono', ['n' => 'calc']) Cuotas quincenales con método francés</div>
                <div>@include('partials.icono', ['n' => 'list']) Listado de pago por quincena</div>
                <div>@include('partials.icono', ['n' => 'shield']) Acceso con usuario y contraseña por rol</div>
            </div>
        </div>
        <small style="color:var(--muted)">SI642 · Finanzas e Ingeniería Económica</small>
    </section>
    <section class="auth-form">
        <div class="auth-box">
            <h1>Bienvenido</h1>
            <p class="muted" style="margin:0 0 24px">Ingresa con el correo y la contraseña que te asignaron.</p>
            @if($errors->any())
                <div class="alert err">@include('partials.icono', ['n' => 'alert'])<div>@foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div></div>
            @endif
            <form method="POST" action="/ingresar">
                @csrf
                <div style="margin-bottom:16px">
                    <label for="correo">Correo @include('partials.ayuda', ['t' => 'Correo con el que te registraron en FiaPro'])</label>
                    <input id="correo" name="correo" type="email" value="{{ old('correo') }}" required autofocus placeholder="tu@correo.com">
                </div>
                <div style="margin-bottom:12px">
                    <label for="password">Contraseña @include('partials.ayuda', ['t' => 'Tu contraseña de acceso. Distingue mayúsculas y minúsculas'])</label>
                    <input id="password" name="password" type="password" required placeholder="••••••••">
                </div>
                <label style="font-weight:600;color:var(--ink-2)"><input type="checkbox" name="recordar" value="1"> Mantener sesión iniciada</label>
                <button class="btn" type="submit" style="width:100%;margin-top:18px;padding:12px">Ingresar</button>
            </form>
        </div>
    </section>
</div>
</body>
</html>
