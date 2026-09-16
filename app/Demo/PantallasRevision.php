<?php

namespace App\Demo;

/**
 * Índice de revisión (solo local): pantallas terminadas y sus variantes.
 * Las pantallas protegidas entran primero como un usuario del seeder (/revision/entrar/{rol}).
 */
class PantallasRevision
{
    private static function como(string $rol, string $ruta): string
    {
        return '/revision/entrar/' . $rol . '?a=' . rawurlencode($ruta);
    }

    public static function todas(): array
    {
        return [
            [
                'grupo' => 'Acceso y cuenta',
                'pantallas' => [
                    ['nombre' => 'Login', 'ruta' => '/ingresar', 'estado' => 'terminada', 'nota' => 'Ingreso real (Bloque D): correo o RUT, 5 intentos y bloqueo de 15 min. Con sesión abierta redirige: cierra sesión antes.', 'variantes' => [
                        'Postores' => '/ingresar',
                        'Administradores' => '/admin/ingresar',
                    ]],
                    ['nombre' => 'Recuperar y restablecer contraseña', 'ruta' => '/recuperar-clave', 'estado' => 'terminada', 'nota' => 'Sin diseño propio: diseño del Login (decisión del 16/09). En local el correo queda en storage/logs.'],
                    ['nombre' => 'Cambiar contraseña', 'ruta' => self::como('postor', '/mi-cuenta/cambiar-clave'), 'estado' => 'terminada', 'nota' => 'Sin diseño propio: diseño del Login. Obligatoria para el primer administrador y tras un restablecimiento por admin.'],
                    ['nombre' => 'Sesiones activas', 'ruta' => self::como('postor', '/mi-cuenta/sesiones'), 'estado' => 'terminada', 'nota' => 'Sin diseño propio: diseño del Login. Requiere SESSION_DRIVER=database.'],
                    ['nombre' => 'Registro de postor', 'ruta' => '/registro', 'estado' => 'terminada', 'nota' => 'Registro real (Bloque D). Probar persona jurídica y RUT 12.345.678-5 (válido) / 12.345.678-9 (inválido). Después pide confirmar el correo (enlace en storage/logs).'],
                    ['nombre' => 'Estado de cuenta', 'ruta' => self::como('postor', '/mi-cuenta'), 'estado' => 'terminada', 'variantes' => [
                        'Cuenta en revisión' => self::como('postor', '/mi-cuenta?estado=cuenta-revision'),
                        'Garantía pendiente' => self::como('postor', '/mi-cuenta?estado=garantia-pendiente'),
                        'Garantía en revisión' => self::como('postor', '/mi-cuenta?estado=garantia-revision'),
                        'Aprobada' => self::como('postor', '/mi-cuenta?estado=aprobada'),
                        'Rechazada' => self::como('postor', '/mi-cuenta?estado=rechazada'),
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
                    ['nombre' => 'Puja en vivo', 'ruta' => self::como('postor', '/remates/apoquindo/sala'), 'estado' => 'terminada', 'nota' => 'Conectada al motor (Bloque K): las pujas son reales. Abre otra ventana privada como otro postor para ver la actualización en vivo. Con la base recién sembrada el remate cierra en 42 min.', 'variantes' => [
                        'Real (María Paz, Postor #1)' => self::como('postor', '/remates/apoquindo/sala'),
                        'Datos del prototipo (?demo=1)' => self::como('postor', '/remates/apoquindo/sala?demo=1'),
                    ]],
                ],
            ],
            [
                'grupo' => 'Administración',
                'pantallas' => [
                    ['nombre' => 'Dashboard', 'ruta' => self::como('admin', '/admin'), 'estado' => 'terminada', 'nota' => 'En móvil/tablet: barra superior y menú deslizable (botón ☰).'],
                    ['nombre' => 'Subastas', 'ruta' => self::como('admin', '/admin/subastas'), 'estado' => 'terminada', 'nota' => 'Probar Crear subasta, pestañas de filtro y Cerrar ahora (modal). En móvil: botón Acciones abre una hoja inferior.'],
                    ['nombre' => 'Postores', 'ruta' => self::como('admin', '/admin/postores'), 'estado' => 'terminada', 'nota' => 'Probar filtros, búsqueda, Aprobar/Rechazar y Ficha. En móvil cada postor es una tarjeta (criterio: aprobar una garantía desde el celular).'],
                    ['nombre' => 'Reportes', 'ruta' => self::como('admin', '/admin/reportes'), 'estado' => 'terminada', 'nota' => 'Gráficos en CSS, sin librerías. CSV/XLSX/PDF aún sin funcionar (Bloque O).'],
                ],
            ],
        ];
    }
}
