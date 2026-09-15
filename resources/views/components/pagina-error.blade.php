{{-- Plantilla común de las páginas de error. Sin dependencias de sesión ni base de datos. --}}
@props(['codigo', 'titulo', 'mensaje', 'accion' => 'Volver a los remates', 'href' => null])
<x-layouts.base :titulo="$titulo">
    <div class="acceso-sandbox">
        <x-tramite.cabecera etiqueta="REMATES" />

        <div class="acceso-sandbox__cuerpo">
            <div class="acceso-sandbox__caja">
                <div class="acceso-sandbox__kicker">ERROR {{ $codigo }}</div>
                <h1 class="acceso-sandbox__titulo">{{ $titulo }}</h1>
                <p class="acceso-sandbox__texto">{{ $mensaje }}</p>
                {{ $slot }}
                <a href="{{ $href ?? url('/') }}" class="acceso-sandbox__boton acceso-sandbox__boton--enlace">{{ $accion }}</a>
                <p class="acceso-sandbox__ayuda">¿Necesitas ayuda? Escríbenos a <a href="mailto:remates@colliers.cl">remates@colliers.cl</a> o llama al <a href="tel:+56227603535">+56 2 2760 3535</a>.</p>
            </div>
        </div>

        <x-tramite.pie />
    </div>
</x-layouts.base>
