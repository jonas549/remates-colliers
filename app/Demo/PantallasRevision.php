<?php

namespace App\Demo;

/**
 * Índice de revisión del Bloque T: pantallas terminadas y sus variantes.
 * Se actualiza al cerrar cada pantalla. Se elimina (y "/" pasa a ser el listado) al cerrar T.
 */
class PantallasRevision
{
    public static function todas(): array
    {
        return [
            [
                'grupo' => 'Acceso y cuenta',
                'pantallas' => [
                    ['nombre' => 'Login', 'ruta' => '/ingresar', 'estado' => 'terminada', 'nota' => 'Móvil: franja de 180px con el próximo remate.'],
                    ['nombre' => 'Registro de postor', 'ruta' => '/registro', 'estado' => 'terminada', 'nota' => 'Probar persona jurídica y RUT 12.345.678-5 (válido) / 12.345.678-9 (inválido).'],
                    ['nombre' => 'Estado de cuenta', 'ruta' => '/mi-cuenta', 'estado' => 'terminada', 'variantes' => [
                        'Cuenta en revisión' => '/mi-cuenta?estado=cuenta-revision',
                        'Garantía pendiente' => '/mi-cuenta?estado=garantia-pendiente',
                        'Garantía en revisión' => '/mi-cuenta?estado=garantia-revision',
                        'Aprobada' => '/mi-cuenta?estado=aprobada',
                        'Rechazada' => '/mi-cuenta?estado=rechazada',
                    ]],
                ],
            ],
            [
                'grupo' => 'Sitio público',
                'pantallas' => [
                    ['nombre' => 'Listado de remates', 'ruta' => '/remates', 'estado' => 'pendiente'],
                    ['nombre' => 'Detalle de remate próximo', 'ruta' => null, 'estado' => 'pendiente'],
                    ['nombre' => 'Detalle de remate en vivo', 'ruta' => null, 'estado' => 'pendiente'],
                ],
            ],
            [
                'grupo' => 'Sala de puja',
                'pantallas' => [
                    ['nombre' => 'Puja en vivo', 'ruta' => null, 'estado' => 'pendiente'],
                ],
            ],
            [
                'grupo' => 'Administración',
                'pantallas' => [
                    ['nombre' => 'Dashboard', 'ruta' => null, 'estado' => 'pendiente'],
                    ['nombre' => 'Subastas', 'ruta' => null, 'estado' => 'pendiente'],
                    ['nombre' => 'Postores', 'ruta' => null, 'estado' => 'pendiente'],
                    ['nombre' => 'Reportes', 'ruta' => null, 'estado' => 'pendiente'],
                ],
            ],
        ];
    }
}
