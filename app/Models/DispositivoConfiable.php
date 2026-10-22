<?php

namespace App\Models;

use App\Casts\FechaUtc;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** «Recordar este dispositivo» del segundo factor (22/10). La cookie lleva el token; aquí va su hash. */
#[Table('dispositivos_confiables')]
#[Fillable(['user_id', 'token_hash', 'user_agent', 'ip', 'expira_en'])]
#[Hidden(['token_hash'])]
class DispositivoConfiable extends Model
{
    protected function casts(): array
    {
        return [
            'expira_en' => FechaUtc::class,
            'ultimo_uso_en' => FechaUtc::class,
            'revocado_en' => FechaUtc::class,
            'created_at' => FechaUtc::class,
            'updated_at' => FechaUtc::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Vigentes: sin revocar y sin vencer. */
    public function scopeVigentes($consulta)
    {
        return $consulta->whereNull('revocado_en')->where('expira_en', '>', now('UTC')->format('Y-m-d H:i:s'));
    }
}
