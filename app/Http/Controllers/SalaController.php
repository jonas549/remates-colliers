<?php

namespace App\Http\Controllers;

use App\Demo\SalaDemo;
use App\Models\Configuracion;
use App\Models\Garantia;
use App\Models\Lote;
use App\Models\Remate;
use App\Subastas\Difusion\Emisor;
use App\Subastas\EstadoRemate;
use App\Subastas\Liquidador;
use App\Support\Sitio;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

/**
 * Sala de puja (Bloque K), conectada al motor del Bloque J.
 *
 * Solo entra un postor con la cuenta y la garantía DE ESTE REMATE aprobadas; los espectadores siguen el remate en el
 * detalle en vivo. El navegador recibe el estado inicial y luego consulta el JSON estático cada ~1 s; toda hora sale
 * del servidor (endpoint /hora).
 */
class SalaController extends Controller
{
    public function show(Request $request, Remate $remate, Liquidador $liquidador, Emisor $emisor): View|RedirectResponse
    {
        abort_if(in_array($remate->estado, [Remate::ESTADO_BORRADOR, Remate::ESTADO_CANCELADO], true), 404);

        // Solo local: datos fijos del prototipo para la comparación visual 1:1.
        if (app()->isLocal() && $request->boolean('demo')) {
            return view('sala.show', SalaDemo::datos());
        }

        $user = $request->user();
        $garantia = Garantia::where('user_id', $user->id)->where('remate_id', $remate->id)
            ->where('estado', Garantia::ESTADO_APROBADA)->first();
        if (! $user->postor?->estaAprobado() || $garantia === null) {
            return redirect()->route('cuenta.estado');
        }

        // Carga de página = detector del cierre perezoso; además garantiza que exista el JSON estático.
        $liquidador->liquidarVencidos($remate);
        try {
            $emisor->publicarEstado($remate);
        } catch (Throwable $e) {
            report($e);
        }

        $lote = $this->loteVigente($remate);
        $alias = EstadoRemate::alias($remate)[$user->id] ?? null;
        $lotes = $remate->lotes()->get();

        return view('sala.show', [
            'componente' => 'salaPuja',
            'config' => [
                'remate' => $remate->slug,
                'loteInicial' => $lote->id,
                // Datos fijos de cada lote: con varios lotes la ficha cambia al pasar al siguiente.
                'lotes' => $lotes->mapWithKeys(fn (Lote $l) => [$l->id => [
                    'orden' => $l->orden,
                    'direccion' => $l->direccion ?: $l->titulo,
                    'meta' => $this->meta($l),
                    'base' => $l->precio_base,
                ]])->all(),
                'miAlias' => $alias,
                'pujasRapidas' => array_map('intval', (array) Configuracion::valor('pujas_rapidas')),
                'estado' => EstadoRemate::construir($remate),
                'servidorMs' => (int) now('UTC')->format('Uv'),
                'uf' => Sitio::uf(),
                'urls' => [
                    'estadoJson' => asset('tiempo-real/' . $remate->slug . '.json'),
                    'estado' => route('tiempo-real.estado', $remate),
                    // Sin framework (public/hora.php): la ruta /hora queda de respaldo.
                    'hora' => asset('hora.php'),
                    'pujar' => url("/remates/{$remate->slug}/lotes/__LOTE__/pujas"),
                    'ingresar' => route('login'),
                ],
            ],
            'propiedad' => [
                'slug' => $remate->slug,
                'folio' => $remate->folio,
                'direccion' => $lote->direccion ?: $lote->titulo,
                'meta' => $this->meta($lote),
                'base' => $lote->precio_base,
                'incremento' => $remate->incrementoMinimo(),
                'garantia' => $garantia->monto,
                'martillero' => $remate->martillero?->name,
                'video' => $remate->youtube_video_id,
                'usuario' => $user->name . ($alias ? ' · ' . $alias : ''),
            ],
        ]);
    }

    /** El lote que se está rematando: el primero sin liquidar; si ya terminaron todos, el último. */
    private function loteVigente(Remate $remate): Lote
    {
        return $remate->lotes()->whereNotIn('estado', Liquidador::ESTADOS_TERMINALES)->first()
            ?? $remate->lotes()->reorder('orden', 'desc')->firstOrFail();
    }

    private function meta(Lote $lote): string
    {
        $partes = array_filter([
            trim(implode(', ', array_filter([$lote->comuna, $lote->region])), ', '),
            $lote->tipo_propiedad,
            $lote->superficie_util ? rtrim(rtrim(number_format((float) $lote->superficie_util, 2, ',', '.'), '0'), ',') . ' m² útiles' : null,
            $lote->dormitorios !== null ? $lote->dormitorios . 'D / ' . ($lote->banos ?? 0) . 'B' : null,
            $lote->ocupacion,
        ]);

        return implode(' · ', $partes);
    }
}
