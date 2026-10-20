{{-- Paginación con los componentes del panel (el proyecto no usa Tailwind, que es lo que trae Laravel por defecto). --}}
@if ($paginator->hasPages())
    <nav class="admin-paginacion" role="navigation" aria-label="{{ __('sitio.paginacion.etiqueta') }}">
        @if ($paginator->onFirstPage())
            <span class="admin-paginacion__item es-inactivo">{{ __('sitio.paginacion.anteriores') }}</span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" class="admin-paginacion__item" rel="prev">{{ __('sitio.paginacion.anteriores') }}</a>
        @endif

        <span class="admin-paginacion__cuenta">
            {{ __('sitio.paginacion.cuenta', ['desde' => $paginator->firstItem(), 'hasta' => $paginator->lastItem(), 'total' => $paginator->total()]) }}
        </span>

        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" class="admin-paginacion__item" rel="next">{{ __('sitio.paginacion.siguientes') }}</a>
        @else
            <span class="admin-paginacion__item es-inactivo">{{ __('sitio.paginacion.siguientes') }}</span>
        @endif
    </nav>
@endif
