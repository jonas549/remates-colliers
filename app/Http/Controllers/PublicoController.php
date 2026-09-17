<?php

namespace App\Http\Controllers;

use App\Models\Configuracion;
use App\Models\Documento;
use App\Models\Garantia;
use App\Models\Remate;
use App\Publico\Catalogo;
use App\Publico\EstadoVisitante;
use App\Subastas\Liquidador;
use App\Support\Formato;
use App\Support\Sitio;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Sitio público (Bloque N): listado y detalle con datos reales, documentos descargables y calendario .ics. */
class PublicoController extends Controller
{
    public function index(Request $request): View
    {
        return view('remates.index', [
            'remates' => Catalogo::listado(),
            'hero' => Catalogo::destacado(),
            'visitante' => EstadoVisitante::para($request->user()),
            'uf' => ['valor' => Sitio::uf(), 'fecha' => Configuracion::valor('uf_fecha')],
            'mostrarFiltroGarantia' => (bool) Configuracion::valor('filtro_garantia_visible'),
        ]);
    }

    public function show(Request $request, Remate $remate, Liquidador $liquidador): View
    {
        abort_if(in_array($remate->estado, [Remate::ESTADO_BORRADOR, Remate::ESTADO_CANCELADO], true) || $remate->lotes()->doesntExist(), 404);

        // Quien abre la ficha también materializa lo que ya ocurrió por reloj (abrir el lote siguiente, cerrar el
        // vencido) y republica el JSON: el espectador no depende de que alguien más «toque» el sistema.
        $liquidador->transicionesPendientes($remate);
        $remate->refresh();

        return view('remates.show', [
            'r' => Catalogo::detalle($remate, $request->user()),
            'visitante' => EstadoVisitante::para($request->user(), $remate),
        ]);
    }

    /** Documento del remate: los públicos para cualquiera; el resto, solo con garantía aprobada para ESTE remate. */
    public function documento(Request $request, Remate $remate, Documento $documento): StreamedResponse
    {
        abort_unless($documento->remate_id === $remate->id && ! in_array($remate->estado, [Remate::ESTADO_BORRADOR, Remate::ESTADO_CANCELADO], true), 404);
        if (! $documento->publico) {
            $user = $request->user();
            abort_unless($user !== null && ($user->esAdmin() || Garantia::where('user_id', $user->id)->where('remate_id', $remate->id)
                ->where('estado', Garantia::ESTADO_APROBADA)->exists()), 403);
        }
        abort_unless(Storage::disk('local')->exists($documento->ruta), 404);

        return Storage::disk('local')->download($documento->ruta, $documento->nombre_original);
    }

    /** «Agregar a mi calendario»: evento .ics con el inicio y el cierre del último lote (hora UTC). */
    public function calendario(Remate $remate): Response
    {
        abort_if(in_array($remate->estado, [Remate::ESTADO_BORRADOR, Remate::ESTADO_CANCELADO], true), 404);
        $inicio = $remate->abreEn();
        abort_if($inicio === null, 404);
        $fin = $remate->cierraEn() ?? $inicio->addMinutes(30);
        $f = fn (CarbonImmutable $d) => $d->utc()->format('Ymd\THis\Z');
        $texto = fn (string $s) => str_replace(["\\", ';', ',', "\n"], ['\\\\', '\;', '\,', '\n'], $s);
        $url = route('remates.show', $remate->slug);

        $ics = implode("\r\n", [
            'BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//Colliers Chile//Remates//ES', 'CALSCALE:GREGORIAN', 'METHOD:PUBLISH',
            'BEGIN:VEVENT',
            'UID:remate-' . $remate->id . '@' . (parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'remates.colliers.cl'),
            'DTSTAMP:' . $f(CarbonImmutable::now('UTC')),
            'DTSTART:' . $f($inicio),
            'DTEND:' . $f($fin),
            'SUMMARY:' . $texto('Remate ' . $remate->folio . ' · ' . $remate->titulo),
            'DESCRIPTION:' . $texto('Remate en línea de Colliers con transmisión en vivo. Cierra automáticamente al vencer el tiempo, sin extensiones.'
                . ($remate->cierre_garantias_en ? ' Garantías hasta el ' . Formato::fecha($remate->cierre_garantias_en) . '.' : '') . ' ' . $url),
            'URL:' . $url,
            'BEGIN:VALARM', 'TRIGGER:-PT1H', 'ACTION:DISPLAY', 'DESCRIPTION:' . $texto('Remate ' . $remate->folio . ' en 1 hora'), 'END:VALARM',
            'END:VEVENT', 'END:VCALENDAR', '',
        ]);

        return response($ics, 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="remate-' . $remate->folio . '.ics"',
        ]);
    }
}
