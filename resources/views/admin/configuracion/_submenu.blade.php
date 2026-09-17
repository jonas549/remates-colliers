{{-- Submenú de Configuración: una entrada por sección (17/09). Sin diseño del prototipo: usa los componentes del panel. --}}
@php
    use App\Models\Configuracion;
@endphp
<nav class="admin-submenu" aria-label="Secciones de configuración">
    @foreach (Configuracion::SECCIONES as $clave => $datos)
        <a href="{{ route('admin.configuracion.seccion', $clave) }}"
            @class(['admin-submenu__item', 'es-actual' => $clave === $actual])
            @if ($clave === $actual) aria-current="page" @endif>
            <span class="admin-submenu__num">{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>
            {{ $datos['titulo'] }}
        </a>
    @endforeach
</nav>
