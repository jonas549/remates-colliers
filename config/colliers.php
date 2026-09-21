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

    /*
     * Límite real de `post_max_size` del servidor. Vacío = el que informa PHP. Se declara aquí porque cuando un envío
     * lo supera, PHP descarta el cuerpo entero y el formulario volvería vacío: `SubidaDemasiadoGrande` lo explica.
     */
    'limite_post' => env('COLLIERS_LIMITE_POST'),

    /*
     * Tiempo real (Bloque J). `json`: estado del remate como archivo estático en `carpeta`, servido por
     * LiteSpeed sin ejecutar PHP. La capa está abstraída (App\Subastas\Difusion\Emisor) para cambiar a Pusher.
     */
    'tiempo_real' => [
        'driver' => env('COLLIERS_TIEMPO_REAL', 'json'),
        'carpeta' => env('COLLIERS_TIEMPO_REAL_CARPETA', public_path('tiempo-real')),
    ],

    /*
     * Límites técnicos de protección (no son reglas de negocio del acta): peticiones por minuto.
     * Un postor real no se acerca a 30 pujas por minuto; los espectadores consultan el JSON estático, no PHP.
     */
    'limites' => [
        'pujas_por_minuto' => (int) env('COLLIERS_PUJAS_POR_MINUTO', 30),
        'estado_por_minuto' => (int) env('COLLIERS_ESTADO_POR_MINUTO', 60),
    ],

    /*
     * Reintento de la conexión a la base en la petición de puja (21/09). En un hosting compartido MariaDB puede rechazar
     * conexiones unos segundos (1040 Too many connections) o reiniciarse (2002). La hora de recepción ya está sellada
     * antes de arrancar Laravel (HoraRecepcion), así que esperar no cambia la validez de la puja.
     *
     * Estos valores fijan el MARGEN DE LIQUIDACIÓN SEGURO: una puja recibida antes de T tiene que tomar el bloqueo del
     * lote antes de T + margen, o la adjudicación se le adelanta. Seguro = reintento + procesamiento, redondeado arriba
     * (App\Subastas\ConexionPuja::margenSeguroSegundos). El panel advierte y pide confirmación por debajo de eso.
     * `procesamiento_ms`: desde que llega la puja hasta que toma el bloqueo, sin OPcache, 1 núcleo y 10 simultáneas. El
     * 17/09 se midió una mediana de ~4 s hasta la RESPUESTA (docs/RENDIMIENTO-SIN-OPCACHE.md); el bloqueo se toma antes de
     * responder, de ahí 3,5 s. Con OPcache en producción se puede bajar.
     */
    'conexion_puja' => [
        'reintento_ms' => (int) env('COLLIERS_PUJA_REINTENTO_MS', 1500),
        'espera_ms' => (int) env('COLLIERS_PUJA_REINTENTO_ESPERA_MS', 500),
        'procesamiento_ms' => (int) env('COLLIERS_PUJA_PROCESAMIENTO_MS', 3500),
    ],

    /*
     * Bloqueo automático de deploy: no se despliega desde estos minutos antes de que abra un lote hasta que
     * todos los lotes del remate estén liquidados.
     */
    'deploy_minutos_antes_de_remate' => (int) env('COLLIERS_DEPLOY_MINUTOS_ANTES', 30),

    /* Zona horaria en que se muestran las fechas. En base de datos todo se guarda en UTC. */
    'zona_visualizacion' => env('COLLIERS_ZONA_VISUALIZACION', 'America/Santiago'),

];
