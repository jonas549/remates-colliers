{{-- Pantalla sin diseño propio: reutiliza el diseño del Login (decisión del 16/09). --}}
<x-acceso.marco
    pagina="Confirma tu contraseña"
    kicker="SEGURIDAD DE LA CUENTA"
    titulo="Confirma tu contraseña"
    intro="Por seguridad, vuelve a ingresar tu contraseña para continuar."
    :proximo="$proximo"
    alpine="{ ver: false }"
>
    @error('password')
        <div class="acceso__error">{{ $message }}</div>
    @enderror

    <form method="POST" action="{{ route('password.confirm.store') }}">
        @csrf
        <div class="acceso__campos">
            <label class="acceso__campo acceso__campo--clave">
                <span class="acceso__etiqueta">CONTRASEÑA</span>
                <input name="password" :type="ver ? 'text' : 'password'" type="password" class="acceso__input" autocomplete="current-password" required>
                <button type="button" class="acceso__ver-clave" @click="ver = !ver" x-text="ver ? 'OCULTAR' : 'MOSTRAR'">MOSTRAR</button>
            </label>
        </div>
        <div class="acceso__opciones"></div>
        <button type="submit" class="acceso__entrar es-listo">Confirmar</button>
    </form>
</x-acceso.marco>
