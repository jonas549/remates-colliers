{{-- Pantalla sin diseño propio: reutiliza el diseño del Login (decisión del 16/09). --}}
<x-acceso.marco
    pagina="Recuperar contraseña"
    kicker="RECUPERAR CONTRASEÑA"
    titulo="Recupera tu acceso"
    intro="Ingresa el correo con que te registraste. Te enviaremos un enlace para crear una contraseña nueva."
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
                <span class="acceso__etiqueta">CORREO ELECTRÓNICO</span>
                <input type="email" name="email" value="{{ old('email') }}" placeholder="maria@correo.cl" class="acceso__input" autocomplete="email" required>
            </label>
        </div>

        <div class="acceso__opciones">
            <a href="{{ route('login') }}" class="acceso__olvide">Volver a ingresar</a>
        </div>

        <button type="submit" class="acceso__entrar es-listo">Enviar enlace</button>
    </form>

    <div class="acceso__contacto">
        <a href="mailto:remates@colliers.cl">remates@colliers.cl</a>
        <a href="tel:+56227603535">+56 2 2760 3535</a>
    </div>
</x-acceso.marco>
