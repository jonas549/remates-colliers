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

/** POST de una puja. La confirmación en modal ocurre antes, en el navegador (Bloque K). */
class PujaController extends Controller
{
    public function store(Request $request, Remate $remate, int $lote, MotorPujas $motor): JsonResponse
    {
        $user = $request->user();

        try {
            $puja = $motor->pujar($user, $this->loteDelRemate($remate, $lote), $request->input('monto'),
                HoraRecepcion::de($request), $request->ip(), $request->userAgent());
        } catch (PujaRechazada $rechazo) {
            return response()->json([
                'aceptada' => false,
                'motivo' => $rechazo->motivo,
                'mensaje' => $rechazo->getMessage(),
                'lote' => $this->resumenLote($remate, $lote),
            ], 422);
        }

        return response()->json([
            'aceptada' => true,
            'puja' => ['monto' => $puja->monto, 'recibida_en_ms' => (int) $puja->recibida_en->format('Uv')],
            'lote' => $this->resumenLote($remate, $lote),
            'mi_alias' => EstadoRemate::alias($remate)[$user->id] ?? null,
        ], 201);
    }

    /** Un lote de otro remate se trata como inexistente: no se revela que existe. */
    private function loteDelRemate(Remate $remate, int $lote): int
    {
        return Lote::whereKey($lote)->where('remate_id', $remate->id)->exists() ? $lote : 0;
    }

    private function resumenLote(Remate $remate, int $loteId): ?array
    {
        $lote = Lote::whereKey($loteId)->where('remate_id', $remate->id)->first();
        if ($lote === null) {
            return null;
        }

        return [
            'id' => $lote->id,
            'precio_actual' => $lote->precio_actual,
            'puja_minima' => EstadoRemate::pujaMinima($lote, $remate->incrementoMinimo()),
            'total_pujas' => $lote->total_pujas,
            'ganador' => $lote->ganador_id ? (EstadoRemate::alias($remate)[$lote->ganador_id] ?? null) : null,
        ];
    }
}
