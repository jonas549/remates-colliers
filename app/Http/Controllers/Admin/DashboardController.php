<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Adjudicacion;
use App\Models\Configuracion;
use App\Models\Garantia;
use App\Models\Lote;
use App\Models\Postor;
use App\Models\Puja;
use App\Models\Remate;
use App\Remates\PresentacionRemate;
use App\Support\Formato;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/** Resumen del panel con datos reales (diseño «Admin Dashboard»). Excluye los remates de demostración. */
class DashboardController extends Controller
{
    public function index(): View
    {
        $ahora = CarbonImmutable::now('UTC');
        $remates = Remate::with(['lotes', 'garantias'])->where('es_demostracion', false)->get();
        $porVista = $remates->groupBy(fn (Remate $r) => $r->estadoVisible($ahora));
        $enVivo = $porVista->get(Remate::VISTA_EN_VIVO, collect());
        $proximos = $porVista->get(Remate::VISTA_PROXIMO, collect());

        $inicioMes = $ahora->setTimezone(Formato::ZONA)->startOfMonth()->utc();
        $adjudicadoMes = Adjudicacion::where('cerrado_en', '>=', $inicioMes->format('Y-m-d H:i:s'))
            ->whereHas('lote.remate', fn ($q) => $q->where('es_demostracion', false))->get();
        $cerradosMes = $remates->filter(fn (Remate $r) => in_array($r->estadoVisible($ahora), [Remate::VISTA_ADJUDICADO, Remate::VISTA_CERRADO], true)
            && $r->cierraEn()?->greaterThanOrEqualTo($inicioMes));
        $concretados = $cerradosMes->filter(fn (Remate $r) => $r->estadoVisible($ahora) === Remate::VISTA_ADJUDICADO)->count();

        $postores = Postor::count();
        $enRevision = Postor::where('estado', Postor::ESTADO_EN_REVISION)->count();
        $garantiasRevision = Garantia::where('estado', Garantia::ESTADO_EN_REVISION)->whereHas('remate', fn ($q) => $q->where('es_demostracion', false));
        $uf = Configuracion::valor('uf_valor');

        return view('admin.dashboard', ['datos' => [
            'fecha' => Formato::fechaConDia($ahora) . ($uf ? ' · UF $' . number_format((float) $uf, 2, ',', '.') : ''),
            'kpis' => [
                ['SUBASTAS ACTIVAS', (string) ($enVivo->count() + $proximos->count()), $enVivo->count() . ' en vivo · ' . $proximos->count() . ' próximas', false],
                ['POSTORES REGISTRADOS', (string) $postores, '+' . Postor::where('created_at', '>=', $ahora->subWeek()->format('Y-m-d H:i:s'))->count() . ' esta semana', false],
                ['CUENTAS POR APROBAR', (string) $enRevision, 'Revisión manual pendiente', $enRevision > 0],
                ['GARANTÍAS EN REVISIÓN', (string) (clone $garantiasRevision)->count(), Formato::clp((int) (clone $garantiasRevision)->sum('monto')) . ' comprometidos', (clone $garantiasRevision)->exists()],
                ['ADJUDICADO EN ' . mb_strtoupper(Formato::MESES[$ahora->setTimezone(Formato::ZONA)->month - 1]), $this->millones((int) $adjudicadoMes->sum('monto')),
                    "{$concretados} de {$cerradosMes->count()} remates concretados", false],
            ],
            'subastas' => $this->subastas($enVivo, $proximos, $porVista, $ahora),
            'pendientes' => $this->pendientes(),
            'actividad' => $this->actividad($ahora),
        ]]);
    }

    private function subastas(Collection $enVivo, Collection $proximos, Collection $porVista, CarbonImmutable $ahora): array
    {
        $cerrados = $porVista->get(Remate::VISTA_ADJUDICADO, collect())->merge($porVista->get(Remate::VISTA_CERRADO, collect()))
            ->sortByDesc(fn (Remate $r) => $r->cierraEn()?->getTimestamp())->take(2);
        $lista = $enVivo->sortBy(fn (Remate $r) => $r->cierraEn()?->getTimestamp())
            ->merge($proximos->sortBy(fn (Remate $r) => $r->abreEn()?->getTimestamp()))->take(6)->merge($cerrados);

        return $lista->map(function (Remate $r) use ($ahora) {
            $fila = PresentacionRemate::fila($r, $ahora);
            $vista = $fila['vista'];
            $activos = $vista === Remate::VISTA_EN_VIVO
                ? Puja::whereIn('lote_id', $r->lotes->pluck('id'))->distinct()->count('user_id') . ' activos'
                : ($vista === Remate::VISTA_PROXIMO ? $r->garantias->count() . ' inscritos' : (string) Puja::whereIn('lote_id', $r->lotes->pluck('id'))->distinct()->count('user_id'));

            return [
                'url' => route('admin.remates.show', $r),
                'folio' => $r->folio,
                'direccion' => $fila['direccion'],
                'comuna' => $fila['comuna'],
                'estado' => mb_strtoupper($vista === Remate::VISTA_PROXIMO ? 'Próximo' : ($vista === Remate::VISTA_EN_VIVO ? 'En vivo' : ($vista === Remate::VISTA_ADJUDICADO ? 'Adjudicado' : 'Cerrado'))),
                'tono' => ['en_vivo' => 'vivo', 'proximo' => 'proximo', 'adjudicado' => 'adjudicada'][$vista] ?? 'cerrado',
                'base' => Formato::clp($fila['base']),
                'actual' => $fila['actual'] ? Formato::clp($fila['actual']) : ($vista === Remate::VISTA_CERRADO ? 'Sin postores' : '—'),
                'postores' => $activos,
                'cierre' => Formato::fechaCorta($r->cierraEn(), $ahora),
            ];
        })->values()->all();
    }

