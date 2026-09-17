<?php

namespace App\Notifications;

use App\Support\Sitio;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;

/**
 * Base de los correos de Remates Colliers (Bloque M). Todos van por la cola, que procesa el cron cada minuto
 * (`queue:work --stop-when-empty`), y quedan en `notificaciones_log` (RegistroDeNotificaciones). Español, sin imágenes
 * externas, con el contacto de Configuración al pie.
 */
abstract class AvisoColliers extends Notification implements ShouldQueue
{
    // SerializesModels: en la cola viajan los id y el correo usa los datos vigentes al enviarse.
    use Queueable, SerializesModels;

    /** Reintentos si el servidor de correo falla (la cola los espacia 60 s). */
    public int $tries = 3;

    abstract public function asunto(): string;

    /** Registro al que se refiere el correo (postor, garantía, remate, adjudicación), para la bitácora. */
    abstract public function notificable(): ?Model;

    /** @param MailMessage $correo mensaje con saludo y asunto ya puestos */
    abstract protected function contenido(MailMessage $correo, object $destinatario): MailMessage;

    public function via(object $destinatario): array
    {
        return ['mail'];
    }

    public function toMail(object $destinatario): MailMessage
    {
        $nombre = $destinatario->name ?? null;
        $correo = (new MailMessage)
            ->subject($this->asunto() . ' · Remates Colliers')
            ->greeting($nombre ? "Hola {$nombre}" : 'Hola');

        return $this->contenido($correo, $destinatario)
            ->salutation('Colliers Chile · ' . Sitio::correo() . (Sitio::telefono() ? ' · ' . Sitio::telefono() : ''));
    }
}
