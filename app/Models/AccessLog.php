<?php

namespace App\Models;

use App\Casts\FechaUtc;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Bitácora de accesos (Bloque D). Solo crece: tiene `created_at` y no `updated_at`. */
#[Table('access_logs')]
#[Fillable(['user_id', 'email', 'evento', 'ip', 'user_agent', 'detalle'])]
class AccessLog extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'detalle' => 'array',
            'created_at' => FechaUtc::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
