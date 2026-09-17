<?php

namespace App\Notifications;

use App\Models\Adjudicacion;
use App\Support\Formato;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Messages\MailMessage;

/** Al adjudicatario, cuando el lote se liquida a su favor. */
class AdjudicacionAviso extends AvisoColliers
{
    public function __construct(public Adjudicacion $adjudicacion) {}

    public function asunto(): string
    {
        return 'Te adjudicaste la propiedad';
    }

    public function notificable(): ?Model
    {
        return $this->adjudicacion;
    }

    protected function contenido(MailMessage $correo, object $destinatario): MailMessage
    {
        $lote = $this->adjudicacion->lote;

        return $correo->line('Tu postura fue la más alta al cierre del remate ' . $lote->remate->folio . '.')
            ->line('Propiedad: ' . ($lote->direccion ?: $lote->titulo) . '.')
            ->line('Precio de adjudicación: ' . Formato::clp($this->adjudicacion->monto) . '.')
            ->line('Un ejecutivo de Colliers te contactará para la firma y el pago del saldo, según las bases del remate.');
    }
}
