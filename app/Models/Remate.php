<?php

namespace App\Models;

use App\Casts\FechaUtc;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Remate: agrupa uno o más lotes bajo una misma transmisión y un mismo horario.
 * Estados según la propuesta del 15/09 (en revisión con el cliente).
 */
#[Table('remates')]
#[Fillable([
    'folio', 'slug', 'titulo', 'descripcion', 'estado', 'inicio_en', 'cierre_garantias_en',
    'duracion_lote_segundos', 'pausa_entre_lotes_segundos', 'incremento_minimo', 'porcentaje_garantia',
    'youtube_video_id', 'martillero_id', 'publicado_en', 'finalizado_en', 'cancelado_en',
    'mensaje_martillero', 'mensaje_martillero_en', 'motivo_cancelacion', 'remate_origen_id',
])]
class Remate extends Model
{
    public const ESTADO_BORRADOR = 'borrador';

    public const ESTADO_PUBLICADO = 'publicado';

    public const ESTADO_EN_CURSO = 'en_curso';

    public const ESTADO_FINALIZADO = 'finalizado';

    public const ESTADO_CANCELADO = 'cancelado';

    protected function casts(): array
    {
        return [
            'inicio_en' => FechaUtc::class,
            'cierre_garantias_en' => FechaUtc::class,
            'publicado_en' => FechaUtc::class,
            'finalizado_en' => FechaUtc::class,
            'cancelado_en' => FechaUtc::class,
            'mensaje_martillero_en' => FechaUtc::class,
            'created_at' => FechaUtc::class,
            'updated_at' => FechaUtc::class,
            'duracion_lote_segundos' => 'integer',
            'pausa_entre_lotes_segundos' => 'integer',
            'incremento_minimo' => 'integer',
            'porcentaje_garantia' => 'decimal:2',
            'es_demostracion' => 'boolean',
        ];
    }

    public function lotes(): HasMany
    {
        return $this->hasMany(Lote::class)->orderBy('orden');
    }

    public function martillero(): BelongsTo
    {
        return $this->belongsTo(User::class, 'martillero_id');
    }

    public function garantias(): HasMany
    {
        return $this->hasMany(Garantia::class);
    }

    public function documentos(): HasMany
    {
        return $this->hasMany(Documento::class)->orderBy('orden');
    }

    public function remateOrigen(): BelongsTo
    {
        return $this->belongsTo(self::class, 'remate_origen_id');
    }

    /** Remates nuevos creados a partir de este cuando no se concretó (no se reabre: se crea otro). */
    public function republicaciones(): HasMany
    {
        return $this->hasMany(self::class, 'remate_origen_id');
    }

    public const VISTA_BORRADOR = 'borrador';

    public const VISTA_PROXIMO = 'proximo';

    public const VISTA_EN_VIVO = 'en_vivo';

    public const VISTA_ADJUDICADO = 'adjudicado';

    public const VISTA_CERRADO = 'cerrado';

    public const VISTA_CANCELADO = 'cancelado';

    /**
     * Estado que se MUESTRA, derivado del estado guardado y del reloj: un remate publicado pasa a «en vivo» cuando abre su
     * primer lote, aunque nadie haya pujado todavía (el motor lo marca en_curso con la primera puja).
     * Usa los lotes ya cargados si los hay.
     */
    public function estadoVisible(?CarbonInterface $ahora = null): string
    {
        $ahora ??= CarbonImmutable::now('UTC');
        $lotes = $this->relationLoaded('lotes') ? $this->lotes : $this->lotes()->get();

        return match (true) {
            $this->estado === self::ESTADO_BORRADOR => self::VISTA_BORRADOR,
            $this->estado === self::ESTADO_CANCELADO => self::VISTA_CANCELADO,
            $this->estado === self::ESTADO_FINALIZADO || ($lotes->isNotEmpty() && $lotes->every(fn (Lote $l) => in_array($l->estado, Lote::ESTADOS_TERMINALES, true)))
                => $lotes->contains(fn (Lote $l) => in_array($l->estado, [Lote::ESTADO_ADJUDICADO, Lote::ESTADO_CERRADO, Lote::ESTADO_INCUMPLIDO], true))
                    ? self::VISTA_ADJUDICADO : self::VISTA_CERRADO,
            $this->estado === self::ESTADO_EN_CURSO || $this->yaComenzo($ahora) => self::VISTA_EN_VIVO,
            default => self::VISTA_PROXIMO,
        };
    }

    public function abreEn(): ?CarbonImmutable
    {
        $lotes = $this->relationLoaded('lotes') ? $this->lotes : $this->lotes()->get();

        return $lotes->pluck('abre_en')->filter()->sort()->first() ?? $this->inicio_en;
    }

    public function cierraEn(): ?CarbonImmutable
    {
        $lotes = $this->relationLoaded('lotes') ? $this->lotes : $this->lotes()->get();

        return $lotes->pluck('cierra_en')->filter()->sort()->last();
    }

    public function yaComenzo(?CarbonInterface $ahora = null): bool
    {
        $abre = $this->abreEn();

        return $abre !== null && ($ahora ?? CarbonImmutable::now('UTC'))->greaterThanOrEqualTo($abre);
    }

    /** Horario, precios e incremento se pueden cambiar solo antes de que abra el primer lote. */
    public function condicionesEditables(?CarbonInterface $ahora = null): bool
    {
        return in_array($this->estado, [self::ESTADO_BORRADOR, self::ESTADO_PUBLICADO], true) && ! $this->yaComenzo($ahora);
    }

    public function incrementoMinimo(): int
    {
        return $this->incremento_minimo ?? (int) Configuracion::valor('incremento_minimo');
    }

    public function porcentajeGarantia(): string
    {
        return (string) ($this->porcentaje_garantia ?? Configuracion::valor('porcentaje_garantia'));
    }

    /** Supuesto vigente (16/09): la base de la garantía es la suma de los precios base de los lotes. */
    public function baseGarantia(): int
    {
        return (int) $this->lotes()->sum('precio_base');
    }

    /** Monto de la garantía en pesos, redondeado hacia arriba al peso. Aritmética entera, sin flotantes. */
    public function montoGarantia(): int
    {
        $centesimas = (int) round((float) $this->porcentajeGarantia() * 100);

        return intdiv($this->baseGarantia() * $centesimas + 9999, 10000);
    }

    public function duracionLoteSegundos(?Lote $lote = null): int
    {
        return $lote?->duracion_segundos
            ?? $this->duracion_lote_segundos
            ?? (int) Configuracion::valor('duracion_lote_minutos') * 60;
    }

    /**
     * Supuesto vigente (16/09): horario fijo. Calcula `abre_en` y `cierra_en` de cada lote desde el inicio
     * del remate, en orden, con la duración y la pausa configuradas. Un cierre anticipado no los mueve.
     */
    public function programarLotes(): void
    {
        if ($this->inicio_en === null) {
            return;
        }

        $momento = $this->inicio_en;
        foreach ($this->lotes()->get() as $lote) {
            $lote->abre_en = $momento;
            $lote->cierra_en = $momento->addSeconds($this->duracionLoteSegundos($lote));
            $lote->save();
            $momento = $lote->cierra_en->addSeconds($this->pausa_entre_lotes_segundos);
        }
    }
}
