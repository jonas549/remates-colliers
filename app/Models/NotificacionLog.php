<?php

namespace App\Models;

use App\Casts\FechaUtc;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Registro de correos (Bloque M). El estado dice lo que respondió el transporte, no lo que suponemos (17/09):
 * pendiente · aceptada · registrada (modo log: no salió) · fallida · sin_verificar (filas anteriores a la corrección).
 */
#[Table('notificaciones_log')]
#[Fillable(['user_id', 'canal', 'transporte', 'tipo', 'destinatario', 'remitente', 'asunto', 'estado', 'respuesta', 'message_id', 'error', 'notificable_type', 'notificable_id', 'enviada_en'])]
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

    public const ESTADOS = [
        'aceptada' => 'Aceptada por el servidor',
        'registrada' => 'Solo registrada (no salió)',
        'fallida' => 'Fallida',
        'pendiente' => 'Pendiente en la cola',
        'sin_verificar' => 'Sin verificar (anterior a la corrección)',
    ];

    /** Etiqueta corta de la insignia: «registrada» a secas se lee como enviada, y no lo es. */
    public const ETIQUETAS = [
        'aceptada' => 'ACEPTADA', 'registrada' => 'NO SALIÓ', 'fallida' => 'FALLIDA',
        'pendiente' => 'EN LA COLA', 'sin_verificar' => 'SIN VERIFICAR',
    ];

    /** Estados que cuentan como «ya se intentó», para no duplicar un recordatorio. */
    public const YA_INTENTADA = ['aceptada', 'registrada', 'sin_verificar', 'pendiente'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function notificable(): MorphTo
    {
        return $this->morphTo();
    }
}
