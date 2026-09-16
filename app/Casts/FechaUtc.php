<?php

namespace App\Casts;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Fecha guardada SIEMPRE en UTC (CLAUDE.md §6). El cast `datetime` de Laravel guarda la hora tal como
 * viene, sin convertir la zona: una fecha armada en America/Santiago quedaría 3–4 horas corrida.
 * Este cast la convierte a UTC al guardar y la devuelve en UTC; la vista la muestra en Santiago.
 *
 * Uso: FechaUtc::class (segundos) o FechaUtc::class . ':6' (microsegundos, para la hora de recepción de pujas).
 */
class FechaUtc implements CastsAttributes
{
    public function __construct(private readonly int $precision = 0) {}

    public function get(Model $model, string $key, mixed $value, array $attributes): ?CarbonImmutable
    {
        if ($value === null) {
            return null;
        }

        return CarbonImmutable::parse($value, 'UTC');
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $fecha = $value instanceof DateTimeInterface
            ? CarbonImmutable::instance($value)
            : CarbonImmutable::parse($value, 'UTC');

        return $fecha->utc()->format($this->precision > 0 ? 'Y-m-d H:i:s.u' : 'Y-m-d H:i:s');
    }
}
