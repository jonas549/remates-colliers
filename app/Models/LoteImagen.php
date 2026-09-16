<?php

namespace App\Models;

use App\Casts\FechaUtc;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Table('lote_imagenes')]
#[Fillable(['lote_id', 'ruta', 'orden', 'texto_alternativo', 'credito'])]
class LoteImagen extends Model
{
    protected function casts(): array
    {
        return [
            'created_at' => FechaUtc::class,
            'updated_at' => FechaUtc::class,
        ];
    }

    public function lote(): BelongsTo
    {
        return $this->belongsTo(Lote::class);
    }
}
