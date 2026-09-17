<?php

namespace App\Notifications;

use App\Correo\Plantillas;
use App\Support\Sitio;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;

/**
 * Base de los correos de Remates Colliers (Bloque M). Todos van por la cola, que procesa el cron cada minuto
 * (`queue:work --stop-when-empty`), y quedan en `notificaciones_log` (RegistroDeNotificaciones).
 *
 * Desde el 17/09 el TEXTO sale de las plantillas editables del panel (`App\Correo\Plantillas`): cada aviso solo aporta
 * su plantilla, sus variables y el enlace del botón (que depende del remate o de la cuenta y no se edita).
 */
abstract class AvisoColliers extends Notification implements ShouldQueue
{
    // SerializesModels: en la cola viajan los id y el correo usa los datos vigentes al enviarse.
    use Queueable, SerializesModels;

    /** Reintentos si el servidor de correo falla (la cola los espacia 60 s). */
    public int $tries = 3;

    /** Clave en App\Correo\Plantillas::CATALOGO. */
    abstract public function plantilla(): string;

    /** Registro al que se refiere el correo (postor, garantía, remate, adjudicación), para la bitácora. */
    abstract public function notificable(): ?Model;

    /** Variables propias de este aviso. @return array<string, string|int|null> */
    abstract protected function datos(?object $destinatario = null): array;

    /** Destino del botón, si la plantilla tiene uno. */
    protected function enlace(?object $destinatario = null): ?string
    {
        return null;
    }

    public function asunto(): string
    {
        return Plantillas::render($this->plantilla(), $this->datos())['asunto'];
    }

    public function via(object $destinatario): array
    {
        return ['mail'];
    }

    public function toMail(object $destinatario): MailMessage
    {
        $nombre = $destinatario->name ?? null;
        $texto = Plantillas::render($this->plantilla(), ['nombre' => $nombre] + $this->datos($destinatario));

        $correo = (new MailMessage)
            ->subject($texto['asunto'] . ' · Remates Colliers')
            ->greeting($nombre ? "Hola {$nombre}" : 'Hola');

        foreach ($texto['parrafos'] as $parrafo) {
            $correo->line($parrafo);
        }

        $enlace = $this->enlace($destinatario);
        if ($texto['boton'] !== null && $enlace !== null) {
            $correo->action($texto['boton'], $enlace);
        }

        return $this->pie($correo, $destinatario)
            ->salutation('Colliers Chile · ' . Sitio::correo() . (Sitio::telefono() ? ' · ' . Sitio::telefono() : ''));
    }

    /** Línea final propia del aviso (por ejemplo, la baja de los suscriptores). */
    protected function pie(MailMessage $correo, object $destinatario): MailMessage
    {
        return $correo;
    }
}
