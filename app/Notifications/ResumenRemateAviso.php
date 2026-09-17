<?php

namespace App\Notifications;

use App\Models\Lote;
use App\Models\Remate;
use App\Support\Formato;
use Illuminate\Database\Eloquent\Model;

/**
 * A la administración, UN correo al cerrar el último lote del remate (decisión de Jonas, 17/09: antes iba uno por
 * lote). Trae todos los lotes con su resultado y monto.
 */
class ResumenRemateAviso extends AvisoColliers
{
    public function __construct(public Remate $remate) {}

    public function plantilla(): string
    {
        return 'resumen_remate';
    }

    public function notificable(): ?Model
    {
        return $this->remate;
    }

    protected function datos(?object $destinatario = null): array
    {
        $lotes = $this->remate->lotes()->with('adjudicacion.user.postor.empresa')->orderBy('orden')->get();
        $adjudicados = $lotes->filter(fn (Lote $l) => $l->adjudicacion !== null);

        $resumen = $lotes->map(function (Lote $lote) {
            $nombre = "Lote {$lote->orden} · " . ($lote->direccion ?: $lote->titulo);
            if ($lote->adjudicacion === null) {
                return "{$nombre}: desierto (base " . Formato::clp($lote->precio_base) . ')';
            }
            $postor = $lote->adjudicacion->user->postor;
            $quien = $postor?->empresa?->razon_social ?? $postor?->nombreCompleto() ?? $lote->adjudicacion->user->name;

            return "{$nombre}: adjudicado en " . Formato::clp($lote->adjudicacion->monto) . " a {$quien} ({$lote->adjudicacion->user->email})"
                . ($lote->motivo_cierre === Lote::CIERRE_ANTICIPADO ? ' · cierre anticipado' : '');
        })->join("\n");

        return [
            'remate' => $this->remate->titulo,
            'folio' => $this->remate->folio,
            'cierre' => Formato::fecha($this->remate->finalizado_en ?? $lotes->max('cerrado_en')),
            'resumen' => $resumen,
            'total' => Formato::clp((int) $adjudicados->sum(fn (Lote $l) => $l->adjudicacion->monto)),
            'adjudicados' => $adjudicados->count(),
            'lotes' => $lotes->count(),
        ];
    }

    protected function enlace(?object $destinatario = null): ?string
    {
        return route('admin.remates.show', $this->remate->id);
    }
}
