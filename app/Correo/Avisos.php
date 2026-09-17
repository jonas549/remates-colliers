<?php

namespace App\Correo;

use App\Models\Configuracion;

/**
 * Interruptores de los avisos (pantalla Notificaciones, 17/09). Sin fila en `configuraciones` = activo: agregar una
 * plantilla nueva no exige tocar la configuración. Los correos de la propia cuenta (confirmar correo, restablecer la
 * contraseña) no se pueden apagar: sin ellos el postor no puede entrar.
 */
class Avisos
{
    public static function activo(string $plantilla): bool
    {
        if (empty(Plantillas::CATALOGO[$plantilla]['activable'])) {
            return true;
        }

        return (bool) Configuracion::valor(self::clave($plantilla), true);
    }

    public static function guardar(string $plantilla, bool $activo): void
    {
        Configuracion::updateOrCreate(
            ['clave' => self::clave($plantilla)],
            ['valor' => $activo ? '1' : '0', 'tipo' => 'booleano', 'grupo' => 'notificaciones',
                'descripcion' => 'Enviar el correo «' . Plantillas::nombre($plantilla) . '»'],
        );
    }

    public static function clave(string $plantilla): string
    {
        return "aviso_{$plantilla}";
    }
}
