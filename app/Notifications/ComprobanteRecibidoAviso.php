<?php

namespace App\Notifications;

use App\Models\Garantia;
use App\Support\Formato;
use App\Support\Sitio;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Messages\MailMessage;

/** Colliers recibió el comprobante (confirmación al postor). */
class ComprobanteRecibidoAviso extends AvisoColliers
{
    public function __construct(public Garantia $garantia) {}

    public function asunto(): string
    {
        return 'Recibimos tu comprobante de garantía';
    }

    public function notificable(): ?Model
    {
        return $this->garantia;
    }

    protected function contenido(MailMessage $correo, object $destinatario): MailMessage
    {
        $remate = $this->garantia->remate;

        return $correo->line("Recibimos el comprobante de tu garantía por " . Formato::clp($this->garantia->monto) . " para {$remate->titulo} ({$remate->folio}).")
            ->line('Colliers lo revisa de forma manual, normalmente dentro de ' . Sitio::horasRevision() . ' horas hábiles. Te avisaremos por correo cuando quede aprobada.')
            ->action('Ver el estado de mi garantía', route('cuenta.estado', ['remate' => $remate->slug]));
    }
}
