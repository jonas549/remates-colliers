<?php

namespace App\Events;

use App\Models\Garantia;
use Illuminate\Foundation\Events\Dispatchable;

/** Un postor subió el comprobante de su garantía: queda en revisión. */
class ComprobanteRecibido
{
    use Dispatchable;

    public function __construct(public readonly Garantia $garantia) {}
}
