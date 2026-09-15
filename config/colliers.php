<?php

/*
 * Configuración propia de Remates Colliers que depende del entorno (.env).
 * Todo valor de NEGOCIO (incrementos, % de garantía, plazos, SMTP, textos) va a base de datos y se edita
 * desde el panel (Bloque V). Aquí solo queda lo que el servidor necesita antes de que exista la base.
 */

return [

    /*
     * Primer administrador. `colliers:instalar` lo crea si todavía no existe ningún administrador.
     * La clave del .env solo se usa esa primera vez; el sistema obliga a cambiarla en el primer ingreso.
     */
    'admin' => [
        'nombre' => env('COLLIERS_ADMIN_NOMBRE', 'Administrador Colliers'),
        'email' => env('COLLIERS_ADMIN_EMAIL'),
        'clave' => env('COLLIERS_ADMIN_CLAVE'),
    ],

    /*
     * Clave de acceso al sitio mientras es un sandbox público. Si está vacía, el sitio queda abierto.
     * En producción se deja vacía.
     */
    'acceso' => [
        'clave' => env('COLLIERS_ACCESO_CLAVE'),
        'dias' => (int) env('COLLIERS_ACCESO_DIAS', 30),
    ],

    /*
     * Archivo que, si existe, bloquea el deploy (colliers:puede-desplegar responde "no").
     * Sirve para congelar deploys a mano; el Bloque J agrega el bloqueo automático con remate en curso.
     */
    'bloqueo_deploy' => storage_path('app/bloquear-deploy'),

    /* Archivo que el programador de tareas actualiza cada minuto; colliers:diagnostico lo revisa. */
    'latido_programador' => storage_path('framework/latido-programador'),

    /* Zona horaria en que se muestran las fechas. En base de datos todo se guarda en UTC. */
    'zona_visualizacion' => env('COLLIERS_ZONA_VISUALIZACION', 'America/Santiago'),

];
