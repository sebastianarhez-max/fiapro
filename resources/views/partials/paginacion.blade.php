@if($p->hasPages())
    <div class="pager">
        <span class="muted small">Mostrando {{ $p->firstItem() }}–{{ $p->lastItem() }} de {{ $p->total() }}</span>
        <div class="actions">
            @if($p->onFirstPage())
                <button class="btn ghost sm" disabled>Anterior</button>
            @else
                <a class="btn ghost sm" href="{{ $p->previousPageUrl() }}">Anterior</a>
            @endif
            @if($p->hasMorePages())
                <a class="btn ghost sm" href="{{ $p->nextPageUrl() }}">Siguiente</a>
            @else
                <button class="btn ghost sm" disabled>Siguiente</button>
            @endif
        </div>
    </div>
@endif
