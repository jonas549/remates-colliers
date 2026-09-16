<?php

namespace App\Models\Concerns;

use App\Support\Rut;
use Illuminate\Database\Eloquent\Builder;

/**
 * RUT cifrado con índice ciego (CLAUDE.md §6). El modelo necesita las columnas `rut` (texto, cast
 * `encrypted`) y `rut_indice` (char 64, único). Al guardar, el RUT se normaliza a 12.345.678-9 y se
 * recalcula el índice; un RUT con formato inválido lanza excepción (la validación de formulario va antes).
 */
trait TieneRutCifrado
{
    public static function bootTieneRutCifrado(): void
    {
        static::saving(function (self $modelo) {
            if ($modelo->isDirty('rut') || $modelo->rut_indice === null) {
                $modelo->rut = Rut::formatear($modelo->rut);
                $modelo->rut_indice = Rut::indiceCiego($modelo->rut);
            }
        });
    }

    public function scopePorRut(Builder $consulta, string $rut): Builder
    {
        if (Rut::normalizar($rut) === null) {
            return $consulta->whereRaw('1 = 0');
        }

        return $consulta->where('rut_indice', Rut::indiceCiego($rut));
    }
}
