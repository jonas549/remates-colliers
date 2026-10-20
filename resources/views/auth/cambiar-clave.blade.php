{{-- Pantalla sin diseño propio: reutiliza el diseño del Login (decisión del 16/09). --}}
@php
    $errores = $errors->getBag('updatePassword');
    $destino = auth()->user()->esAdministracion() ? route('admin.dashboard') : route('cuenta.estado');
@endphp
<x-acceso.marco
    :pagina="__('acceso.cambiar_clave.pagina')"
    :kicker="__('acceso.comun.seguridad_cuenta')"
    :titulo="$obligatorio ? __('acceso.cambiar_clave.titulo_obligatorio') : __('acceso.cambiar_clave.titulo')"
    :intro="$obligatorio ? __('acceso.cambiar_clave.intro_obligatorio') : __('acceso.cambiar_clave.intro')"
    :proximo="$proximo"
    alpine="{ ver: false }"
>
    @if (session('status') === 'password-updated')
        <p class="acceso__intro">{{ __('acceso.cambiar_clave.actualizada') }}</p>
        <form method="GET" action="{{ $destino }}">
            <button type="submit" class="acceso__entrar es-listo">{{ __('acceso.cambiar_clave.continuar') }}</button>
        </form>
    @else
        @if ($errores->any())
            <div class="acceso__error">{{ $errores->first() }}</div>
        @endif

        <form method="POST" action="{{ route('user-password.update') }}">
            @csrf
            @method('PUT')
            <div class="acceso__campos">
                <label class="acceso__campo acceso__campo--clave">
                    <span class="acceso__etiqueta">{{ __('acceso.cambiar_clave.contrasena_actual') }}</span>
                    <input name="current_password" :type="ver ? 'text' : 'password'" type="password" class="acceso__input" autocomplete="current-password" required>
                    <button type="button" class="acceso__ver-clave" @click="ver = !ver" x-text="ver ? @js(__('acceso.comun.ocultar')) : @js(__('acceso.comun.mostrar'))">{{ __('acceso.comun.mostrar') }}</button>
                </label>
                <label class="acceso__campo">
                    <span class="acceso__etiqueta">{{ __('acceso.comun.nueva_contrasena') }}</span>
                    <input name="password" :type="ver ? 'text' : 'password'" type="password" placeholder="{{ __('acceso.comun.minimo_8') }}" class="acceso__input" autocomplete="new-password" required>
                </label>
                <label class="acceso__campo">
                    <span class="acceso__etiqueta">{{ __('acceso.comun.repetir_contrasena') }}</span>
                    <input name="password_confirmation" :type="ver ? 'text' : 'password'" type="password" placeholder="{{ __('acceso.comun.repite_contrasena') }}" class="acceso__input" autocomplete="new-password" required>
                </label>
            </div>

            <div class="acceso__opciones">
                @unless ($obligatorio)
                    <a href="{{ $destino }}" class="acceso__olvide">{{ auth()->user()->esAdministracion() ? __('acceso.cambiar_clave.volver_panel') : __('acceso.cambiar_clave.volver_cuenta') }}</a>
                @endunless
            </div>

            <button type="submit" class="acceso__entrar es-listo">{{ __('acceso.comun.guardar_contrasena') }}</button>
        </form>
    @endif

    <div class="acceso__contacto">
        <a href="{{ route('cuenta.sesiones') }}">{{ __('acceso.cambiar_clave.sesiones_activas') }}</a>
        <form method="POST" action="{{ route('logout') }}" class="formulario-en-linea">
            @csrf
            <button type="submit" class="boton-enlace acceso__contacto-admin">{{ __('acceso.comun.cerrar_sesion') }}</button>
        </form>
    </div>
</x-acceso.marco>
