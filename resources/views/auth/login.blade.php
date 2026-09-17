@php
    $administracion = ($portal ?? 'postores') === 'administracion';
    // Ningún error queda sin mostrar: primero los del formulario y, si no, cualquier otro.
    $errorServidor = $errors->first('usuario') ?: $errors->first('password') ?: $errors->first('email') ?: $errors->first();
    if (! $errorServidor && request('sesion') === 'perdida') {
        $errorServidor = 'Tu usuario y contraseña son correctos, pero el navegador no conservó la sesión. Vuelve a ingresar; si se repite, '
            . 'permite las cookies de este sitio o prueba en otra ventana. Ya quedó registrado para revisarlo.';
    }
@endphp
<x-acceso.marco
    pagina="Ingresar"
    :kicker="$administracion ? 'ACCESO DE ADMINISTRADORES' : 'ACCESO DE POSTORES'"
    :titulo="$administracion ? 'Ingresa al panel de Colliers' : 'Ingresa a tu cuenta'"
    :intro="$administracion ? 'Administración de remates, postores y garantías. Acceso exclusivo para el equipo de Colliers y los martilleros.' : 'Sigue los remates publicados, revisa el estado de tus garantías y entra a la sala de pujas cuando comience la transmisión.'"
    :proximo="$proximo"
    :alpine="'acceso(' . \Illuminate\Support\Js::from(old('usuario', '')) . ')'"
>
    @if ($errorServidor)
        <div class="acceso__error" x-show="!error">{{ $errorServidor }}</div>
    @endif
    @if (session('status'))
        <p class="acceso__intro">{{ session('status') }}</p>
    @endif
    <template x-if="error">
        <div class="acceso__error">Ingresa tu correo o RUT y tu contraseña.</div>
    </template>

    <form method="POST" action="{{ route($administracion ? 'admin.ingresar.store' : 'login.store') }}" @submit="enviar">
        @csrf
        <div class="acceso__campos">
            <label class="acceso__campo">
                <span class="acceso__etiqueta">{{ $administracion ? 'CORREO' : 'CORREO O RUT' }}</span>
                <input name="usuario" x-model="usuario" @input="error = false" placeholder="{{ $administracion ? 'nombre@colliers.com' : 'maria@correo.cl' }}" class="acceso__input" autocomplete="username">
            </label>
            <label class="acceso__campo acceso__campo--clave">
                <span class="acceso__etiqueta">CONTRASEÑA</span>
                <input name="password" :type="ver ? 'text' : 'password'" type="password" x-model="clave" @input="error = false" placeholder="Tu contraseña" class="acceso__input" autocomplete="current-password">
                <button type="button" class="acceso__ver-clave" @click="alternarClave" x-text="ver ? 'OCULTAR' : 'MOSTRAR'">MOSTRAR</button>
            </label>
        </div>

        <div class="acceso__opciones">
            <label class="acceso__recordar">
                <input type="checkbox" name="remember" x-model="recordar" checked>
                Mantener sesión iniciada
            </label>
            <a href="{{ route('password.request') }}" class="acceso__olvide">Olvidé mi contraseña</a>
        </div>

        <button type="submit" class="acceso__entrar" :class="{ 'es-listo': listo }">Ingresar</button>
    </form>

    @unless ($administracion)
        <div class="acceso__separador">
            <span class="acceso__separador-linea"></span>
            <span class="acceso__separador-texto">O REGÍSTRATE</span>
            <span class="acceso__separador-linea"></span>
        </div>

        <a href="{{ route('register') }}" class="acceso__registro">Crear cuenta de postor</a>
        <p class="acceso__nota">El registro queda sujeto a aprobación de Colliers. La garantía se constituye por vale a la vista o transferencia, fuera de la plataforma.</p>
    @endunless

    <div class="acceso__contacto">
        <a href="mailto:{{ \App\Support\Sitio::correo() }}">{{ \App\Support\Sitio::correo() }}</a>
        <a href="{{ \App\Support\Sitio::telefonoEnlace() }}">{{ \App\Support\Sitio::telefono() }}</a>
        @if ($administracion)
            <a href="{{ route('login') }}" class="acceso__contacto-admin">Acceso de postores</a>
        @else
            <a href="{{ route('admin.ingresar') }}" class="acceso__contacto-admin">Acceso administradores</a>
        @endif
    </div>
</x-acceso.marco>
