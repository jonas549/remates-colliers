<?php

namespace App\Models;

use App\Casts\FechaUtc;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/** Registro de notificaciones enviadas o fallidas (Bloque M). */
#[Table('notificaciones_log')]
#[Fillable(['user_id', 'canal', 'tipo', 'destinatario', 'asunto', 'estado', 'error', 'notificable_type', 'notificable_id', 'enviada_en'])]
class NotificacionLog extends Model
{
    protected function casts(): array
    {
        return [
            'enviada_en' => FechaUtc::class,
            'created_at' => FechaUtc::class,
            'updated_at' => FechaUtc::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function notificable(): MorphTo
    {
        return $this->morphTo();
    }
}
