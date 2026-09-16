{{-- Pantalla sin diseño propio: reutiliza el diseño del Login (decisión del 16/09). --}}
<x-acceso.marco
    pagina="Confirma tu correo"
    kicker="REGISTRO DE POSTOR"
    titulo="Confirma tu correo"
    :intro="'Enviamos un enlace a ' . auth()->user()->email . '. Ábrelo para confirmar tu correo: recién entonces Colliers revisa tus antecedentes.'"
    :proximo="$proximo"
>
    @if (session('status') === 'verification-link-sent')
        <p class="acceso__intro">Te enviamos un enlace nuevo. Revisa también la carpeta de spam.</p>
    @endif

    <form method="POST" action="{{ route('verification.send') }}">
        @csrf
        <button type="submit" class="acceso__entrar es-listo">Reenviar el enlace</button>
    </form>

    <p class="acceso__nota">¿Te equivocaste de correo? Escríbenos a remates@colliers.cl y lo corregimos.</p>

    <div class="acceso__contacto">
        <a href="mailto:remates@colliers.cl">remates@colliers.cl</a>
        <form method="POST" action="{{ route('logout') }}" class="formulario-en-linea">
            @csrf
            <button type="submit" class="boton-enlace acceso__contacto-admin">Cerrar sesión</button>
        </form>
    </div>
</x-acceso.marco>
