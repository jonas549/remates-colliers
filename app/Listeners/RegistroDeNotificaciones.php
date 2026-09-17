<?php

namespace App\Listeners;

use App\Correo\DiagnosticoSmtp;
use App\Models\NotificacionLog;
use App\Models\User;
use App\Notifications\AvisoColliers;
use Illuminate\Events\Dispatcher;
use Illuminate\Mail\SentMessage;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Events\NotificationFailed;
use Illuminate\Notifications\Events\NotificationSent;
use Throwable;

/**
 * Bitácora `notificaciones_log` (Bloque M): cada correo, con lo que respondió el transporte.
 *
 * El estado dice lo que de verdad pasó (17/09, corrección pedida por Jonas):
 *   - `aceptada`: el servidor de salida aceptó el mensaje (se guarda su respuesta literal y el Message-ID).
 *     Aceptar no es entregar: puede rebotar o filtrarse después.
 *   - `registrada`: el transporte era «log» o «array»: el correo quedó en un archivo y NO salió a Internet.
 *   - `fallida`: el transporte lanzó un error, que se guarda entero.
 *   - `pendiente`: encolada, todavía sin respuesta del transporte.
 */
class RegistroDeNotificaciones
{
    /** Transportes que no envían nada a Internet. */
    private const SIN_SALIDA = ['log', 'array', 'null'];

    public function subscribe(Dispatcher $eventos): array
    {
        return [
            NotificationSent::class => 'enviada',
            NotificationFailed::class => 'fallida',
        ];
    }

    public function enviada(NotificationSent $evento): void
    {
        $transporte = (string) config('mail.default');
        $enviado = $evento->response instanceof SentMessage ? $evento->response->getSymfonySentMessage() : null;
        $transcripcion = (string) $enviado?->getDebug();

        $this->anotar($evento->notifiable, $evento->notification, [
            'estado' => match (true) {
                in_array($transporte, self::SIN_SALIDA, true) => 'registrada',
                $enviado !== null => 'aceptada',
                // El canal no devolvió nada: no hay con qué afirmar que salió.
                default => 'sin_verificar',
            },
            'transporte' => $transporte,
            'respuesta' => $this->recortar(DiagnosticoSmtp::respuestaFinal($transcripcion), 500),
            'message_id' => $enviado?->getMessageId(),
            'remitente' => $this->remitente($enviado),
            'error' => null,
        ]);
    }

    public function fallida(NotificationFailed $evento): void
    {
        $error = $evento->data['exception'] ?? null;
        $texto = $error instanceof Throwable ? $error->getMessage() : null;
        $transcripcion = $error instanceof Throwable && method_exists($error, 'getDebug') ? (string) $error->getDebug() : '';

        $this->anotar($evento->notifiable, $evento->notification, [
            'estado' => 'fallida',
            'transporte' => (string) config('mail.default'),
            'respuesta' => $this->recortar(DiagnosticoSmtp::respuestaFinal($transcripcion), 500),
            'message_id' => null,
            'remitente' => (string) config('mail.from.address') ?: null,
            'error' => $this->recortar(trim($texto . ($transcripcion ? "\n\nConversación con el servidor:\n" . $transcripcion : '')), 2000),
        ]);
    }

    /** @param array<string, mixed> $resultado */
    private function anotar(object $destinatario, object $notificacion, array $resultado): void
    {
        $notificable = $notificacion instanceof AvisoColliers ? $notificacion->notificable() : null;
        $datos = [
            'user_id' => $destinatario instanceof User ? $destinatario->id : null,
            'canal' => 'correo',
            'tipo' => class_basename($notificacion),
            'destinatario' => mb_substr((string) ($destinatario instanceof AnonymousNotifiable
                ? ($destinatario->routes['mail'] ?? '') : ($destinatario->routeNotificationFor('mail') ?? $destinatario->email ?? '')), 0, 255),
            'asunto' => $notificacion instanceof AvisoColliers ? mb_substr($notificacion->asunto(), 0, 255) : null,
            'notificable_type' => $notificable?->getMorphClass(),
            'notificable_id' => $notificable?->getKey(),
        ];
        try {
            // Un recordatorio se anota «pendiente» al encolarlo (evita duplicados): al resolverse se actualiza esa fila.
            $fila = NotificacionLog::where($datos)->where('estado', 'pendiente')->first() ?? new NotificacionLog($datos);
            $fila->fill($resultado + ['enviada_en' => $resultado['estado'] === 'aceptada' ? now('UTC') : null])->save();
        } catch (Throwable $e) {
            report($e);
        }
    }

    private function remitente(?\Symfony\Component\Mailer\SentMessage $enviado): ?string
    {
        $de = $enviado?->getEnvelope()->getSender()->getAddress();

        return $de ?: ((string) config('mail.from.address') ?: null);
    }

    private function recortar(?string $texto, int $largo): ?string
    {
        return blank($texto) ? null : mb_substr($texto, 0, $largo);
    }
}
