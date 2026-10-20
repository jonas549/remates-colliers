@php
    $administracion = ($portal ?? 'postores') === 'administracion';
    // «Ingresar como este postor» (postores de prueba) llega con ?usuario=correo: queda escrito y el foco va a la clave.
    $usuarioSugerido = is_string(request()->query('usuario')) && filter_var(request()->query('usuario'), FILTER_VALIDATE_EMAIL) ? request()->query('usuario') : '';
    $usuarioInicial = old('usuario', $usuarioSugerido);
    // Ningún error queda sin mostrar: primero los del formulario y, si no, cualquier otro.
    $errorServidor = $errors->first('usuario') ?: $errors->first('password') ?: $errors->first('email') ?: $errors->first();
    if (! $errorServidor && request('sesion') === 'perdida') {
        $errorServidor = __('acceso.login.sesion_perdida');
    }
@endphp
<x-acceso.marco
    :pagina="__('acceso.login.pagina')"
    :kicker="$administracion ? __('acceso.login.kicker_admin') : __('acceso.login.kicker_postores')"
    :titulo="$administracion ? __('acceso.login.titulo_admin') : __('acceso.login.titulo_postores')"
    :intro="$administracion ? __('acceso.login.intro_admin') : __('acceso.login.intro_postores')"
    :proximo="$proximo"
    :alpine="'acceso(' . \Illuminate\Support\Js::from($usuarioInicial) . ')'"
>
    @if ($errorServidor)
        <div class="acceso__error" x-show="!error">{{ $errorServidor }}</div>
    @endif
    @if (session('status'))
        <p class="acceso__intro">{{ session('status') }}</p>
    @endif
    <template x-if="error">
        <div class="acceso__error">{{ __('acceso.login.campos_vacios') }}</div>
    </template>

    <form method="POST" action="{{ route($administracion ? 'admin.ingresar.store' : 'login.store') }}" @submit="enviar">
        @csrf
        <div class="acceso__campos">
            <label class="acceso__campo">
                <span class="acceso__etiqueta">{{ $administracion ? __('acceso.login.etiqueta_correo') : __('acceso.login.etiqueta_correo_rut') }}</span>
                <input name="usuario" x-model="usuario" @input="error = false" placeholder="{{ $administracion ? __('acceso.login.ejemplo_admin') : __('acceso.comun.correo_ejemplo') }}" class="acceso__input" autocomplete="username">
            </label>
            <label class="acceso__campo acceso__campo--clave">
                <span class="acceso__etiqueta">{{ __('acceso.comun.contrasena') }}</span>
                <input name="password" :type="ver ? 'text' : 'password'" type="password" x-model="clave" @input="error = false" placeholder="{{ __('acceso.login.placeholder_clave') }}" class="acceso__input" autocomplete="current-password" @if ($usuarioInicial !== '' && ! $errorServidor) autofocus @endif>
                <button type="button" class="acceso__ver-clave" @click="alternarClave" x-text="ver ? @js(__('acceso.comun.ocultar')) : @js(__('acceso.comun.mostrar'))">{{ __('acceso.comun.mostrar') }}</button>
            </label>
        </div>

        <div class="acceso__opciones">
            <label class="acceso__recordar">
                <input type="checkbox" name="remember" x-model="recordar" checked>
                {{ __('acceso.login.mantener_sesion') }}
            </label>
            <a href="{{ route('password.request') }}" class="acceso__olvide">{{ __('acceso.login.olvide') }}</a>
        </div>

        <button type="submit" class="acceso__entrar" :class="{ 'es-listo': listo }">{{ __('acceso.comun.ingresar') }}</button>
    </form>

    @unless ($administracion)
        <div class="acceso__separador">
            <span class="acceso__separador-linea"></span>
            <span class="acceso__separador-texto">{{ __('acceso.login.o_registrate') }}</span>
            <span class="acceso__separador-linea"></span>
        </div>

        <a href="{{ route('register') }}" class="acceso__registro">{{ __('acceso.login.crear_cuenta') }}</a>
        <p class="acceso__nota">{{ __('acceso.login.nota_registro') }}</p>
    @endunless

    <div class="acceso__contacto">
        <a href="mailto:{{ \App\Support\Sitio::correo() }}">{{ \App\Support\Sitio::correo() }}</a>
        <a href="{{ \App\Support\Sitio::telefonoEnlace() }}">{{ \App\Support\Sitio::telefono() }}</a>
        @if ($administracion)
            <a href="{{ route('login') }}" class="acceso__contacto-admin">{{ __('acceso.login.acceso_postores') }}</a>
        @else
            <a href="{{ route('admin.ingresar') }}" class="acceso__contacto-admin">{{ __('acceso.login.acceso_admin') }}</a>
        @endif
    </div>
</x-acceso.marco>
