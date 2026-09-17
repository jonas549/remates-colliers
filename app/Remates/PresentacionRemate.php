<?php

namespace App\Remates;

use App\Models\Garantia;
use App\Models\Lote;
use App\Models\Remate;
use App\Support\Formato;
use Carbon\CarbonImmutable;

/** Datos de remates listos para las pantallas del panel (misma forma que usaban los datos de ejemplo del Bloque T). */
class PresentacionRemate
{
    public const ETIQUETAS_PANEL = [
        Remate::VISTA_BORRADOR => ['Borrador', 'borrador'],
        Remate::VISTA_PROXIMO => ['Próxima', 'proximo'],
        Remate::VISTA_EN_VIVO => ['En vivo', 'vivo'],
        Remate::VISTA_ADJUDICADO => ['Adjudicada', 'adjudicada'],
        Remate::VISTA_CERRADO => ['Cerrada', 'cerrado'],
        Remate::VISTA_CANCELADO => ['Cancelada', 'cerrado'],
    ];

    public static function direccion(Remate $remate): string
    {
        $lotes = $remate->lotes;

        return $lotes->count() === 1 ? ($lotes->first()->direccion ?: $remate->titulo) : $remate->titulo;
    }

    public static function comuna(Remate $remate): string
    {
        $comunas = $remate->lotes->pluck('comuna')->filter()->unique();

        return $comunas->count() > 1 ? $remate->lotes->count() . ' lotes' : (string) $comunas->first();
    }

    /** Fila de la tabla de Subastas del panel. */
    public static function fila(Remate $remate, CarbonImmutable $ahora): array
    {
        $vista = $remate->estadoVisible($ahora);
        [$etiqueta, $tono] = self::ETIQUETAS_PANEL[$vista];
        $lotes = $remate->lotes;
        $actual = $lotes->sum(fn (Lote $l) => (int) $l->precio_actual) ?: null;
        $garantias = $remate->garantias;
        $aprobadas = $garantias->where('estado', Garantia::ESTADO_APROBADA)->count();

        return [
            'id' => $remate->id,
            'slug' => $remate->slug,
            'folio' => $remate->folio,
            'direccion' => self::direccion($remate),
            'comuna' => self::comuna($remate),
            'martillero' => $remate->martillero?->name ?? 'Sin martillero',
            'estado' => $etiqueta,
            'tono' => $tono,
            'vista' => $vista,
            'base' => (int) $lotes->sum('precio_base'),
            'actual' => $vista === Remate::VISTA_CERRADO ? null : $actual,
            'garantia' => $remate->montoGarantia(),
            'inscritos' => $vista === Remate::VISTA_BORRADOR ? '—'
                : (in_array($vista, [Remate::VISTA_EN_VIVO, Remate::VISTA_ADJUDICADO, Remate::VISTA_CERRADO], true)
                    ? "{$aprobadas} habilitados" : $garantias->count() . ' inscritos'),
            'inicio' => Formato::fechaCorta($remate->abreEn(), $ahora),
            'cierre' => Formato::fechaCorta($remate->cierraEn(), $ahora),
            'lotes' => $lotes->count(),
        ];
    }
}
