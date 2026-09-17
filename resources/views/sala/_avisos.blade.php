{{--
    Avisos de la sala conectada al motor (no están en el diseño; mínimos, con los tokens de la sala):
    mensaje del martillero y cómo terminó el lote anterior cuando el remate tiene varios lotes.
    $donde: panel (escritorio, dentro del panel de puja) | contenido (bajo 1120px, sobre el video).
--}}
<template x-if="mensajeMartillero || avisoLote">
    <div class="sala-avisos sala-avisos--{{ $donde }}" aria-live="polite">
        <template x-if="mensajeMartillero">
            <div class="sala-aviso sala-aviso--martillero">
                <div class="sala-aviso__etiqueta">MENSAJE DEL MARTILLERO <span x-text="mensajeHace"></span></div>
                <div class="sala-aviso__texto" x-text="mensajeMartillero"></div>
            </div>
        </template>
        <template x-if="avisoLote">
            <div class="sala-aviso">
                <div class="sala-aviso__etiqueta" x-text="etiquetaLote.toUpperCase()"></div>
                <div class="sala-aviso__texto" x-text="avisoLote"></div>
            </div>
        </template>
    </div>
</template>
