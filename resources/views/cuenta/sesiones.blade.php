{{-- Pantalla sin diseño propio: reutiliza el diseño del Login (decisión del 16/09). --}}
@php
    $zona = config('colliers.zona_visualizacion');
    $destino = auth()->user()->esAdministracion() ? route('admin.dashboard') : route('cuenta.estado');
    $navegador = function (?string $agente): string {
        $agente = (string) $agente;
        $sistema = match (true) {
            str_contains($agente, 'iPhone') || str_contains($agente, 'iPad') => 'iPhone/iPad',
            str_contains($agente, 'Android') => 'Android',
            str_contains($agente, 'Windows') => 'Windows',
            str_contains($agente, 'Mac OS') => 'Mac',
            default => 'Otro dispositivo',
        };
        $programa = match (true) {
            str_contains($agente, 'Edg/') => 'Edge',
            str_contains($agente, 'Chrome/') => 'Chrome',
            str_contains($agente, 'Firefox/') => 'Firefox',
            str_contains($agente, 'Safari/') => 'Safari',
            default => 'navegador',
        };

        return "{$programa} en {$sistema}";
    };
@endphp
<x-acceso.marco
    pagina="Sesiones activas"
    kicker="SEGURIDAD DE LA CUENTA"
    titulo="Sesiones activas"
    intro="Dispositivos donde tu cuenta tiene la sesión abierta. Si no reconoces alguno, ciérralo y cambia tu contraseña."
    :proximo="$proximo"
>
    @if (session('estado'))
        <p class="acceso__intro">{{ session('estado') }}</p>
    @endif

    @if (! $disponible)
        <p class="acceso__nota">La lista de sesiones requiere SESSION_DRIVER=database.</p>
    @else
        <div class="acceso__campos">
            @foreach ($sesiones as $sesion)
                {{-- --clave: da el punto de referencia al botón CERRAR, igual que MOSTRAR en el Login. --}}
                <div class="acceso__campo acceso__campo--clave">
                    <span class="acceso__etiqueta">{{ $sesion->actual ? 'ESTE DISPOSITIVO' : 'ÚLTIMA ACTIVIDAD ' . $sesion->ultima_actividad->setTimezone($zona)->format('d-m-Y H:i') }}</span>
                    <span class="acceso__input">{{ $navegador($sesion->navegador) }} · {{ $sesion->ip }}</span>
                    @unless ($sesion->actual)
                        <form method="POST" action="{{ route('cuenta.sesiones.cerrar', $sesion->id) }}" class="formulario-en-linea">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="acceso__ver-clave">CERRAR</button>
                        </form>
                    @endunless
                </div>
            @endforeach
        </div>

        <div class="acceso__opciones">
            <a href="{{ $destino }}" class="acceso__olvide">{{ auth()->user()->esAdministracion() ? 'Volver al panel' : 'Volver a mi cuenta' }}</a>
        </div>

        @if ($sesiones->where('actual', false)->isNotEmpty())
            <form method="POST" action="{{ route('cuenta.sesiones.cerrar-otras') }}">
                @csrf
                @method('DELETE')
                <button type="submit" class="acceso__entrar es-listo">Cerrar las demás sesiones</button>
            </form>
        @endif
    @endif

    <div class="acceso__contacto">
        <a href="{{ route('cuenta.clave') }}">Cambiar contraseña</a>
        <form method="POST" action="{{ route('logout') }}" class="formulario-en-linea">
            @csrf
            <button type="submit" class="boton-enlace acceso__contacto-admin">Cerrar sesión</button>
        </form>
    </div>
</x-acceso.marco>
