{{-- QA 21/10, 4.4: una dirección que solo recibe formularios, abierta desde la barra o un marcador (antes, la página de Symfony en inglés). --}}
@php($panel = request()->is('admin', 'admin/*'))
<x-pagina-error codigo="405" :titulo="__('errores.405.titulo')" :mensaje="__('errores.405.mensaje')"
    :accion="$panel ? __('errores.volver_panel') : null" :href="$panel ? route('admin.dashboard') : null" />
