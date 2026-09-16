<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Middleware\HoraRecepcion;
use App\Models\Lote;
use App\Models\Remate;
use App\Models\User;
use App\Subastas\Difusion\Emisor;
use App\Subastas\EstadoRemate;
use App\Subastas\Liquidador;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * Acciones del martillero sobre la sala en vivo (las pantallas son del Bloque I).
 * Autorización mínima hasta el Bloque D: administrador, o el martillero asignado a ESE remate.
 */
class SalaMartilleroController extends Controller
{
    public function cerrarLote(Request $request, Remate $remate, int $lote, Liquidador $liquidador): JsonResponse
    {
        $this->autorizar($request->user(), $remate);
        $modelo = Lote::whereKey($lote)->where('remate_id', $remate->id)->firstOrFail();

        try {
            $modelo = $liquidador->cerrarAnticipadamente($modelo, $request->user(), HoraRecepcion::de($request));
        } catch (DomainException $e) {
            return response()->json(['cerrado' => false, 'mensaje' => $e->getMessage()], 422);
        }

        return response()->json([
            'cerrado' => true,
            'cierra_en_ms' => (int) $modelo->cierra_en->format('Uv'),
            'mensaje' => 'Lote cerrado. La adjudicación se materializa en ' . $liquidador->margenSegundos() . ' s.',
        ]);
    }

    public function mensaje(Request $request, Remate $remate, Emisor $emisor): JsonResponse
    {
        $this->autorizar($request->user(), $remate);
        $datos = $request->validate(['texto' => ['nullable', 'string', 'max:500']]);

        $texto = trim((string) ($datos['texto'] ?? ''));
        $remate->update([
            'mensaje_martillero' => $texto === '' ? null : $texto,
            'mensaje_martillero_en' => $texto === '' ? null : CarbonImmutable::now('UTC'),
        ]);
        try {
            $emisor->publicarEstado($remate);
        } catch (Throwable $e) {
            report($e);
        }

        return response()->json(['publicado' => true, 'estado' => EstadoRemate::construir($remate)]);
    }

    private function autorizar(?User $user, Remate $remate): void
    {
        $user = $user === null ? null : User::find($user->id);
        $permitido = $user !== null && $user->estado === User::ESTADO_ACTIVO && (
            $user->rol === User::ROL_ADMIN
            || ($user->rol === User::ROL_MARTILLERO && $remate->martillero_id === $user->id)
        );
        abort_unless($permitido, 403);
    }
}
