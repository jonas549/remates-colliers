{{-- Pantalla sin diseño propio: reutiliza el diseño del Login (decisión del 16/09). --}}
<x-acceso.marco
    :pagina="__('acceso.recuperar.pagina')"
    :kicker="__('acceso.recuperar.kicker')"
    :titulo="__('acceso.recuperar.titulo')"
    :intro="__('acceso.recuperar.intro')"
    :proximo="$proximo"
>
    @if (session('status'))
        <p class="acceso__intro">{{ session('status') }}</p>
    @endif
    @error('email')
        <div class="acceso__error">{{ $message }}</div>
    @enderror

    <form method="POST" action="{{ route('password.email') }}">
        @csrf
        <div class="acceso__campos">
            <label class="acceso__campo">
                <span class="acceso__etiqueta">{{ __('acceso.comun.correo_electronico') }}</span>
                <input type="email" name="email" value="{{ old('email') }}" placeholder="{{ __('acceso.comun.correo_ejemplo') }}" class="acceso__input" autocomplete="email" required>
            </label>
        </div>

        <div class="acceso__opciones">
            <a href="{{ route('login') }}" class="acceso__olvide">{{ __('acceso.comun.volver_a_ingresar') }}</a>
        </div>

        <button type="submit" class="acceso__entrar es-listo">{{ __('acceso.recuperar.enviar') }}</button>
    </form>

    <div class="acceso__contacto">
        <a href="mailto:{{ \App\Support\Sitio::correo() }}">{{ \App\Support\Sitio::correo() }}</a>
        <a href="{{ \App\Support\Sitio::telefonoEnlace() }}">{{ \App\Support\Sitio::telefono() }}</a>
    </div>
</x-acceso.marco>
