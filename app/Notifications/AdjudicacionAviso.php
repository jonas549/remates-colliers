<?php

namespace App\Notifications;

use App\Models\Adjudicacion;
use App\Support\Formato;
use Illuminate\Database\Eloquent\Model;

/** Al adjudicatario, cuando el lote se liquida a su favor. */
class AdjudicacionAviso extends AvisoColliers
{
    public function __construct(public Adjudicacion $adjudicacion) {}

    public function plantilla(): string
    {
        return 'adjudicacion';
    }

    public function notificable(): ?Model
    {
        return $this->adjudicacion;
    }

    protected function datos(?object $destinatario = null): array
    {
        $lote = $this->adjudicacion->lote;

        return [
            'remate' => $lote->remate->titulo,
            'folio' => $lote->remate->folio,
            'lote' => $lote->direccion ?: $lote->titulo,
            'monto' => Formato::clp($this->adjudicacion->monto),
            'cierre' => Formato::fecha($this->adjudicacion->cerrado_en),
        ];
    }

    protected function enlace(?object $destinatario = null): ?string
    {
        return route('remates.show', $this->adjudicacion->lote->remate->slug);
    }
}
