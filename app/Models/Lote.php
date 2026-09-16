<?php

namespace App\Models;

use App\Casts\FechaUtc;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Lote: el activo que se subasta y la fila que bloquea el motor de pujas (Bloque J).
 * `precio_actual`, `ganador_id`, `total_pujas`, `cerrado_en` y `motivo_cierre` NO son asignables en masa:
 * solo los escribe el motor dentro de su transacción.
 */
#[Table('lotes')]
#[Fillable([
    'remate_id', 'orden', 'estado', 'titulo', 'tipo_propiedad', 'direccion', 'comuna', 'region', 'latitud',
    'longitud', 'superficie_util', 'superficie_terraza', 'superficie_terreno', 'dormitorios', 'banos',
    'estacionamientos', 'bodega', 'ocupacion', 'descripcion', 'atributos', 'precio_base', 'duracion_segundos',
    'abre_en', 'cierra_en', 'lote_origen_id',
])]
class Lote extends Model
{
    public const ESTADO_PROGRAMADO = 'programado';

    public const ESTADO_ABIERTO = 'abierto';

    public const ESTADO_LIQUIDANDO = 'liquidando';

    public const ESTADO_ADJUDICADO = 'adjudicado';

    public const ESTADO_DESIERTO = 'desierto';

    public const ESTADO_CERRADO = 'cerrado';

    public const ESTADO_INCUMPLIDO = 'incumplido';

    public const CIERRE_TIEMPO = 'tiempo';

    public const CIERRE_ANTICIPADO = 'anticipado';

    protected function casts(): array
    {
        return [
            'atributos' => 'array',
            'bodega' => 'boolean',
            'precio_base' => 'integer',
            'precio_actual' => 'integer',
            'total_pujas' => 'integer',
            'duracion_segundos' => 'integer',
            'superficie_util' => 'decimal:2',
            'superficie_terraza' => 'decimal:2',
            'superficie_terreno' => 'decimal:2',
            'latitud' => 'decimal:7',
            'longitud' => 'decimal:7',
            'abre_en' => FechaUtc::class,
            'cierra_en' => FechaUtc::class,
            'ultima_puja_en' => FechaUtc::class . ':6',
            'cerrado_en' => FechaUtc::class . ':6',
            'created_at' => FechaUtc::class,
            'updated_at' => FechaUtc::class,
        ];
    }

    public function remate(): BelongsTo
    {
        return $this->belongsTo(Remate::class);
    }

    public function imagenes(): HasMany
    {
        return $this->hasMany(LoteImagen::class)->orderBy('orden');
    }

    public function visitas(): HasMany
    {
        return $this->hasMany(LoteVisita::class)->orderBy('inicia_en');
    }

    public function pujas(): HasMany
    {
        return $this->hasMany(Puja::class);
    }

    public function ganador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ganador_id');
    }

    public function adjudicacion(): HasOne
    {
        return $this->hasOne(Adjudicacion::class);
    }

    public function loteOrigen(): BelongsTo
    {
        return $this->belongsTo(self::class, 'lote_origen_id');
    }

    /** Cierre perezoso (CLAUDE.md §5): cerrado por definición cuando ahora ≥ cierra_en. */
    public function vencido(CarbonInterface $ahora): bool
    {
        return $this->cierra_en !== null && $ahora->greaterThanOrEqualTo($this->cierra_en);
    }
}
