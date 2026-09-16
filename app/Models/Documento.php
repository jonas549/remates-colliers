<?php

namespace App\Models;

use App\Casts\FechaUtc;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Documento descargable del remate (lote_id nulo, p. ej. bases) o de un lote en particular. */
#[Table('documentos')]
#[Fillable(['remate_id', 'lote_id', 'titulo', 'ruta', 'nombre_original', 'mime', 'tamano_bytes', 'orden', 'publico'])]
class Documento extends Model
{
    protected function casts(): array
    {
        return [
            'publico' => 'boolean',
            'created_at' => FechaUtc::class,
            'updated_at' => FechaUtc::class,
        ];
    }

    public function remate(): BelongsTo
    {
        return $this->belongsTo(Remate::class);
    }

    public function lote(): BelongsTo
    {
        return $this->belongsTo(Lote::class);
    }
}
