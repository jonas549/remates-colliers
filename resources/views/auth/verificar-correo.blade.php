{{-- Pantalla sin diseño propio: reutiliza el diseño del Login (decisión del 16/09). --}}
<x-acceso.marco
    :pagina="__('acceso.verificar.pagina')"
    :kicker="__('acceso.verificar.kicker')"
    :titulo="__('acceso.verificar.titulo')"
    :intro="__('acceso.verificar.intro', ['correo' => auth()->user()->email])"
    :proximo="$proximo"
>
    @if (session('status') === 'verification-link-sent')
        <p class="acceso__intro">{{ __('acceso.verificar.reenviado') }}</p>
    @endif

    <form method="POST" action="{{ route('verification.send') }}">
        @csrf
        <button type="submit" class="acceso__entrar es-listo">{{ __('acceso.verificar.reenviar') }}</button>
    </form>

    <p class="acceso__nota">{{ __('acceso.verificar.nota', ['correo' => \App\Support\Sitio::correo()]) }}</p>

    <div class="acceso__contacto">
        <a href="mailto:{{ \App\Support\Sitio::correo() }}">{{ \App\Support\Sitio::correo() }}</a>
        <form method="POST" action="{{ route('logout') }}" class="formulario-en-linea">
            @csrf
            <button type="submit" class="boton-enlace acceso__contacto-admin">{{ __('acceso.comun.cerrar_sesion') }}</button>
        </form>
    </div>
</x-acceso.marco>
