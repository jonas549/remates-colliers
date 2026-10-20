{{-- Pantalla sin diseño propio: reutiliza el diseño del Login (decisión del 16/09). --}}
<x-acceso.marco
    :pagina="__('acceso.ingresar_como.pagina')"
    :kicker="__('acceso.ingresar_como.kicker')"
    :titulo="__('acceso.ingresar_como.titulo', ['usuario' => $usuario])"
    :intro="__('acceso.ingresar_como.intro', ['nombre' => $actual->name, 'correo' => $actual->email, 'usuario' => $usuario])"
    :proximo="$proximo"
>
    <form method="POST" action="{{ route('ingresar.como.cambiar') }}">
        @csrf
        <input type="hidden" name="usuario" value="{{ $usuario }}">
        @if ($portal)
            <input type="hidden" name="portal" value="{{ $portal }}">
        @endif
        <button type="submit" class="acceso__entrar es-listo">{{ __('acceso.ingresar_como.boton', ['usuario' => $usuario]) }}</button>
    </form>

    <p class="acceso__nota">{{ __('acceso.ingresar_como.nota', ['nombre' => $actual->name]) }}</p>
    <div class="acceso__enlace-copiar">
        <code class="acceso__enlace" data-enlace>{{ $enlace }}</code>
        <button type="button" class="boton-copiar" x-data="copiar(@js($enlace))" @click="copiar"
                x-text="copiado ? @js(__('acceso.ingresar_como.copiado')) : @js(__('acceso.ingresar_como.copiar_enlace'))">{{ __('acceso.ingresar_como.copiar_enlace') }}</button>
    </div>

    <div class="acceso__contacto">
        <a href="{{ $actual->esAdministracion() ? route('admin.dashboard') : route('cuenta.estado') }}">{{ __('acceso.ingresar_como.volver') }}</a>
    </div>
</x-acceso.marco>
