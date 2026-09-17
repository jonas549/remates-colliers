<?php

namespace App\Reportes;

use App\Models\Adjudicacion;
use App\Models\Garantia;
use App\Models\Lote;
use App\Models\Postor;
use App\Models\Puja;
use App\Models\Remate;
use App\Subastas\EstadoRemate;
use App\Support\Formato;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * Reportes post-evento (Bloque O) sobre los remates reales: excluye demostraciones y cancelados.
 *
 * La unidad es el LOTE terminado (adjudicado o desierto): un remate de una propiedad es un remate con un lote, así que
 * con un solo lote cada fila es el remate del diseño. El período se cuenta por la hora de cierre del lote, en hora de
 * Chile. Nada de lo que sale de aquí trae identidades: los postores van como «Postor #N» (mismo alias que la sala).
 */
class ReporteRemates
{
    public const TODO = 'todo';

    /** Lotes terminados del período, con remate, adjudicación y la primera y última puja. */
    private Collection $lotes;

    private CarbonImmutable $inicio;

    private CarbonImmutable $fin;

    private string $etiqueta;

    public function __construct(private readonly string $periodo)
    {
        [$this->inicio, $this->fin, $this->etiqueta] = self::rango($periodo);
        $this->lotes = $this->cargarLotes();
    }

    /** Período por defecto: el mes del último lote cerrado; si no hay ninguno, el mes en curso. */
    public static function periodoPorDefecto(?CarbonImmutable $ahora = null): string
    {
        $ultimo = self::lotesTerminados()->max('lotes.cerrado_en');
        $fecha = $ultimo ? CarbonImmutable::parse($ultimo, 'UTC') : ($ahora ?? CarbonImmutable::now('UTC'));

        return $fecha->setTimezone(Formato::ZONA)->format('Y-m');
    }

    /** Opciones del selector: meses, trimestres y años con cierres, más el mes en curso y todo el historial. @return array<string, array<string, string>> */
    public static function opciones(?CarbonImmutable $ahora = null): array
    {
        $ahora = ($ahora ?? CarbonImmutable::now('UTC'))->setTimezone(Formato::ZONA);
        $fechas = self::lotesTerminados()->pluck('lotes.cerrado_en')
            ->map(fn ($f) => CarbonImmutable::parse($f, 'UTC')->setTimezone(Formato::ZONA))->push($ahora);

        $meses = $trimestres = $anios = [];
        foreach ($fechas->sortDesc() as $f) {
            $meses[$f->format('Y-m')] = ucfirst(Formato::MESES[$f->month - 1]) . ' ' . $f->year;
            $trimestres[$f->year . '-T' . $f->quarter] = self::rango($f->year . '-T' . $f->quarter)[2];
            $anios[(string) $f->year] = 'Año ' . $f->year;
        }

        return ['Meses' => $meses, 'Trimestres' => $trimestres, 'Años' => $anios + [self::TODO => 'Todo el historial']];
    }

    /**
     * Rango [inicio, fin) en UTC y su etiqueta. Claves: `AAAA-MM`, `AAAA-TN`, `AAAA` o `todo`.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable, 2: string}
     */
    public static function rango(string $periodo): array
    {
        $zona = Formato::ZONA;
        if ($periodo === self::TODO) {
            return [CarbonImmutable::create(2000, 1, 1, 0, 0, 0, 'UTC'), CarbonImmutable::create(2100, 1, 1, 0, 0, 0, 'UTC'), 'todo el historial'];
        }
        if (preg_match('/^(\d{4})-(\d{2})$/', $periodo, $m) && (int) $m[2] >= 1 && (int) $m[2] <= 12) {
            $inicio = CarbonImmutable::create((int) $m[1], (int) $m[2], 1, 0, 0, 0, $zona);

            return [$inicio->utc(), $inicio->addMonth()->utc(), Formato::MESES[$inicio->month - 1] . ' ' . $inicio->year];
        }
        if (preg_match('/^(\d{4})-T([1-4])$/', $periodo, $m)) {
            $inicio = CarbonImmutable::create((int) $m[1], ((int) $m[2] - 1) * 3 + 1, 1, 0, 0, 0, $zona);
            $ultimo = $inicio->addMonths(2);

            return [$inicio->utc(), $inicio->addMonths(3)->utc(), Formato::MESES[$inicio->month - 1] . ' a ' . Formato::MESES[$ultimo->month - 1] . ' ' . $inicio->year];
        }
        if (preg_match('/^(\d{4})$/', $periodo, $m)) {
            $inicio = CarbonImmutable::create((int) $m[1], 1, 1, 0, 0, 0, $zona);

            return [$inicio->utc(), $inicio->addYear()->utc(), 'año ' . $inicio->year];
        }

        throw new InvalidArgumentException("Período no válido: {$periodo}");
    }

    public function etiqueta(): string
    {
        return $this->etiqueta;
    }

    public function periodo(): string
    {
        return $this->periodo;
    }

