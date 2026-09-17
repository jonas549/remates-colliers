<?php

namespace App\Notifications;

use App\Models\Garantia;
use App\Support\Formato;
use App\Support\Sitio;
use Illuminate\Database\Eloquent\Model;

/** Colliers recibió el comprobante (confirmación al postor). */
class ComprobanteRecibidoAviso extends AvisoColliers
{
    public function __construct(public Garantia $garantia) {}

    public function plantilla(): string
    {
        return 'comprobante_recibido';
    }

    public function notificable(): ?Model
    {
        return $this->garantia;
    }

    protected function datos(?object $destinatario = null): array
    {
        return [
            'remate' => $this->garantia->remate->titulo,
            'folio' => $this->garantia->remate->folio,
            'monto' => Formato::clp($this->garantia->monto),
            'horas' => Sitio::horasRevision(),
        ];
    }

    protected function enlace(?object $destinatario = null): ?string
    {
        return route('cuenta.estado', ['remate' => $this->garantia->remate->slug]);
    }
}
