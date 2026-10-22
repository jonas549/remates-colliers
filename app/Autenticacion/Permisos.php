<?php

namespace App\Autenticacion;

use App\Models\User;

/**
 * Qué puede hacer cada rol interno en el panel (22/10). Es el único lugar donde se decide: las rutas (middleware
 * `permiso:x`), el menú lateral y los botones de las pantallas preguntan aquí.
 *
 * Los permisos del Editor son la PROPUESTA de Jonas, todavía a confirmar con el cliente: para ajustarlos basta
 * agregar o quitar el rol en la lista de cada permiso.
 *
 * Además de esto, el martillero solo ve los remates a su cargo (se revisa en cada controlador).
 */
final class Permisos
{
    public const MAPA = [
        // Entrar al panel.
        'panel' => [User::ROL_ADMIN, User::ROL_MARTILLERO, User::ROL_EDITOR],
        // «01 Dashboard»: pendientes de postores y garantías (administración) o los remates a cargo (martillero).
        'dashboard' => [User::ROL_ADMIN, User::ROL_MARTILLERO],
        // «02 Subastas»: listado y ficha.
        'remates.ver' => [User::ROL_ADMIN, User::ROL_MARTILLERO, User::ROL_EDITOR],
        // Crear y editar remates y lotes, fotos, documentos y horarios de visita; «Crear remate nuevo» desde uno cerrado.
        'remates.editar' => [User::ROL_ADMIN, User::ROL_EDITOR],
        // Publicar y despublicar.
        'remates.publicar' => [User::ROL_ADMIN, User::ROL_EDITOR],
        // Cancelar un remate y «Cerrar ahora» (cierre anticipado).
        'remates.cerrar' => [User::ROL_ADMIN],
        // Subastas de prueba: crean postores con clave.
        'remates.prueba' => [User::ROL_ADMIN],
        // Panel en vivo (el martillero, solo el de sus remates).
        'en_vivo' => [User::ROL_ADMIN, User::ROL_MARTILLERO],
        // «04 Reportes» y sus exportables (sin datos personales).
        'reportes' => [User::ROL_ADMIN, User::ROL_MARTILLERO],
        // «03 Postores»: datos personales, cuentas y garantías.
        'postores' => [User::ROL_ADMIN],
        // «06 Usuarios»: cuentas internas.
        'usuarios' => [User::ROL_ADMIN],
        // «05 Configuración»: cada sección tiene su permiso; las que no figuran en CONFIGURACION son solo de administración.
        'configuracion' => [User::ROL_ADMIN],
        // Segundo factor por correo al ingresar (22/10). Por ahora solo Administración; extenderlo a otro rol es
        // agregarlo aquí (App\Autenticacion\SegundoFactor no cambia).
        'dos_pasos' => [User::ROL_ADMIN],
    ];

    /** Secciones de Configuración abiertas a otros roles además de administración. */
    public const CONFIGURACION = [
        // Textos, enlaces, contacto y UF del sitio público.
        'sitio' => [User::ROL_ADMIN, User::ROL_EDITOR],
    ];

    public static function permite(?User $user, string $permiso): bool
    {
        return $user !== null && $user->estado === User::ESTADO_ACTIVO
            && in_array($user->rol, self::MAPA[$permiso] ?? [], true);
    }

    public static function configuracion(?User $user, string $seccion): bool
    {
        return $user !== null && $user->estado === User::ESTADO_ACTIVO
            && in_array($user->rol, self::CONFIGURACION[$seccion] ?? self::MAPA['configuracion'], true);
    }

    /** Secciones de Configuración que este usuario ve, en el orden del menú. @return list<string> */
    public static function seccionesConfiguracion(?User $user): array
    {
        return array_values(array_filter(array_keys(\App\Models\Configuracion::SECCIONES), fn (string $s) => self::configuracion($user, $s)));
    }
}
