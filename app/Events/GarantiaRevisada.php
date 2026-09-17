<?php

namespace App\Events;

use App\Models\Garantia;
use Illuminate\Foundation\Events\Dispatchable;

/** Colliers aprobó o rechazó la garantía de un postor para un remate. El Bloque M le envía el correo. */
class GarantiaRevisada
{
    use Dispatchable;

    public function __construct(public readonly Garantia $garantia) {}
}
