<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Plantilla de correo editada desde el panel. Sin fila = se usa la original del catálogo (App\Correo\Plantillas). */
class PlantillaCorreo extends Model
{
    protected $table = 'plantillas_correo';

    protected $fillable = ['clave', 'asunto', 'cuerpo', 'boton', 'actualizado_por_id'];

    public function actualizadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actualizado_por_id');
    }
}
