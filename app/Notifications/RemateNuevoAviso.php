<?php

namespace App\Notifications;

use App\Models\Garantia;
use App\Models\Remate;
use App\Models\Suscripcion;
use App\Support\Formato;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Messages\MailMessage;

/** A suscriptores: se publicó un remate nuevo. */
class RemateNuevoAviso extends AvisoColliers
{
    public function __construct(public Remate $remate) {}

    public function asunto(): string
    {
        return 'Nuevo remate: ' . $this->remate->titulo;
    }

    public function notificable(): ?Model
    {
        return $this->remate;
    }

    protected function contenido(MailMessage $correo, object $destinatario): MailMessage
    {
        $r = $this->remate;

        return self::baja($correo->line("Colliers publicó el remate {$r->folio}: {$r->titulo}.")
            ->line('Comienza el ' . Formato::fecha($r->abreEn()) . '. Precio base ' . Formato::clp((int) $r->lotes()->sum('precio_base')) . ' · garantía ' . Formato::clp($r->montoGarantia()) . '.')
            ->line($r->cierre_garantias_en ? 'La garantía debe estar aprobada a más tardar el ' . Formato::fecha($r->cierre_garantias_en) . '.' : '')
            ->action('Ver el remate', route('remates.show', $r->slug)), $destinatario);
    }

    public static function baja(MailMessage $correo, object $destinatario): MailMessage
    {
        return $destinatario instanceof Suscripcion
            ? $correo->line('Recibes este correo porque pediste avisos de remates. [Dejar de recibirlos](' . route('suscripciones.baja', $destinatario->token) . ').')
            : $correo;
    }
}
