<?php

namespace App\Models;

use App\Casts\FechaUtc;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Código de ingreso enviado por correo (segundo factor, 22/10). Solo el hash; nunca el código en claro. */
#[Table('codigos_ingreso')]
#[Fillable(['user_id', 'codigo_hash', 'expira_en', 'ip', 'user_agent'])]
#[Hidden(['codigo_hash'])]
class CodigoIngreso extends Model
{
    protected function casts(): array
    {
        return [
            'expira_en' => FechaUtc::class,
            'usado_en' => FechaUtc::class,
            'descartado_en' => FechaUtc::class,
            'created_at' => FechaUtc::class,
            'updated_at' => FechaUtc::class,
            'intentos' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
