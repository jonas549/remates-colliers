<?php

namespace App\Models;

use App\Casts\FechaUtc;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Resultado de un lote adjudicado. Un lote desierto no tiene adjudicación. */
#[Table('adjudicaciones')]
#[Fillable(['lote_id', 'user_id', 'puja_id', 'monto', 'cerrado_en', 'motivo_cierre', 'estado'])]
class Adjudicacion extends Model
{
    public const ESTADO_ADJUDICADO = 'adjudicado';

    public const ESTADO_CERRADO = 'cerrado';

    public const ESTADO_INCUMPLIDO = 'incumplido';

    protected function casts(): array
    {
        return [
            'monto' => 'integer',
            'cerrado_en' => FechaUtc::class . ':6',
            'notificado_ganador_en' => FechaUtc::class,
            'notificado_admin_en' => FechaUtc::class,
            'created_at' => FechaUtc::class,
            'updated_at' => FechaUtc::class,
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

    public function puja(): BelongsTo
    {
        return $this->belongsTo(Puja::class);
    }
}
