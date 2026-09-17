<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Garantia;
use App\Models\Remate;
use App\Models\User;
use App\Subastas\Difusion\Emisor;
use App\Subastas\EstadoRemate;
use App\Subastas\Liquidador;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

/**
 * Panel del martillero (Bloque I): seguimiento en vivo del remate con las identidades de los postores, mensaje a la sala
 * y cierre anticipado. Sin diseño propio: usa los componentes del panel. Mismo transporte que la sala (JSON estático cada
 * ~1 s y reloj con hora.php): el panel no agrega carga de PHP mientras se puja.
 */
class EnVivoController extends Controller
{
    public function show(Request $request, Remate $remate, Liquidador $liquidador, Emisor $emisor): View
    {
        $user = User::find($request->user()->id);
        abort_unless($user->esAdmin() || ($user->rol === User::ROL_MARTILLERO && $remate->martillero_id === $user->id), 403);
        abort_if($remate->estado === Remate::ESTADO_BORRADOR, 404);

        $liquidador->liquidarVencidos($remate);
        try {
            $emisor->publicarEstado($remate);
        } catch (Throwable $e) {
            report($e);
        }

        // Alias público → identidad, solo para el panel. Las garantías aprobadas después de cargar muestran solo el alias.
        $nombres = [];
        $garantias = Garantia::with('user.postor.empresa')->where('remate_id', $remate->id)->get()->keyBy('user_id');
        foreach (EstadoRemate::alias($remate) as $userId => $alias) {
            $g = $garantias->get($userId);
            $postor = $g?->user?->postor;
            $nombres[$alias] = $postor ? ($postor->empresa?->razon_social ?? $postor->nombreCompleto()) : ($g?->user?->name ?? $alias);
        }

        return view('admin.remates.en-vivo', [
            'remate' => $remate->load(['lotes', 'martillero']),
            'habilitados' => $garantias->where('estado', Garantia::ESTADO_APROBADA)->count(),
            'config' => [
                'estado' => EstadoRemate::construir($remate),
                'nombres' => $nombres,
                'lotes' => $remate->lotes->mapWithKeys(fn ($l) => [$l->id => ['orden' => $l->orden, 'direccion' => $l->direccion ?: $l->titulo, 'base' => $l->precio_base]])->all(),
                'servidorMs' => (int) now('UTC')->format('Uv'),
                'urls' => [
                    'estadoJson' => asset('tiempo-real/' . $remate->slug . '.json'),
                    'estado' => route('tiempo-real.estado', $remate),
                    'hora' => asset('hora.php'),
                    'cerrar' => url("/admin/remates/{$remate->slug}/lotes/__LOTE__/cerrar"),
                    'mensaje' => route('admin.sala.mensaje', $remate),
                ],
            ],
        ]);
    }
}
