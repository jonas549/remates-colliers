<?php

namespace App\Notifications;

use App\Models\Garantia;
use App\Support\Formato;
use Illuminate\Database\Eloquent\Model;

/** Garantía aprobada o rechazada. */
class GarantiaRevisadaAviso extends AvisoColliers
{
    public function __construct(public Garantia $garantia) {}

    public function plantilla(): string
    {
        return $this->garantia->estado === Garantia::ESTADO_APROBADA ? 'garantia_aprobada' : 'garantia_rechazada';
    }

    public function notificable(): ?Model
    {
        return $this->garantia;
    }

    protected function datos(?object $destinatario = null): array
    {
        $remate = $this->garantia->remate;

        return [
            'remate' => $remate->titulo,
            'folio' => $remate->folio,
            'monto' => Formato::clp($this->garantia->monto),
            'inicio' => Formato::fecha($remate->abreEn()),
            'motivo' => $this->garantia->motivo_rechazo ?: 'no indicado',
        ];
    }

    protected function enlace(?object $destinatario = null): ?string
    {
        return route('cuenta.estado', ['remate' => $this->garantia->remate->slug]);
    }
}
