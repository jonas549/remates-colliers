{{-- Pantalla sin diseño propio: reutiliza el diseño del Login (decisión del 16/09). --}}
<x-acceso.marco
    pagina="Nueva contraseña"
    kicker="NUEVA CONTRASEÑA"
    titulo="Crea tu nueva contraseña"
    intro="Al guardarla cerraremos las sesiones abiertas de tu cuenta en todos los dispositivos."
    :proximo="$proximo"
    alpine="{ ver: false }"
>
    @if ($errors->any())
        <div class="acceso__error">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ route('password.update') }}">
        @csrf
        <input type="hidden" name="token" value="{{ $request->route('token') }}">
        <div class="acceso__campos">
            <label class="acceso__campo">
                <span class="acceso__etiqueta">CORREO ELECTRÓNICO</span>
                <input type="email" name="email" value="{{ old('email', $request->query('email')) }}" class="acceso__input" autocomplete="email" required>
            </label>
            <label class="acceso__campo acceso__campo--clave">
                <span class="acceso__etiqueta">NUEVA CONTRASEÑA</span>
                <input name="password" :type="ver ? 'text' : 'password'" type="password" placeholder="Mínimo 8 caracteres" class="acceso__input" autocomplete="new-password" required>
                <button type="button" class="acceso__ver-clave" @click="ver = !ver" x-text="ver ? 'OCULTAR' : 'MOSTRAR'">MOSTRAR</button>
            </label>
            <label class="acceso__campo">
                <span class="acceso__etiqueta">REPETIR CONTRASEÑA</span>
                <input name="password_confirmation" :type="ver ? 'text' : 'password'" type="password" placeholder="Repite la contraseña" class="acceso__input" autocomplete="new-password" required>
            </label>
        </div>

        <div class="acceso__opciones">
            <a href="{{ route('login') }}" class="acceso__olvide">Volver a ingresar</a>
        </div>

        <button type="submit" class="acceso__entrar es-listo">Guardar contraseña</button>
    </form>
</x-acceso.marco>
