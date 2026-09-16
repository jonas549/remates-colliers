<?php

namespace App\Models;

use App\Casts\FechaUtc;
use App\Models\Concerns\TieneRutCifrado;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Datos del postor (la cuenta vive en `users`). Estados de cuenta según la propuesta del 15/09,
 * en revisión con el cliente: guardados como texto para cambiarlos sin migrar.
 */
#[Table('postores')]
#[Fillable([
    'user_id', 'tipo', 'nombres', 'apellidos', 'rut', 'fecha_nacimiento', 'nacionalidad', 'estado_civil',
    'telefono', 'direccion', 'comuna', 'region', 'empresa_id', 'calidad', 'origen', 'acepta_terminos_en',
    'datos_extra',
])]
#[Hidden(['rut_indice'])]
class Postor extends Model
{
    use TieneRutCifrado;

    public const TIPO_NATURAL = 'natural';

    public const TIPO_JURIDICA = 'juridica';

    public const ESTADO_REGISTRADO = 'registrado';

    public const ESTADO_EN_REVISION = 'en_revision';

    public const ESTADO_APROBADO = 'aprobado';

    public const ESTADO_RECHAZADO = 'rechazado';

    public const ESTADO_BLOQUEADO = 'bloqueado';

    protected function casts(): array
    {
        return [
            'rut' => 'encrypted',
            'fecha_nacimiento' => 'date:Y-m-d',
            'datos_extra' => 'array',
            'acepta_terminos_en' => FechaUtc::class,
            'revisado_en' => FechaUtc::class,
            'created_at' => FechaUtc::class,
            'updated_at' => FechaUtc::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function documentos(): HasMany
    {
        return $this->hasMany(PostorDocumento::class);
    }

    public function revisadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revisado_por_id');
    }

    public function nombreCompleto(): string
    {
        return trim($this->nombres . ' ' . $this->apellidos);
    }

    public function estaAprobado(): bool
    {
        return $this->estado === self::ESTADO_APROBADO;
    }
}
