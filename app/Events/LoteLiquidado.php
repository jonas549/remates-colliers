<?php

namespace App\Events;

use App\Models\Lote;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Un lote quedó adjudicado o desierto. El Bloque M le agrega los avisos al adjudicatario y al
 * administrador (por la cola del cron; la difusión en tiempo real ya ocurrió de forma síncrona).
 */
class LoteLiquidado
{
    use Dispatchable;

    public function __construct(public readonly Lote $lote) {}
}
