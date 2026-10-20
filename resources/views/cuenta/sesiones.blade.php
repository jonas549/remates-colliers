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
            default => __('cuenta.sesiones.otro_dispositivo'),
        };
        $programa = match (true) {
            str_contains($agente, 'Edg/') => 'Edge',
            str_contains($agente, 'Chrome/') => 'Chrome',
            str_contains($agente, 'Firefox/') => 'Firefox',
            str_contains($agente, 'Safari/') => 'Safari',
            default => __('cuenta.sesiones.navegador'),
        };

        return __('cuenta.sesiones.programa_en_sistema', ['programa' => $programa, 'sistema' => $sistema]);
    };
@endphp
<x-acceso.marco
    :pagina="__('cuenta.sesiones.titulo')"
    :kicker="__('cuenta.sesiones.kicker')"
    :titulo="__('cuenta.sesiones.titulo')"
    :intro="__('cuenta.sesiones.intro')"
    :proximo="$proximo"
>
    @if (session('estado'))
        <p class="acceso__intro">{{ session('estado') }}</p>
    @endif

    @if (! $disponible)
        <p class="acceso__nota">{{ __('cuenta.sesiones.requiere_base') }}</p>
    @else
        <div class="acceso__campos">
            @foreach ($sesiones as $sesion)
                {{-- --clave: da el punto de referencia al botón CERRAR, igual que MOSTRAR en el Login. --}}
                <div class="acceso__campo acceso__campo--clave">
                    <span class="acceso__etiqueta">{{ $sesion->actual ? __('cuenta.sesiones.este_dispositivo') : __('cuenta.sesiones.ultima_actividad', ['fecha' => $sesion->ultima_actividad->setTimezone($zona)->format('d-m-Y H:i')]) }}</span>
                    <span class="acceso__input">{{ $navegador($sesion->navegador) }} · {{ $sesion->ip }}</span>
                    @unless ($sesion->actual)
                        <form method="POST" action="{{ route('cuenta.sesiones.cerrar', $sesion->id) }}" class="formulario-en-linea">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="acceso__ver-clave">{{ __('cuenta.sesiones.cerrar') }}</button>
                        </form>
                    @endunless
                </div>
            @endforeach
        </div>

        <div class="acceso__opciones">
            <a href="{{ $destino }}" class="acceso__olvide">{{ __(auth()->user()->esAdministracion() ? 'cuenta.sesiones.volver_panel' : 'cuenta.sesiones.volver_cuenta') }}</a>
        </div>

        @if ($sesiones->where('actual', false)->isNotEmpty())
            <form method="POST" action="{{ route('cuenta.sesiones.cerrar-otras') }}">
                @csrf
                @method('DELETE')
                <button type="submit" class="acceso__entrar es-listo">{{ __('cuenta.sesiones.cerrar_demas') }}</button>
            </form>
        @endif
    @endif

    <div class="acceso__contacto">
        <a href="{{ route('cuenta.clave') }}">{{ __('cuenta.sesiones.cambiar_clave') }}</a>
        <form method="POST" action="{{ route('logout') }}" class="formulario-en-linea">
            @csrf
            <button type="submit" class="boton-enlace acceso__contacto-admin">{{ __('cuenta.sesiones.cerrar_sesion') }}</button>
        </form>
    </div>
</x-acceso.marco>
