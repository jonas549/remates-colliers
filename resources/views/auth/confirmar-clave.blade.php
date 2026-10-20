{{-- Pantalla sin diseño propio: reutiliza el diseño del Login (decisión del 16/09). --}}
<x-acceso.marco
    :pagina="__('acceso.confirmar_clave.titulo')"
    :kicker="__('acceso.comun.seguridad_cuenta')"
    :titulo="__('acceso.confirmar_clave.titulo')"
    :intro="__('acceso.confirmar_clave.intro')"
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
                <span class="acceso__etiqueta">{{ __('acceso.comun.contrasena') }}</span>
                <input name="password" :type="ver ? 'text' : 'password'" type="password" class="acceso__input" autocomplete="current-password" required>
                <button type="button" class="acceso__ver-clave" @click="ver = !ver" x-text="ver ? @js(__('acceso.comun.ocultar')) : @js(__('acceso.comun.mostrar'))">{{ __('acceso.comun.mostrar') }}</button>
            </label>
        </div>
        <div class="acceso__opciones"></div>
        <button type="submit" class="acceso__entrar es-listo">{{ __('acceso.confirmar_clave.confirmar') }}</button>
    </form>
</x-acceso.marco>
