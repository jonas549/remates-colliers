<?php

namespace App\Models;

use App\Casts\FechaUtc;
use App\Models\Concerns\TieneRutCifrado;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Persona jurídica. Supuesto vigente (16/09): una empresa = una cuenta, validado en la aplicación. */
#[Table('empresas')]
#[Fillable(['rut', 'razon_social', 'giro'])]
#[Hidden(['rut_indice'])]
class Empresa extends Model
{
    use TieneRutCifrado;

    protected function casts(): array
    {
        return [
            'rut' => 'encrypted',
            'created_at' => FechaUtc::class,
            'updated_at' => FechaUtc::class,
        ];
    }

    public function postores(): HasMany
    {
        return $this->hasMany(Postor::class);
    }
}
