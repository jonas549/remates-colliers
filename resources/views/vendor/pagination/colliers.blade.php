{{-- Paginación con los componentes del panel (el proyecto no usa Tailwind, que es lo que trae Laravel por defecto). --}}
@if ($paginator->hasPages())
    <nav class="admin-paginacion" role="navigation" aria-label="Paginación">
        @if ($paginator->onFirstPage())
            <span class="admin-paginacion__item es-inactivo">← Anteriores</span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" class="admin-paginacion__item" rel="prev">← Anteriores</a>
        @endif

        <span class="admin-paginacion__cuenta">
            {{ $paginator->firstItem() }}–{{ $paginator->lastItem() }} de {{ $paginator->total() }}
        </span>

        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" class="admin-paginacion__item" rel="next">Siguientes →</a>
        @else
            <span class="admin-paginacion__item es-inactivo">Siguientes →</span>
        @endif
    </nav>
@endif
