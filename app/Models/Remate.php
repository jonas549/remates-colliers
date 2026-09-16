<?php

namespace App\Models;

use App\Casts\FechaUtc;
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
            'created_at' => FechaUtc::class,
            'updated_at' => FechaUtc::class,
            'duracion_lote_segundos' => 'integer',
            'pausa_entre_lotes_segundos' => 'integer',
            'incremento_minimo' => 'integer',
            'porcentaje_garantia' => 'decimal:2',
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
