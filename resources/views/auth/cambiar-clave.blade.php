{{-- Pantalla sin diseño propio: reutiliza el diseño del Login (decisión del 16/09). --}}
@php
    $errores = $errors->getBag('updatePassword');
    $destino = auth()->user()->esAdministracion() ? route('admin.dashboard') : route('cuenta.estado');
@endphp
<x-acceso.marco
    pagina="Cambiar contraseña"
    kicker="SEGURIDAD DE LA CUENTA"
    :titulo="$obligatorio ? 'Cambia tu contraseña para continuar' : 'Cambia tu contraseña'"
    :intro="$obligatorio ? 'Tu contraseña es temporal. Crea una nueva antes de usar la plataforma.' : 'Al guardarla cerraremos tus sesiones abiertas en otros dispositivos.'"
    :proximo="$proximo"
    alpine="{ ver: false }"
>
    @if (session('status') === 'password-updated')
        <p class="acceso__intro">Contraseña actualizada. Cerramos tus sesiones en otros dispositivos.</p>
        <form method="GET" action="{{ $destino }}">
            <button type="submit" class="acceso__entrar es-listo">Continuar</button>
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
                    <span class="acceso__etiqueta">CONTRASEÑA ACTUAL</span>
                    <input name="current_password" :type="ver ? 'text' : 'password'" type="password" class="acceso__input" autocomplete="current-password" required>
                    <button type="button" class="acceso__ver-clave" @click="ver = !ver" x-text="ver ? 'OCULTAR' : 'MOSTRAR'">MOSTRAR</button>
                </label>
                <label class="acceso__campo">
                    <span class="acceso__etiqueta">NUEVA CONTRASEÑA</span>
                    <input name="password" :type="ver ? 'text' : 'password'" type="password" placeholder="Mínimo 8 caracteres" class="acceso__input" autocomplete="new-password" required>
                </label>
                <label class="acceso__campo">
                    <span class="acceso__etiqueta">REPETIR CONTRASEÑA</span>
                    <input name="password_confirmation" :type="ver ? 'text' : 'password'" type="password" placeholder="Repite la contraseña" class="acceso__input" autocomplete="new-password" required>
                </label>
            </div>

            <div class="acceso__opciones">
                @unless ($obligatorio)
                    <a href="{{ $destino }}" class="acceso__olvide">{{ auth()->user()->esAdministracion() ? 'Volver al panel' : 'Volver a mi cuenta' }}</a>
                @endunless
            </div>

            <button type="submit" class="acceso__entrar es-listo">Guardar contraseña</button>
        </form>
    @endif

    <div class="acceso__contacto">
        <a href="{{ route('cuenta.sesiones') }}">Sesiones activas</a>
        <form method="POST" action="{{ route('logout') }}" class="formulario-en-linea">
            @csrf
            <button type="submit" class="boton-enlace acceso__contacto-admin">Cerrar sesión</button>
        </form>
    </div>
</x-acceso.marco>
