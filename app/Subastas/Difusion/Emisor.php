<?php

namespace App\Subastas\Difusion;

use App\Models\Remate;

/**
 * Capa de difusión en tiempo real, abstraída (CLAUDE.md §5). La base de datos es la fuente de verdad;
 * el emisor solo transmite el estado ya confirmado. Implementación actual: JSON estático servido por
 * LiteSpeed. Para pasar a Pusher basta otra implementación de esta interfaz.
 *
 * Se llama de forma SÍNCRONA después del commit, nunca por la cola del cron.
 * Un fallo al emitir no revierte la puja: se registra y el siguiente evento vuelve a publicar el estado completo.
 */
interface Emisor
{
    public function publicarEstado(Remate $remate): void;
}
