<?php

namespace App\Models;

use App\Casts\FechaUtc;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Notifications\Notifiable;

/** «Avísame» (Bloque M). Sin remate: remates nuevos y cierre de garantías. Con remate: recordatorio antes de que comience. */
#[Table('suscripciones')]
#[Fillable(['email', 'remate_id', 'user_id', 'token', 'ip', 'baja_en'])]
class Suscripcion extends Model
{
    use Notifiable;

    protected function casts(): array
    {
        return [
            'baja_en' => FechaUtc::class,
            'created_at' => FechaUtc::class,
            'updated_at' => FechaUtc::class,
        ];
    }

    public function remate(): BelongsTo
    {
        return $this->belongsTo(Remate::class);
    }

    public function scopeVigentes(Builder $consulta): Builder
    {
        return $consulta->whereNull('baja_en');
    }

    public function routeNotificationForMail(): string
    {
        return $this->email;
    }
}
