{{--
    Cabecera pública. $sesion: visitante | registrado | en-revision | aprobada (demo hasta el Bloque D).
    Slot opcional: franjas bajo la navegación (barra de garantía, ticker de remate en vivo).
--}}
@props(['sesion' => 'visitante', 'zIndex' => null])
@php($logueado = $sesion !== 'visitante')
<div class="pub-cabecera" @if ($zIndex) style="z-index: {{ $zIndex }}" @endif x-data="{ menu: false }">
    <div class="pub-cabecera__nav contenedor">
        <a href="{{ route('remates.index') }}"><img src="{{ asset('img/colliers-logo.png') }}" alt="Colliers" class="pub-cabecera__logo"></a>
        <div class="pub-cabecera__links">
            <a href="#">Servicios</a>
            <a href="#">Expertos</a>
            <a href="#">Propiedades</a>
            <a href="#">Investigación</a>
            <a href="#">Nosotros</a>
            <a href="{{ route('remates.index') }}" class="es-actual">Remates</a>
        </div>
        <div class="pub-cabecera__derecha">
            <a href="{{ route('remates.index') }}#buscar" class="pub-cabecera__buscar" aria-label="Buscar remates"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#25408f" stroke-width="1.5"><circle cx="11" cy="11" r="7"></circle><path d="m20 20-3.5-3.5"></path></svg></a>
            <span class="pub-cabecera__idioma">EN / <span>ES</span></span>
            @if ($logueado)
                <a href="{{ route('admin.dashboard') }}" class="pub-cabecera__panel">Panel Colliers</a>
                <a href="{{ route('cuenta.estado') }}" class="pub-cabecera__acceso">Mi cuenta</a>
            @else
                <a href="{{ route('login') }}" class="pub-cabecera__acceso">Ingresar</a>
            @endif
            <button type="button" class="pub-cabecera__menu-boton" aria-label="Abrir menú" :aria-expanded="menu" @click="menu = !menu">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#25408f" stroke-width="1.5"><path d="M3 6h18M3 12h18M3 18h18"></path></svg>
            </button>
        </div>
    </div>
    <template x-if="menu">
        <div class="pub-menu">
            <a href="#">Servicios</a>
            <a href="#">Expertos</a>
            <a href="#">Propiedades</a>
            <a href="#">Investigación</a>
            <a href="#">Nosotros</a>
            <a href="{{ route('remates.index') }}" class="es-actual">Remates</a>
            @if ($logueado)
                <a href="{{ route('cuenta.estado') }}" class="pub-menu__acceso">Mi cuenta</a>
                <a href="{{ route('admin.dashboard') }}" class="pub-menu__panel">Panel Colliers</a>
            @else
                <a href="{{ route('login') }}" class="pub-menu__acceso">Ingresar</a>
            @endif
        </div>
    </template>
    {{ $slot }}
</div>
