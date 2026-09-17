<?php

namespace App\Listeners;

use App\Models\NotificacionLog;
use App\Models\User;
use App\Notifications\AvisoColliers;
use Illuminate\Events\Dispatcher;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Events\NotificationFailed;
use Illuminate\Notifications\Events\NotificationSent;
use Throwable;

/** Bitácora `notificaciones_log` (Bloque M): cada correo enviado o fallido, con destinatario, asunto y registro relacionado. */
class RegistroDeNotificaciones
{
    public function subscribe(Dispatcher $eventos): array
    {
        return [
            NotificationSent::class => 'enviada',
            NotificationFailed::class => 'fallida',
        ];
    }

    public function enviada(NotificationSent $evento): void
    {
        $this->anotar($evento->notifiable, $evento->notification, 'enviada', null);
    }

    public function fallida(NotificationFailed $evento): void
    {
        $error = $evento->data['exception'] ?? null;
        $this->anotar($evento->notifiable, $evento->notification, 'fallida', $error instanceof Throwable ? $error->getMessage() : null);
    }

    private function anotar(object $destinatario, object $notificacion, string $estado, ?string $error): void
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
            // Un recordatorio se anota «pendiente» al encolarlo (evita duplicados): al enviarse se actualiza esa fila.
            $fila = NotificacionLog::where($datos)->where('estado', 'pendiente')->first() ?? new NotificacionLog($datos);
            $fila->fill([
                'estado' => $estado,
                'error' => $error === null ? null : mb_substr($error, 0, 2000),
                'enviada_en' => $estado === 'enviada' ? now('UTC') : null,
            ])->save();
        } catch (Throwable $e) {
            report($e);
        }
    }
}
