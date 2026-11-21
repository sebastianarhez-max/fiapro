<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('titulo', 'FiaPro')</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/fiapro.css') }}?v=4">
    @stack('head')
</head>
<body>
@php
    $u = auth()->user();
    $rol = $u->rol;
    $nombresRol = ['admin_sistema' => 'Administrador del sistema', 'admin_tienda' => 'Administrador de tienda', 'cliente' => 'Cliente'];
    $iniciales = collect(explode(' ', trim($u->name)))->filter()->take(2)->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))->implode('');
    $menu = match ($rol) {
        'admin_sistema' => [
            ['Administración', [
                ['/admin/tiendas', 'Tiendas', 'store', 'admin/tiendas*'],
                ['/admin/bitacora', 'Bitácora', 'log', 'admin/bitacora*'],
            ]],
        ],
        'admin_tienda' => [
            ['General', [
                ['/panel', 'Resumen', 'home', 'panel'],
            ]],
            ['Operaciones', [
                ['/compras', 'Compras', 'cart', 'compras*'],
                ['/pagos', 'Cobros y pagos', 'wallet', 'pagos*'],
                ['/listados', 'Listados de pago', 'list', 'listados*'],
            ]],
            ['Catálogos', [
                ['/clientes', 'Clientes', 'users', 'clientes*'],
                ['/productos', 'Productos', 'box', 'productos*'],
            ]],
            ['Herramientas', [
                ['/cronograma', 'Simulador', 'calc', 'cronograma*'],
            ]],
        ],
        default => [
            ['Mi crédito', [
                ['/mi-cuenta', 'Mi cuenta', 'user', 'mi-cuenta'],
                ['/mi-cuenta/listado', 'Mi listado de pago', 'list', 'mi-cuenta/listado*'],
                ['/mi-cuenta/catalogo', 'Catálogo de la tienda', 'box', 'mi-cuenta/catalogo*'],
                ['/cronograma', 'Simulador', 'calc', 'cronograma*'],
            ]],
        ],
    };
@endphp
<div class="layout">
    <aside class="sidebar">
        <a class="brand" href="/inicio">
            <span class="brand-logo">F</span>
            <span class="brand-name">Fia<span>Pro</span></span>
        </a>
        <nav class="menu">
            @foreach($menu as [$titulo, $items])
                <div class="menu-title">{{ $titulo }}</div>
                @foreach($items as [$url, $texto, $icono, $patron])
                    <a href="{{ $url }}" class="{{ request()->is($patron) ? 'active' : '' }}">@include('partials.icono', ['n' => $icono]) {{ $texto }}</a>
                @endforeach
            @endforeach
        </nav>
        <div class="user-box">
            <div class="who">
                <span class="avatar">{{ $iniciales ?: 'U' }}</span>
                <div><strong>{{ $u->name }}</strong><small>{{ $nombresRol[$rol] ?? $rol }}</small></div>
            </div>
            <form method="POST" action="/salir">@csrf<button class="btn-salir" type="submit">Cerrar sesión</button></form>
        </div>
    </aside>
    <main class="main">
        @if(session('success'))
            <div class="alert ok">@include('partials.icono', ['n' => 'check'])<div>{{ session('success') }}</div></div>
        @endif
        @if($errors->any())
            <div class="alert err">@include('partials.icono', ['n' => 'alert'])<div>@foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div></div>
        @endif
        @yield('contenido')
    </main>
</div>
@stack('scripts')
</body>
</html>
