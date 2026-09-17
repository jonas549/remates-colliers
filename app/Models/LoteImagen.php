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

    /** Las fotos subidas desde el panel viven en el disco público; las de muestra del seeder, en public/img. */
    public function url(): string
    {
        return asset(str_starts_with($this->ruta, 'img/') ? $this->ruta : 'storage/' . $this->ruta);
    }

    /** Solo se borra el archivo de una foto subida y que ninguna otra fila usa (un remate republicado las comparte). */
    public function archivoPropio(): bool
    {
        return ! str_starts_with($this->ruta, 'img/') && ! self::where('ruta', $this->ruta)->whereKeyNot($this->id)->exists();
    }
}
