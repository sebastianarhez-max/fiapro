@extends('layouts.panel')
@section('titulo', 'Catálogo | FiaPro')
@use('App\Support\Moneda')
@php
    $mon = $cfg->moneda ?? 'PEN';
    $modalidades = ['UNICO' => 'Pago único', 'CUOTAS' => 'En cuotas', 'AMBOS' => 'Pago único o en cuotas'];
@endphp
@section('contenido')
    <div class="page-head">
        <div>
            <h1>Catálogo de {{ $tienda->nombre_razon_social ?? 'la tienda' }}</h1>
            <p>Productos y servicios que puedes llevar al fiado. Pídelos en la tienda y quedarán registrados en tu cuenta.</p>
        </div>
        @if($linea)
            <div class="card" style="padding:12px 16px">
                <div class="small muted">Tu crédito disponible</div>
                <div style="font-size:20px;font-weight:700">{{ Moneda::f($linea['disponible'], $mon) }}</div>
            </div>
        @endif
    </div>

    @if($productos->isEmpty())
        <div class="card"><div class="empty">@include('partials.icono', ['n' => 'box'])La tienda todavía no tiene productos.</div></div>
    @else
        <div class="catalogo">
            @foreach($productos as $p)
                @php($alcanza = ! $linea || $p->precio_lista <= $linea['disponible'])
                <div class="producto-card">
                    <div class="img">
                        @if(! $alcanza)
                            <span class="badge warn aviso">Supera tu disponible</span>
                        @endif
                        @if($p->imagen_url)
                            <img src="{{ $p->imagen_url }}" alt="{{ $p->descripcion }}" loading="lazy">
                        @else
                            @include('partials.icono', ['n' => 'box'])
                        @endif
                    </div>
                    <div class="body">
                        <h3>{{ $p->descripcion }}</h3>
                        <div class="cell-sub">{{ $p->marca ? $p->marca . ' · ' : '' }}{{ $p->unidad_medida }}</div>
                        <div><span class="chip">{{ $modalidades[$p->modalidad_venta] ?? $p->modalidad_venta }}</span></div>
                        <div class="precios">
                            <div>
                                <div class="small muted">Al contado {{ number_format($p->precio_contado, 2) }}</div>
                                <div class="precio">{{ Moneda::f($p->precio_lista, $mon) }}</div>
                                <div class="small muted">precio a crédito</div>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
@endsection
