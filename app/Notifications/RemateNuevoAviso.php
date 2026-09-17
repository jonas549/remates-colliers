<?php

namespace App\Notifications;

use App\Models\Remate;
use App\Models\Suscripcion;
use App\Support\Formato;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Messages\MailMessage;

/** A suscriptores: se publicó un remate nuevo. */
class RemateNuevoAviso extends AvisoColliers
{
    public function __construct(public Remate $remate) {}

    public function plantilla(): string
    {
        return 'remate_nuevo';
    }

    public function notificable(): ?Model
    {
        return $this->remate;
    }

    protected function datos(?object $destinatario = null): array
    {
        return [
            'remate' => $this->remate->titulo,
            'folio' => $this->remate->folio,
            'inicio' => Formato::fecha($this->remate->abreEn()),
            'base' => Formato::clp((int) $this->remate->lotes()->sum('precio_base')),
            'baja' => $destinatario instanceof Suscripcion ? route('suscripciones.baja', $destinatario->token) : '',
        ];
    }

    protected function enlace(?object $destinatario = null): ?string
    {
        return route('remates.show', $this->remate->slug);
    }

    protected function pie(MailMessage $correo, object $destinatario): MailMessage
    {
        return self::baja($correo, $destinatario);
    }

    /** Enlace de baja al pie, solo para suscriptores. */
    public static function baja(MailMessage $correo, object $destinatario): MailMessage
    {
        return $destinatario instanceof Suscripcion
            ? $correo->line('Recibes este correo porque pediste avisos de remates. [Dejar de recibirlos](' . route('suscripciones.baja', $destinatario->token) . ').')
            : $correo;
    }
}
