<?php

namespace App\Models;

use App\Casts\FechaUtc;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Puja ACEPTADA. La tabla solo crece (CLAUDE.md §5): el modelo impide editar o borrar.
 * Ojo: las consultas masivas (`Puja::query()->delete()`) no pasan por el modelo; no se usan en la aplicación.
 */
#[Table('pujas')]
#[WithoutTimestamps]
#[Fillable(['lote_id', 'user_id', 'monto', 'recibida_en', 'ip', 'user_agent'])]
class Puja extends Model
{
    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Las pujas no se editan.'));
        static::deleting(fn () => throw new LogicException('Las pujas no se borran.'));
    }

    protected function casts(): array
    {
        return [
            'monto' => 'integer',
            'recibida_en' => FechaUtc::class . ':6',
        ];
    }

    public function lote(): BelongsTo
    {
        return $this->belongsTo(Lote::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
