<?php

namespace App\Models;

use App\Casts\FechaUtc;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Table('lote_visitas')]
#[Fillable(['lote_id', 'inicia_en', 'termina_en', 'notas'])]
class LoteVisita extends Model
{
    protected function casts(): array
    {
        return [
            'inicia_en' => FechaUtc::class,
            'termina_en' => FechaUtc::class,
            'created_at' => FechaUtc::class,
            'updated_at' => FechaUtc::class,
        ];
    }

    public function lote(): BelongsTo
    {
        return $this->belongsTo(Lote::class);
    }
}