    /**
     * Una fila por lote terminado, del cierre más reciente al más antiguo.
     *
     * @return list<array<string, mixed>>
     */
    public function desempeno(): array
    {
        return $this->desempenoCache ??= $this->lotes->map(function (Lote $lote) {
            $final = $lote->adjudicacion?->monto;
            $varios = $lote->remate->lotes_count > 1;
            $minutos = $lote->primera_puja && $lote->ultima_puja
                ? CarbonImmutable::parse($lote->primera_puja, 'UTC')->diffInSeconds(CarbonImmutable::parse($lote->ultima_puja, 'UTC')) / 60 : null;
            $resultado = match (true) {
                $final === null => 'No adjudicado',
                $lote->motivo_cierre === Lote::CIERRE_ANTICIPADO => 'Cierre anticipado',
                default => 'Adjudicado',
            };

            return [
                'folio' => $lote->remate->folio,
                'lote' => $varios ? $lote->orden : null,
                'etiqueta' => $this->etiquetaCorta($lote),
                'direccion' => $lote->direccion ?: $lote->titulo,
                'comuna' => $lote->comuna,
                'region' => $lote->region,
                'categoria' => $lote->tipo_propiedad ?: 'Sin categoría',
                'cerrado_en' => $lote->cerrado_en,
                'base' => (int) $lote->precio_base,
                'final' => $final,
                'sobreprecio' => $final !== null ? $final / $lote->precio_base - 1 : null,
                'pujas' => (int) $lote->total_pujas,
                'postores' => (int) $lote->postores_distintos,
                'minutos' => $minutos,
                'motivo' => $lote->motivo_cierre === Lote::CIERRE_ANTICIPADO ? 'Anticipado' : 'Tiempo',
                'resultado' => $resultado,
            ];
        })->values()->all();
    }

    /** @return array<string, int|float|null> */
    public function totales(): array
    {
        $filas = collect($this->desempeno());
        $vendidos = $filas->whereNotNull('final');
        $remates = $this->lotes->pluck('remate_id')->unique();

        return [
            'lotes' => $filas->count(),
            'remates' => $remates->count(),
            'vendidos' => $vendidos->count(),
            'volumen' => (int) $vendidos->sum('final'),
            'tasa' => $filas->count() ? $vendidos->count() / $filas->count() : null,
            'sobreprecio' => $vendidos->count() ? $vendidos->avg('sobreprecio') : null,
            'pujas' => (int) $filas->sum('pujas'),
            'registrados' => $this->postoresRegistrados(),
            'con_garantia' => $this->garantias()->where('estado', Garantia::ESTADO_APROBADA)->pluck('user_id')->unique()->count(),
            'activos' => $this->lotes->isEmpty() ? 0 : Puja::whereIn('lote_id', $this->lotes->pluck('id'))->distinct()->count('user_id'),
            'adjudicatarios' => $this->lotes->map(fn (Lote $l) => $l->adjudicacion?->user_id)->filter()->unique()->count(),
            'garantias_rechazadas' => $this->garantias()->where('estado', Garantia::ESTADO_RECHAZADA)->count(),
        ];
    }

    /** Por categoría de activo, con la fila de total al final. @return list<array<string, mixed>> */
    public function categorias(): array
    {
        $resumen = fn (Collection $filas, string $nombre, bool $total) => [
            'categoria' => $nombre,
            'lotes' => $filas->count(),
            'vendidos' => $filas->whereNotNull('final')->count(),
            'volumen' => (int) $filas->sum('final'),
            'sobreprecio' => $filas->whereNotNull('final')->avg('sobreprecio'),
            'tasa' => $filas->count() ? $filas->whereNotNull('final')->count() / $filas->count() : null,
            'total' => $total,
        ];
        $filas = collect($this->desempeno());

        return $filas->groupBy('categoria')->sortKeys()
            ->map(fn (Collection $grupo, string $nombre) => $resumen($grupo, $nombre, false))->values()
            ->push($resumen($filas, 'Total período', true))->all();
    }

    /**
     * Minutos entre la primera y la última puja, en orden de cierre (los más recientes a la derecha).
     *
     * @return list<array{etiqueta: string, minutos: float}>
     */
    public function dinamica(int $maximo = 12): array
    {
        return collect($this->desempeno())->whereNotNull('minutos')->sortBy('cerrado_en')->take(-$maximo)
            ->map(fn (array $f) => ['etiqueta' => $f['etiqueta'], 'minutos' => $f['minutos']])->values()->all();
    }

    /** Participación por remate del período (sin identidades). @return list<array<string, mixed>> */
    public function participacionPorRemate(): array
    {
        $garantias = $this->garantias()->groupBy('remate_id');

        return $this->lotes->groupBy('remate_id')->map(function (Collection $lotes, int $remateId) use ($garantias) {
            $remate = $lotes->first()->remate;
            $delRemate = $garantias->get($remateId, collect());

            return [
                'folio' => $remate->folio,
                'remate' => $remate->titulo,
                'lotes' => $lotes->count(),
                'inscritos' => $delRemate->pluck('user_id')->unique()->count(),
                'garantias_aprobadas' => $delRemate->where('estado', Garantia::ESTADO_APROBADA)->count(),
                'postores_activos' => Puja::whereIn('lote_id', $lotes->pluck('id'))->distinct()->count('user_id'),
                'pujas' => (int) $lotes->sum('total_pujas'),
                'adjudicados' => $lotes->filter(fn (Lote $l) => $l->adjudicacion !== null)->count(),
            ];
        })->values()->all();
    }

