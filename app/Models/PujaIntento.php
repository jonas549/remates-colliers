<?php

namespace App\Models;

use App\Casts\FechaUtc;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Model;

/** Intento de puja rechazado. Se escribe FUERA de la transacción de la puja (un rollback lo borraría). */
#[Table('puja_intentos')]
#[WithoutTimestamps]
#[Fillable(['lote_id', 'user_id', 'monto', 'motivo', 'detalle', 'recibida_en', 'ip', 'user_agent'])]
class PujaIntento extends Model
{
    protected function casts(): array
    {
        return [
            'monto' => 'integer',
            'detalle' => 'array',
            'recibida_en' => FechaUtc::class . ':6',
        ];
    }
}
