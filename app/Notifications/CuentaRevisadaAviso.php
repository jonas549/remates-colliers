<?php

namespace App\Notifications;

use App\Models\Postor;
use App\Support\Sitio;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Messages\MailMessage;

/** Cuenta aprobada, rechazada, bloqueada o desbloqueada. */
class CuentaRevisadaAviso extends AvisoColliers
{
    public function __construct(public Postor $postor, public string $accion) {}

    public function asunto(): string
    {
        return match ($this->accion) {
            'cuenta_aprobada' => 'Tu cuenta fue aprobada',
            'cuenta_rechazada' => 'No pudimos aprobar tu cuenta',
            'cuenta_bloqueada' => 'Tu cuenta fue bloqueada',
            default => 'Tu cuenta fue desbloqueada',
        };
    }

    public function notificable(): ?Model
    {
        return $this->postor;
    }

    protected function contenido(MailMessage $correo, object $destinatario): MailMessage
    {
        return match ($this->accion) {
            'cuenta_aprobada' => $correo->line('Colliers revisó tus antecedentes y aprobó tu cuenta de postor.')
                ->line('Ya puedes inscribirte en un remate: al hacerlo verás el monto de la garantía y cómo constituirla (vale a la vista o transferencia, fuera de la plataforma).')
                ->action('Ver remates publicados', route('remates.index')),
            'cuenta_rechazada' => $correo->line('Colliers revisó tus antecedentes y no aprobó tu cuenta.')
                ->line('Motivo: ' . ($this->postor->motivo_rechazo ?: 'no indicado') . '.')
                ->line('Si quieres corregir algún dato o enviar documentos, responde a este correo o escríbenos a ' . Sitio::correo() . '.'),
            'cuenta_bloqueada' => $correo->line('Tu cuenta quedó bloqueada: no puedes inscribirte ni pujar mientras se revisa.')
                ->line('Motivo: ' . ($this->postor->motivo_rechazo ?: 'no indicado') . '.')
                ->line('Escríbenos a ' . Sitio::correo() . ' para más información.'),
            default => $correo->line('Tu cuenta fue desbloqueada: ya puedes volver a inscribirte y pujar.')
                ->action('Ir a mi cuenta', route('cuenta.estado')),
        };
    }
}
