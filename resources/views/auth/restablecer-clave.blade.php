{{-- Pantalla sin diseño propio: reutiliza el diseño del Login (decisión del 16/09). --}}
<x-acceso.marco
    :pagina="__('acceso.restablecer.pagina')"
    :kicker="__('acceso.restablecer.kicker')"
    :titulo="__('acceso.restablecer.titulo')"
    :intro="__('acceso.restablecer.intro')"
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
                <span class="acceso__etiqueta">{{ __('acceso.comun.correo_electronico') }}</span>
                <input type="email" name="email" value="{{ old('email', $request->query('email')) }}" class="acceso__input" autocomplete="email" required>
            </label>
            <label class="acceso__campo acceso__campo--clave">
                <span class="acceso__etiqueta">{{ __('acceso.comun.nueva_contrasena') }}</span>
                <input name="password" :type="ver ? 'text' : 'password'" type="password" placeholder="{{ __('acceso.comun.minimo_8') }}" class="acceso__input" autocomplete="new-password" required>
                <button type="button" class="acceso__ver-clave" @click="ver = !ver" x-text="ver ? @js(__('acceso.comun.ocultar')) : @js(__('acceso.comun.mostrar'))">{{ __('acceso.comun.mostrar') }}</button>
            </label>
            <label class="acceso__campo">
                <span class="acceso__etiqueta">{{ __('acceso.comun.repetir_contrasena') }}</span>
                <input name="password_confirmation" :type="ver ? 'text' : 'password'" type="password" placeholder="{{ __('acceso.comun.repite_contrasena') }}" class="acceso__input" autocomplete="new-password" required>
            </label>
        </div>

        <div class="acceso__opciones">
            <a href="{{ route('login') }}" class="acceso__olvide">{{ __('acceso.comun.volver_a_ingresar') }}</a>
        </div>

        <button type="submit" class="acceso__entrar es-listo">{{ __('acceso.comun.guardar_contrasena') }}</button>
    </form>
</x-acceso.marco>
