<?php

namespace App\Notifications;

use App\Models\Remate;
use App\Support\Formato;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Recordatorio antes del remate. `motivo`:
 * inicio (a inscritos con garantía aprobada y a quienes pidieron «Avísame» de ese remate),
 * garantia (a inscritos con la garantía sin aprobar),
 * cierre_garantias (a suscriptores generales, antes del cierre de garantías).
 */
class RecordatorioRemateAviso extends AvisoColliers
{
    public function __construct(public Remate $remate, public string $motivo) {}

    public function asunto(): string
    {
        return match ($this->motivo) {
            'garantia' => 'Falta aprobar tu garantía: ' . $this->remate->folio,
            'cierre_garantias' => 'Cierra el plazo de garantías: ' . $this->remate->titulo,
            default => 'El remate comienza pronto: ' . $this->remate->titulo,
        };
    }

    public function notificable(): ?Model
    {
        return $this->remate;
    }

    protected function contenido(MailMessage $correo, object $destinatario): MailMessage
    {
        $r = $this->remate;
        $inicio = Formato::fecha($r->abreEn());

        $correo = match ($this->motivo) {
            'garantia' => $correo->line("El remate {$r->folio} ({$r->titulo}) comienza el {$inicio} y tu garantía todavía no está aprobada.")
                ->line($r->cierre_garantias_en ? 'El plazo para constituirla cierra el ' . Formato::fecha($r->cierre_garantias_en) . '.' : 'Constitúyela antes del inicio.')
                ->action('Ver mi garantía', route('cuenta.estado', ['remate' => $r->slug])),
            'cierre_garantias' => $correo->line("El plazo para constituir la garantía del remate {$r->folio} ({$r->titulo}) cierra el " . Formato::fecha($r->cierre_garantias_en) . '.')
                ->line("El remate comienza el {$inicio}.")
                ->action('Ver el remate', route('remates.show', $r->slug)),
            default => $correo->line("El remate {$r->folio} ({$r->titulo}) comienza el {$inicio} (hora de Chile).")
                ->line('Se transmite en vivo y cierra automáticamente al vencer el tiempo, sin extensiones.')
                ->action('Ver el remate', route('remates.show', $r->slug)),
        };

        return RemateNuevoAviso::baja($correo, $destinatario);
    }
}
