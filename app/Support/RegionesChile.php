<?php

namespace App\Support;

/**
 * Regiones de Chile en orden geográfico (norte a sur), para ordenar las opciones de filtro
 * generadas desde los remates publicados.
 */
class RegionesChile
{
    public const ORDEN = [
        'Región de Arica y Parinacota',
        'Región de Tarapacá',
        'Región de Antofagasta',
        'Región de Atacama',
        'Región de Coquimbo',
        'Región de Valparaíso',
        'Región Metropolitana',
        "Región del Libertador General Bernardo O'Higgins",
        'Región del Maule',
        'Región de Ñuble',
        'Región del Biobío',
        'Región de La Araucanía',
        'Región de Los Ríos',
        'Región de Los Lagos',
        'Región de Aysén del General Carlos Ibáñez del Campo',
        'Región de Magallanes y de la Antártica Chilena',
    ];

    /** Nombre corto para mostrar: "Región del Biobío" → "Biobío", "Región Metropolitana" → "Metropolitana". */
    public static function nombreCorto(string $region): string
    {
        return preg_replace('/^Región (del |de )?/u', '', $region);
    }

    /** Valor guardado para «Otra / extranjero» en el registro de postores (el mismo que ya existía). */
    public const OTRA = 'Otra';

    /**
     * Opciones de región del registro de postores (QA 22/10: antes eran 4 y «Otra»), de norte a sur y «Otra» al final.
     * Se guarda el nombre corto, el mismo formato que ya tenían los registros («Metropolitana», «Biobío», «Otra»).
     *
     * @return list<string>
     */
    public static function delRegistro(): array
    {
        return [...array_map(self::nombreCorto(...), self::ORDEN), self::OTRA];
    }

    /** Ordena una lista de regiones según ORDEN; las desconocidas van al final, alfabéticamente. */
    public static function ordenar(array $regiones): array
    {
        $posicion = array_flip(self::ORDEN);
        usort($regiones, fn ($a, $b) => [($posicion[$a] ?? 99), $a] <=> [($posicion[$b] ?? 99), $b]);

        return $regiones;
    }
}
