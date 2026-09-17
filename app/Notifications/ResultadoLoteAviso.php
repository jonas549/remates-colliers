<?php

namespace App\Notifications;

use App\Models\Lote;
use App\Models\Remate;
use App\Support\Formato;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Messages\MailMessage;

/** A la administración: resultado de cada lote (adjudicado o desierto). */
class ResultadoLoteAviso extends AvisoColliers
{
    public function __construct(public Lote $lote) {}

    public function asunto(): string
    {
        return ($this->lote->estado === Lote::ESTADO_ADJUDICADO ? 'Lote adjudicado' : 'Lote cerrado sin posturas') . ' en ' . $this->lote->remate->folio;
    }

    public function notificable(): ?Model
    {
        return $this->lote;
    }

    protected function contenido(MailMessage $correo, object $destinatario): MailMessage
    {
        $lote = $this->lote;
        $correo->line("Remate {$lote->remate->folio} · lote {$lote->orden}: " . ($lote->direccion ?: $lote->titulo) . '.')
            ->line('Cierre: ' . Formato::fecha($lote->cerrado_en) . ($lote->motivo_cierre === Lote::CIERRE_ANTICIPADO ? ' (anticipado' . ($lote->nota_cierre ? ': ' . $lote->nota_cierre : '') . ')' : ' (por tiempo)') . '.');

        if ($lote->estado === Lote::ESTADO_ADJUDICADO && $lote->adjudicacion) {
            $postor = $lote->adjudicacion->user->postor;
            $correo->line('Adjudicado en ' . Formato::clp($lote->adjudicacion->monto) . ' a ' . ($postor?->empresa?->razon_social ?? $postor?->nombreCompleto() ?? $lote->adjudicacion->user->name)
                . ' (' . $lote->adjudicacion->user->email . ($postor?->telefono ? ', ' . $postor->telefono : '') . ').')
                ->line('Pujas: ' . $lote->total_pujas . '. Precio base: ' . Formato::clp($lote->precio_base) . '.');
        } else {
            $correo->line('Sin posturas: el lote quedó desierto. Para volver a rematarlo, crea un remate nuevo desde el panel.');
        }

        return $correo->action('Ver el remate en el panel', route('admin.remates.show', $lote->remate_id));
    }
}
