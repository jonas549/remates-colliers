<?php

namespace App\Subastas;

use App\Models\Configuracion;
use App\Models\Garantia;
use App\Models\Lote;
use App\Models\Puja;
use App\Models\Remate;
use Carbon\CarbonImmutable;

/**
 * Estado público de un remate: lo que se difunde a todos los espectadores y lo que devuelve el endpoint de
 * estado. NO incluye identidades: cada postor aparece como «Postor #N» (supuesto vigente, como el diseño).
 * Las horas van en milisegundos UTC para que el navegador compare contra su reloj sincronizado.
 */
final class EstadoRemate
{
    public const PUJAS_RECIENTES = 10;

    public static function construir(Remate $remate, ?CarbonImmutable $ahora = null): array
    {
        $ahora ??= CarbonImmutable::now('UTC');
        $remate = $remate->fresh(['lotes']);
        $alias = self::alias($remate);
        $incremento = $remate->incrementoMinimo();

        $lotes = $remate->lotes->map(function (Lote $lote) use ($alias, $incremento) {
            $recientes = $lote->estado === Lote::ESTADO_PROGRAMADO && $lote->total_pujas === 0 ? collect() : $lote->pujas()
                ->orderByDesc('id')->limit(self::PUJAS_RECIENTES)->get()
                ->map(fn (Puja $p) => ['monto' => $p->monto, 'postor' => $alias[$p->user_id] ?? null, 'en_ms' => self::ms($p->recibida_en)]);

            return [
                'id' => $lote->id,
                'orden' => $lote->orden,
                'titulo' => $lote->titulo,
                'estado' => $lote->estado,
                'abre_en_ms' => self::ms($lote->abre_en),
                'cierra_en_ms' => self::ms($lote->cierra_en),
                'motivo_cierre' => $lote->motivo_cierre,
                'precio_base' => $lote->precio_base,
                'precio_actual' => $lote->precio_actual,
                'puja_minima' => self::pujaMinima($lote, $incremento),
                'total_pujas' => $lote->total_pujas,
                'ganador' => $lote->ganador_id ? ($alias[$lote->ganador_id] ?? null) : null,
                'pujas' => $recientes->values()->all(),
            ];
        });

        return [
            'remate' => $remate->slug,
            'estado' => $remate->estado,
            'incremento_minimo' => $incremento,
            'margen_liquidacion_ms' => (int) Configuracion::valor('margen_liquidacion_segundos') * 1000,
            'mensaje_martillero' => $remate->mensaje_martillero === null ? null : [
                'texto' => $remate->mensaje_martillero,
                'en_ms' => self::ms($remate->mensaje_martillero_en),
            ],
            'lotes' => $lotes->values()->all(),
            'generado_en_ms' => self::ms($ahora),
        ];
    }

    public static function pujaMinima(Lote $lote, int $incremento): int
    {
        return $lote->precio_actual === null ? $lote->precio_base : $lote->precio_actual + $incremento;
    }

    /**
     * «Postor #N»: N es el orden en que el postor inscribió su garantía en el remate. Estable, porque las
     * garantías no se borran y los ids solo crecen.
     *
     * @return array<int, string> user_id => alias
     */
    public static function alias(Remate $remate): array
    {
        $alias = [];
        foreach (Garantia::where('remate_id', $remate->id)->orderBy('id')->pluck('user_id')->unique()->values() as $i => $userId) {
            $alias[$userId] = 'Postor #' . ($i + 1);
        }

        return $alias;
    }

    private static function ms(?CarbonImmutable $fecha): ?int
    {
        return $fecha === null ? null : (int) $fecha->format('Uv');
    }
}
