<?php

namespace App\Notifications;

use App\Models\Lote;
use App\Support\Formato;
use Illuminate\Database\Eloquent\Model;

/**
 * A los postores que pujaron en el lote y no ganaron, una sola vez al cierre (decisión de Jonas, 17/09: reemplaza el
 * correo «te superaron» por puja, que con la cola por cron llegaría tarde y sería ruido durante el remate).
 */
class NoAdjudicadoAviso extends AvisoColliers
{
    public function __construct(public Lote $lote) {}

    public function plantilla(): string
    {
        return 'no_adjudicado';
    }

    public function notificable(): ?Model
    {
        return $this->lote;
    }

    protected function datos(?object $destinatario = null): array
    {
        return [
            'remate' => $this->lote->remate->titulo,
            'folio' => $this->lote->remate->folio,
            'lote' => $this->lote->direccion ?: $this->lote->titulo,
            'monto' => Formato::clp($this->lote->adjudicacion?->monto ?? $this->lote->precio_actual),
            'cierre' => Formato::fecha($this->lote->cerrado_en),
        ];
    }

    protected function enlace(?object $destinatario = null): ?string
    {
        return route('remates.index');
    }
}
