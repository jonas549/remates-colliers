<?php

namespace App\Events;

use App\Models\Remate;
use Illuminate\Foundation\Events\Dispatchable;

/** Un remate pasó de borrador a publicado. El Bloque M avisa a los suscriptores. */
class RematePublicado
{
    use Dispatchable;

    public function __construct(public readonly Remate $remate) {}
}
