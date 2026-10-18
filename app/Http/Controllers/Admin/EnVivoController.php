<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Garantia;
use App\Models\Remate;
use App\Models\User;
use App\Subastas\Difusion\Emisor;
use App\Subastas\EstadoRemate;
use App\Subastas\Liquidador;
use App\Subastas\Presencia;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
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
        $this->autorizar($request, $remate);

        $liquidador->transicionesPendientes($remate);
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
                    'presencia' => route('admin.remates.presencia', $remate),
                ],
                'presencia' => $this->conectados($remate),
                'latidoMs' => Presencia::latidoMs(),
            ],
        ]);
    }

    /**
     * Quién tiene la sala abierta, con nombre: solo para el panel (administración y el martillero del remate). El JSON
     * público lleva solo el número. Lo consulta el panel cada ~15 s: son una o dos personas, no multiplica peticiones.
     */
    public function presencia(Request $request, Remate $remate): JsonResponse
    {
        $this->autorizar($request, $remate);

        return response()->json($this->conectados($remate), 200, ['Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0']);
    }

    /**
     * Nombre y apellido del postor; si puja por una empresa, el nombre de la empresa. Nada más: ni RUT ni correo. En la
     * sala solo entra quien está habilitado, así que no se marca la habilitación.
     *
     * @return array{conectados: int, usuarios: list<array{nombre: string}>}
     */
    private function conectados(Remate $remate): array
    {
        $activos = Presencia::sala()->activos($remate->id, (int) CarbonImmutable::now('UTC')->format('Uv'));
        $usuarios = User::with('postor.empresa')->whereIn('id', array_keys($activos))->get()
            ->map(fn (User $u) => ['nombre' => $u->postor ? ($u->postor->empresa?->razon_social ?? $u->postor->nombreCompleto()) : $u->name])
            ->sortBy('nombre', SORT_NATURAL | SORT_FLAG_CASE)->values()->all();

        return ['conectados' => count($activos), 'usuarios' => $usuarios];
    }

    private function autorizar(Request $request, Remate $remate): void
    {
        $user = User::find($request->user()->id);
        // El martillero entra al panel de SUS remates. Si abre el de otro, el 403 dice por qué y a quién está asignado.
        abort_unless($user->esAdmin() || ($user->rol === User::ROL_MARTILLERO && $remate->martillero_id === $user->id), 403,
            $user->rol === User::ROL_MARTILLERO
                ? 'El remate ' . $remate->folio . ' está a cargo de ' . ($remate->martillero?->name ?? 'otro martillero')
                    . '. En «Subastas» aparecen los remates asignados a ti.'
                : '');
        abort_if($remate->estado === Remate::ESTADO_BORRADOR, 404);
    }
}
