<?php

namespace App\Notifications;

use App\Models\Garantia;
use App\Support\Formato;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Messages\MailMessage;

/** Garantía aprobada o rechazada. */
class GarantiaRevisadaAviso extends AvisoColliers
{
    public function __construct(public Garantia $garantia) {}

    public function asunto(): string
    {
        return $this->garantia->estado === Garantia::ESTADO_APROBADA ? 'Tu garantía fue aprobada' : 'No pudimos validar tu garantía';
    }

    public function notificable(): ?Model
    {
        return $this->garantia;
    }

    protected function contenido(MailMessage $correo, object $destinatario): MailMessage
    {
        $remate = $this->garantia->remate;
        $monto = Formato::clp($this->garantia->monto);

        if ($this->garantia->estado === Garantia::ESTADO_APROBADA) {
            return $correo->line("Tu garantía por {$monto} para {$remate->titulo} ({$remate->folio}) fue aprobada.")
                ->line('Quedas habilitado para pujar en este remate. La sala de pujas se abre el ' . Formato::fecha($remate->abreEn()) . ' (hora de Chile), junto con la transmisión.')
                ->line('El precio y el cronómetro de la plataforma son los oficiales: el video tiene 10 a 30 segundos de retraso.')
                ->action('Ir a mi cuenta', route('cuenta.estado', ['remate' => $remate->slug]));
        }

        return $correo->line("No pudimos validar tu garantía por {$monto} para {$remate->titulo} ({$remate->folio}).")
            ->line('Motivo: ' . ($this->garantia->motivo_rechazo ?: 'no indicado') . '.')
            ->line('Puedes corregirla y volver a enviar el comprobante' . ($remate->cierre_garantias_en ? ' hasta el ' . Formato::fecha($remate->cierre_garantias_en) : '') . '.')
            ->action('Enviar el comprobante de nuevo', route('cuenta.estado', ['remate' => $remate->slug]));
    }
}
