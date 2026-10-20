{{-- Plantilla común de las páginas de error. Sin dependencias de sesión ni base de datos. --}}
@props(['codigo', 'titulo', 'mensaje', 'accion' => null, 'href' => null])
@php($correo = \App\Support\Sitio::correo())
<x-layouts.base :titulo="$titulo">
    <div class="acceso-sandbox">
        <x-tramite.cabecera :etiqueta="__('errores.etiqueta')" />

        <div class="acceso-sandbox__cuerpo">
            <div class="acceso-sandbox__caja">
                <div class="acceso-sandbox__kicker">{{ __('errores.kicker', ['codigo' => $codigo]) }}</div>
                <h1 class="acceso-sandbox__titulo">{{ $titulo }}</h1>
                <p class="acceso-sandbox__texto">{{ $mensaje }}</p>
                {{ $slot }}
                <a href="{{ $href ?? route('remates.index') }}" class="acceso-sandbox__boton acceso-sandbox__boton--enlace">{{ $accion ?? __('errores.volver_remates') }}</a>
                <p class="acceso-sandbox__ayuda">{!! __('errores.ayuda', [
                    'correo' => '<a href="mailto:' . e($correo) . '">' . e($correo) . '</a>',
                    'telefono' => '<a href="' . e(\App\Support\Sitio::telefonoEnlace()) . '">' . e(\App\Support\Sitio::telefono()) . '</a>',
                ]) !!}</p>
            </div>
        </div>

        <x-tramite.pie />
    </div>
</x-layouts.base>