    private function pendientes(): array
    {
        $cuentas = Postor::with('empresa')->where('estado', Postor::ESTADO_EN_REVISION)->latest('updated_at')->take(4)->get()
            ->map(fn (Postor $p) => [$p->updated_at, $p->empresa?->razon_social ?? $p->nombreCompleto(), 'Cuenta nueva · RUT ' . $p->rut, 'CUENTA', 'revision']);
        $garantias = Garantia::with(['user.postor.empresa', 'remate'])->where('estado', Garantia::ESTADO_EN_REVISION)->latest('updated_at')->take(4)->get()
            ->map(fn (Garantia $g) => [$g->updated_at, $g->user->postor?->empresa?->razon_social ?? $g->user->postor?->nombreCompleto() ?? $g->user->name,
                'Garantía ' . $g->remate->folio . ' · ' . Formato::clp($g->monto), 'GARANTÍA', 'pendiente']);

        return $cuentas->merge($garantias)->sortByDesc(fn ($f) => $f[0]?->getTimestamp())->take(6)
            ->map(fn ($f) => array_slice($f, 1))->values()->all();
    }

    /** Últimos hechos del sistema, mezclados por hora: pujas, garantías revisadas, publicaciones y cierres. */
    private function actividad(CarbonImmutable $ahora): array
    {
        $hechos = collect();
        foreach (Puja::with('lote.remate')->latest('id')->take(3)->get() as $p) {
            $hechos->push([$p->recibida_en, 'Nueva puja de ' . Formato::clp($p->monto) . ' en ' . $p->lote->remate->folio, '#c8102e']);
        }
        foreach (Garantia::with(['user', 'remate'])->whereIn('estado', [Garantia::ESTADO_APROBADA, Garantia::ESTADO_RECHAZADA])->whereNotNull('revisado_en')->latest('revisado_en')->take(3)->get() as $g) {
            $aprobada = $g->estado === Garantia::ESTADO_APROBADA;
            $hechos->push([$g->revisado_en, ($aprobada ? 'Garantía aprobada a ' : 'Garantía rechazada a ') . $g->user->name . ' (' . $g->remate->folio . ')'
                . (! $aprobada && $g->motivo_rechazo ? ': ' . mb_strtolower($g->motivo_rechazo) : ''), $aprobada ? '#1c5330' : '#c8102e']);
        }
        foreach (Remate::with('lotes')->whereNotNull('publicado_en')->latest('publicado_en')->take(3)->get() as $r) {
            $hechos->push([$r->publicado_en, 'Se publicó el remate ' . $r->folio . ', ' . PresentacionRemate::direccion($r), '#25408f']);
        }
        foreach (Lote::with(['remate', 'cerradoPor'])->whereNotNull('cerrado_en')->latest('cerrado_en')->take(3)->get() as $l) {
            $texto = $l->motivo_cierre === Lote::CIERRE_ANTICIPADO
                ? ($l->cerradoPor?->name ?? 'Colliers') . ' cerró anticipadamente ' . $l->remate->folio
                : ($l->estado === Lote::ESTADO_DESIERTO ? $l->remate->folio . ' cerró sin postores' : $l->remate->folio . ' adjudicado en ' . Formato::clp($l->precio_actual));
            $hechos->push([$l->cerrado_en, $texto, $l->estado === Lote::ESTADO_ADJUDICADO ? '#1c5330' : '#a3abb8']);
        }

        return $hechos->filter(fn ($h) => $h[0] !== null)->sortByDesc(fn ($h) => $h[0]->getTimestamp())->take(6)
            ->map(fn ($h) => [$h[1], Formato::hace($h[0], $ahora), $h[2]])->values()->all();
    }

    private function millones(int $monto): string
    {
        return '$' . number_format(round($monto / 1000000), 0, ',', '.') . 'M';
    }
}
