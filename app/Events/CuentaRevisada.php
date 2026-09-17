<?php

namespace App\Events;

use App\Models\Postor;
use Illuminate\Foundation\Events\Dispatchable;

/** Colliers aprobó, rechazó, bloqueó o desbloqueó la cuenta de un postor. El Bloque M le envía el correo. */
class CuentaRevisada
{
    use Dispatchable;

    public function __construct(public readonly Postor $postor, public readonly string $accion) {}
}
