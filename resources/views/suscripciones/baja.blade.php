{{-- Baja de «Avísame» (Bloque M). Sin diseño propio: la misma caja de las páginas de sistema. --}}
<x-layouts.base :titulo="__('cuenta.avisame.baja_titulo_pagina')">
    <div class="acceso-sandbox">
        <x-tramite.cabecera :etiqueta="__('cuenta.avisame.baja_etiqueta')" />

        <div class="acceso-sandbox__cuerpo">
            <div class="acceso-sandbox__caja">
                <div class="acceso-sandbox__kicker">{{ __('cuenta.avisame.baja_kicker') }}</div>
                <h1 class="acceso-sandbox__titulo">{{ __('cuenta.avisame.baja_titulo') }}</h1>
                <p class="acceso-sandbox__texto">
                    {{ $suscripcion->remate ? __('cuenta.avisame.baja_recordatorio', ['email' => $suscripcion->email, 'folio' => $suscripcion->remate->folio]) : __('cuenta.avisame.baja_nuevos', ['email' => $suscripcion->email]) }}
                    {{ __('cuenta.avisame.baja_volver') }}
                </p>
                <a href="{{ route('remates.index') }}" class="acceso-sandbox__boton acceso-sandbox__boton--enlace">{{ __('cuenta.avisame.baja_ver_remates') }}</a>
            </div>
        </div>

        <x-tramite.pie />
    </div>
</x-layouts.base>
