{{--
    Layout del panel de administración. $seccion: dashboard | subastas | postores | reportes.
    El contador de Postores suma cuentas y garantías esperando revisión.
--}}
@props(['seccion', 'titulo', 'claseCuerpo' => ''])
@php
    $pendientes = \App\Models\Postor::where('estado', \App\Models\Postor::ESTADO_EN_REVISION)->count()
        + \App\Models\Garantia::where('estado', \App\Models\Garantia::ESTADO_EN_REVISION)->count();
    $items = [
        'dashboard' => ['01', 'Dashboard', route('admin.dashboard'), null],
        'subastas' => ['02', 'Subastas', route('admin.subastas'), null],
        'postores' => ['03', 'Postores', route('admin.postores'), $pendientes ?: null],
        'reportes' => ['04', 'Reportes', route('admin.reportes'), null],
    ];
@endphp
<x-layouts.base :titulo="$titulo . ' · Administración'" :clase-cuerpo="$claseCuerpo">
    <div class="admin" x-data="{ menu: false }" @keydown.escape.window="menu = false">

        <div class="admin-lateral" id="admin-menu" :class="{ 'es-abierta': menu }">
            <div class="admin-lateral__marca">
                <img src="{{ asset('img/colliers-logo.png') }}" alt="Colliers" class="admin-lateral__logo">
                <div class="admin-lateral__subtitulo">ADMINISTRACIÓN<br>DE REMATES</div>
                <button type="button" class="admin-lateral__cerrar" @click="menu = false" aria-label="Cerrar menú">✕</button>
            </div>
            <div class="admin-lateral__menu">
                @foreach ($items as $clave => [$num, $nombre, $ruta, $badge])
                    <a href="{{ $ruta }}" @class(['admin-lateral__item', 'es-actual' => $clave === $seccion]) @if ($clave === $seccion) aria-current="page" @endif><span class="admin-lateral__num">{{ $num }}</span> {{ $nombre }}@if ($badge) <span class="admin-lateral__badge">{{ $badge }}</span>@endif</a>
                @endforeach
            </div>
            <div class="admin-lateral__pie">
                <div class="admin-lateral__usuario">{{ auth()->user()?->name }}</div>
                <div class="admin-lateral__rol">{{ auth()->user()?->rol === \App\Models\User::ROL_MARTILLERO ? 'Martillero/a' : 'Administración' }}</div>
                <form method="POST" action="{{ route('logout') }}" class="formulario-en-linea">
                    @csrf
                    <button type="submit" class="boton-enlace admin-lateral__salir">Cerrar sesión</button>
                </form>
            </div>
        </div>

        <div class="admin-principal">
            {{ $slot }}
        </div>

        {{-- Barra superior y velo del menú (solo < 1120px). Van al final para no alterar la estructura
             del prototipo en escritorio; en móvil la barra se ordena primero con CSS. --}}
        <div class="admin-barra">
            <a href="{{ route('admin.dashboard') }}"><img src="{{ asset('img/colliers-logo.png') }}" alt="Colliers" class="admin-barra__logo"></a>
            <span class="admin-barra__seccion">{{ mb_strtoupper($items[$seccion][1]) }}</span>
            <button type="button" class="admin-barra__menu" @click="menu = true" :aria-expanded="menu" aria-controls="admin-menu" aria-label="Abrir menú">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M3 6h18M3 12h18M3 18h18"></path></svg>
            </button>
        </div>
        <div class="admin-velo" :class="{ 'es-visible': menu }" @click="menu = false"></div>
    </div>
</x-layouts.base>
