<?php

namespace App\Http\Controllers;

use App\Models\Remate;
use App\Subastas\Difusion\Emisor;
use App\Subastas\EstadoRemate;
use App\Subastas\Liquidador;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * Endpoints de apoyo al tiempo real. Los espectadores leen el JSON estático; estos endpoints ejecutan PHP y
 * se usan solo al cargar, al reconectar, para sincronizar el reloj y cuando el navegador detecta que pasó
 * cierra_en + margen y el JSON todavía no muestra el lote liquidado.
 */
class TiempoRealController extends Controller
{
    private const SIN_CACHE = ['Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0', 'Pragma' => 'no-cache'];

    /** Hora oficial del servidor: el cronómetro del navegador se sincroniza contra esto. */
    public function hora(): JsonResponse
    {
        return response()->json(['servidor_ms' => (int) CarbonImmutable::now('UTC')->format('Uv')], 200, self::SIN_CACHE);
    }

    /**
     * Estado actual del remate (reconexión). Es además detector del cierre perezoso: liquida los lotes vencidos
     * y vuelve a publicar el JSON estático.
     */
    public function estado(Remate $remate, Liquidador $liquidador, Emisor $emisor): JsonResponse
    {
        abort_if(in_array($remate->estado, [Remate::ESTADO_BORRADOR], true), 404);

        $liquidador->transicionesPendientes($remate);
        try {
            $emisor->publicarEstado($remate);
        } catch (Throwable $e) {
            report($e);
        }

        return response()->json(EstadoRemate::construir($remate), 200, self::SIN_CACHE);
    }
}
