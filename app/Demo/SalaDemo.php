<?php

namespace App\Demo;

/**
 * Datos fijos de la sala del prototipo (Puja en Vivo.dc.html). Solo para la comparación visual 1:1 en local
 * (`/remates/{remate}/sala?demo=1`): con datos reales la cuenta regresiva y los «hace N seg» cambian en cada corrida.
 */
class SalaDemo
{
    public static function datos(): array
    {
        return [
            'componente' => 'salaPujaDemo',
            'config' => [
                'base' => 185000000,
                'paso' => 100000,
                'actual' => 198500000,
                'deltaCierre' => 7 * 60 + 12,
                'postor' => 21,
                // monto, postor, segundos atrás, es mía
                'historial' => [
                    [198500000, 7, 34, false], [195000000, 3, 96, false], [191500000, 21, 172, true],
                    [188000000, 12, 240, false], [186500000, 3, 318, false], [185000000, 12, 402, false],
                ],
            ],
            'propiedad' => [
                'slug' => 'apoquindo',
                'folio' => 'R-2026-114',
                'direccion' => 'Av. Apoquindo 4501, Depto. 1802',
                'meta' => 'Las Condes, Región Metropolitana · Departamento · 118 m² útiles · 3D / 2B · Desocupada',
                'base' => 185000000,
                'incremento' => 100000,
                'garantia' => 8000000,
                'martillero' => 'M. Ossandón',
                'video' => 'jfKfPfyJRdk',
                'usuario' => 'María Paz González · Postor #21',
            ],
        ];
    }
}
