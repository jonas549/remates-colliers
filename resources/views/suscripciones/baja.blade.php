{{-- Baja de «Avísame» (Bloque M). Sin diseño propio: la misma caja de las páginas de sistema. --}}
<x-layouts.base titulo="Avisos desactivados">
    <div class="acceso-sandbox">
        <x-tramite.cabecera etiqueta="REMATES" />

        <div class="acceso-sandbox__cuerpo">
            <div class="acceso-sandbox__caja">
                <div class="acceso-sandbox__kicker">AVISOS POR CORREO</div>
                <h1 class="acceso-sandbox__titulo">Listo, no te enviaremos más avisos</h1>
                <p class="acceso-sandbox__texto">
                    {{ $suscripcion->email }} ya no recibirá {{ $suscripcion->remate ? 'el recordatorio del remate ' . $suscripcion->remate->folio : 'avisos de remates nuevos' }}.
                    Puedes volver a suscribirte cuando quieras desde el sitio.
                </p>
                <a href="{{ route('remates.index') }}" class="acceso-sandbox__boton acceso-sandbox__boton--enlace">Ver los remates</a>
            </div>
        </div>

        <x-tramite.pie />
    </div>
</x-layouts.base>
