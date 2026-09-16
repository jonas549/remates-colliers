<?php

namespace App\Models;

use App\Casts\FechaUtc;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Table('postor_documentos')]
#[Fillable(['postor_id', 'tipo', 'ruta', 'nombre_original', 'mime', 'tamano_bytes'])]
class PostorDocumento extends Model
{
    protected function casts(): array
    {
        return [
            'created_at' => FechaUtc::class,
            'updated_at' => FechaUtc::class,
        ];
    }

    public function postor(): BelongsTo
    {
        return $this->belongsTo(Postor::class);
    }
}