    /** Garantías por remate del período, por estado. @return list<array<string, mixed>> */
    public function garantiasPorRemate(): array
    {
        $remates = $this->lotes->pluck('remate', 'remate_id');

        return $this->garantias()->groupBy('remate_id')->map(function (Collection $grupo, int $remateId) use ($remates) {
            $contar = fn (string $estado) => $grupo->where('estado', $estado)->count();

            return [
                'folio' => $remates[$remateId]->folio,
                'remate' => $remates[$remateId]->titulo,
                'inscritas' => $grupo->count(),
                'pendientes' => $contar(Garantia::ESTADO_PENDIENTE),
                'en_revision' => $contar(Garantia::ESTADO_EN_REVISION),
                'aprobadas' => $contar(Garantia::ESTADO_APROBADA),
                'rechazadas' => $contar(Garantia::ESTADO_RECHAZADA),
                'monto_aprobado' => (int) $grupo->where('estado', Garantia::ESTADO_APROBADA)->sum('monto'),
            ];
        })->sortBy('folio')->values()->all();
    }

    /**
     * Cada puja aceptada del período, con el postor como «Postor #N» de su remate.
     *
     * @return iterable<array<string, mixed>>
     */
    public function pujas(): iterable
    {
        $lotes = $this->lotes->keyBy('id');
        $alias = [];
        foreach ($this->lotes->pluck('remate')->unique('id') as $remate) {
            $alias[$remate->id] = EstadoRemate::alias($remate);
        }

        foreach (Puja::whereIn('lote_id', $lotes->keys())->orderBy('lote_id')->orderBy('id')->lazy(500) as $puja) {
            $lote = $lotes[$puja->lote_id];
            yield [
                'folio' => $lote->remate->folio,
                'lote' => $lote->orden,
                'direccion' => $lote->direccion ?: $lote->titulo,
                'recibida_en' => $puja->recibida_en,
                'monto' => (int) $puja->monto,
                'postor' => $alias[$lote->remate_id][$puja->user_id] ?? 'Postor',
            ];
        }
    }

    /** Lotes terminados de remates reales. */
    private static function lotesTerminados()
    {
        return Lote::query()
            ->join('remates', 'remates.id', '=', 'lotes.remate_id')
            ->where('remates.es_demostracion', false)
            ->where('remates.estado', '!=', Remate::ESTADO_CANCELADO)
            ->whereIn('lotes.estado', Lote::ESTADOS_TERMINALES)
            ->whereNotNull('lotes.cerrado_en');
    }

    private function cargarLotes(): Collection
    {
        $formato = 'Y-m-d H:i:s';

        return self::lotesTerminados()
            ->where('lotes.cerrado_en', '>=', $this->inicio->format($formato))
            ->where('lotes.cerrado_en', '<', $this->fin->format($formato))
            ->select('lotes.*')
            ->selectSub(Puja::selectRaw('min(recibida_en)')->whereColumn('pujas.lote_id', 'lotes.id'), 'primera_puja')
            ->selectSub(Puja::selectRaw('max(recibida_en)')->whereColumn('pujas.lote_id', 'lotes.id'), 'ultima_puja')
            ->selectSub(Puja::selectRaw('count(distinct user_id)')->whereColumn('pujas.lote_id', 'lotes.id'), 'postores_distintos')
            ->with(['remate' => fn ($q) => $q->withCount('lotes'), 'adjudicacion'])
            ->orderByDesc('lotes.cerrado_en')->orderByDesc('lotes.id')
            ->get();
    }

    private function garantias(): Collection
    {
        return $this->garantiasCache ??= $this->lotes->isEmpty()
            ? collect()
            : Garantia::whereIn('remate_id', $this->lotes->pluck('remate_id')->unique())->get(['id', 'remate_id', 'user_id', 'estado', 'monto']);
    }

    private ?Collection $garantiasCache = null;

    private ?array $desempenoCache = null;

    /** Cuentas aprobadas por Colliers hasta el cierre del período (las bloqueadas después también cuentan). */
    private function postoresRegistrados(): int
    {
        return Postor::whereIn('estado', [Postor::ESTADO_APROBADO, Postor::ESTADO_BLOQUEADO])
            ->where(fn ($q) => $q->whereNull('revisado_en')->orWhere('revisado_en', '<', $this->fin->format('Y-m-d H:i:s')))
            ->count();
    }

    /** «R-105» para `R-2026-105`; con varios lotes, «R-105·2». */
    private function etiquetaCorta(Lote $lote): string
    {
        $corto = preg_replace('/^R-\d{4}-/', 'R-', $lote->remate->folio);

        return $lote->remate->lotes_count > 1 ? "{$corto}·{$lote->orden}" : $corto;
    }
}
