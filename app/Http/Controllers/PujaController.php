<?php

namespace App\Http\Controllers;

use App\Http\Middleware\HoraRecepcion;
use App\Models\Lote;
use App\Models\Remate;
use App\Subastas\EstadoRemate;
use App\Subastas\MotorPujas;
use App\Subastas\PujaRechazada;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * POST de una puja. La confirmación en modal ocurre antes, en el navegador (Bloque K).
 * Camino caliente: sin OPcache cada consulta extra se nota con varias pujas simultáneas, así que el resumen se arma con
 * una sola lectura del lote y una del alias (docs/RENDIMIENTO-SIN-OPCACHE.md).
 */
class PujaController extends Controller
{
    public function store(Request $request, Remate $remate, int $lote, MotorPujas $motor): JsonResponse
    {
        $user = $request->user();
        // Un lote de otro remate se trata como inexistente: no se revela que existe.
        $pertenece = Lote::whereKey($lote)->where('remate_id', $remate->id)->exists();

        try {
            $puja = $motor->pujar($user, $pertenece ? $lote : 0, $request->input('monto'),
                HoraRecepcion::de($request), $request->ip(), $request->userAgent());
        } catch (PujaRechazada $rechazo) {
            return response()->json([
                'aceptada' => false,
                'motivo' => $rechazo->motivo,
                'mensaje' => $rechazo->getMessage(),
                'lote' => $pertenece ? $this->resumenLote($remate, $lote, EstadoRemate::alias($remate)) : null,
            ], 422);
        }

        $alias = EstadoRemate::alias($remate);

        return response()->json([
            'aceptada' => true,
            'puja' => ['monto' => $puja->monto, 'recibida_en_ms' => (int) $puja->recibida_en->format('Uv')],
            'lote' => $this->resumenLote($remate, $lote, $alias),
            'mi_alias' => $alias[$user->id] ?? null,
        ], 201);
    }

    /** @param array<int, string> $alias */
    private function resumenLote(Remate $remate, int $loteId, array $alias): array
    {
        $lote = Lote::findOrFail($loteId);

        return [
            'id' => $lote->id,
            'precio_actual' => $lote->precio_actual,
            'puja_minima' => EstadoRemate::pujaMinima($lote, $remate->incrementoMinimo()),
            'total_pujas' => $lote->total_pujas,
            'ganador' => $lote->ganador_id ? ($alias[$lote->ganador_id] ?? null) : null,
        ];
    }
}
