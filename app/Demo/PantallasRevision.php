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
                    ['nombre' => 'Listado de remates', 'ruta' => '/', 'estado' => 'terminada', 'nota' => 'Probar filtros, búsqueda, orden, vista tabla (solo escritorio), cargar más y guardar. En tablet/móvil los filtros se abren como panel lateral.', 'variantes' => [
                        'Visitante' => '/',
                        'Registrado (sin garantía)' => '/?sesion=registrado',
                        'Garantía en revisión' => '/?sesion=en-revision',
                        'Garantía aprobada' => '/?sesion=aprobada',
                    ]],
                    ['nombre' => 'Detalle de remate próximo', 'ruta' => '/remates/militares', 'estado' => 'terminada', 'nota' => 'Mapa con OpenStreetMap en gris claro (el prototipo usaba Esri, servicio deprecado).', 'variantes' => [
                        'Visitante' => '/remates/militares',
                        'Registrado' => '/remates/militares?sesion=registrado',
                        'Garantía en revisión' => '/remates/militares?sesion=en-revision',
                        'Garantía aprobada' => '/remates/militares?sesion=aprobada',
                    ]],
                    ['nombre' => 'Detalle de remate en vivo', 'ruta' => '/remates/apoquindo', 'estado' => 'terminada', 'nota' => 'Historial de pujas y cuenta regresiva en vivo (datos de ejemplo).', 'variantes' => [
                        'Visitante' => '/remates/apoquindo',
                        'Registrado' => '/remates/apoquindo?sesion=registrado',
                        'Garantía en revisión' => '/remates/apoquindo?sesion=en-revision',
                        'Garantía aprobada' => '/remates/apoquindo?sesion=aprobada',
                    ]],
                ],
            ],
            [
                'grupo' => 'Sala de puja',
                'pantallas' => [
                    ['nombre' => 'Puja en vivo', 'ruta' => '/remates/apoquindo/sala', 'estado' => 'terminada', 'nota' => 'Probar puja rápida, monto libre y confirmación. En móvil/tablet: barra fija inferior y botón Pujar que abre la hoja. Las pujas son solo en pantalla hasta el Bloque K.'],
                ],
            ],
            [
                'grupo' => 'Administración',
                'pantallas' => [
                    ['nombre' => 'Dashboard', 'ruta' => '/admin', 'estado' => 'terminada', 'nota' => 'En móvil/tablet: barra superior y menú deslizable (botón ☰).'],
                    ['nombre' => 'Subastas', 'ruta' => '/admin/subastas', 'estado' => 'terminada', 'nota' => 'Probar Crear subasta, pestañas de filtro y Cerrar ahora (modal). En móvil: botón Acciones abre una hoja inferior.'],
                    ['nombre' => 'Postores', 'ruta' => '/admin/postores', 'estado' => 'terminada', 'nota' => 'Probar filtros, búsqueda, Aprobar/Rechazar y Ficha. En móvil cada postor es una tarjeta (criterio: aprobar una garantía desde el celular).'],
                    ['nombre' => 'Reportes', 'ruta' => '/admin/reportes', 'estado' => 'terminada', 'nota' => 'Gráficos en CSS, sin librerías. CSV/XLSX/PDF aún sin funcionar (Bloque O).'],
                ],
            ],
        ];
    }
}
