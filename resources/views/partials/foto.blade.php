@php($tam = $tam ?? 'sm')
@if(! empty($url))
    <img class="foto foto-{{ $tam }}" src="{{ $url }}" alt="{{ $alt ?? '' }}" loading="lazy">
@else
    <span class="foto foto-{{ $tam }} foto-vacia">@include('partials.icono', ['n' => 'box'])</span>
@endif
