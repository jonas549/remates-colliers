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
    /*
     * Medición por tramos de la puja (22/10): la respuesta lleva `Server-Timing` (App\Subastas\CronometroPuja).
     * Apagada por defecto; se enciende para medir (tools/rendimiento/tramos.php) y se vuelve a apagar.
     */
    'medir_puja' => (bool) env('COLLIERS_MEDIR_PUJA', false),

    'tiempo_real' => [
        'driver' => env('COLLIERS_TIEMPO_REAL', 'json'),
        'carpeta' => env('COLLIERS_TIEMPO_REAL_CARPETA', public_path('tiempo-real')),
    ],

    /*
     * Presencia en la sala de pujas (App\Subastas\PresenciaSala). El navegador late cada `latido_segundos` a
     * public/presencia.php (sin Laravel); se cuenta como conectado quien latió en los últimos `ventana_segundos`.
     * `ajustes`: archivo que Laravel deja para el script (clave del token y carpetas); fuera de `public`.
     */
    'presencia' => [
        'carpeta' => env('COLLIERS_PRESENCIA_CARPETA', storage_path('app/presencia')),
        'ajustes' => env('COLLIERS_PRESENCIA_AJUSTES', storage_path('app/presencia-ajustes.json')),
        'latido_segundos' => 15,
        'ventana_segundos' => 45,
    ],

    /*
     * Límites técnicos de protección (no son reglas de negocio del acta): peticiones por minuto.
     * Un postor real no se acerca a 30 pujas por minuto; los espectadores consultan el JSON estático, no PHP.
     * Registro: envíos por hora y por IP (cada envío puede subir ~21 MB de adjuntos; los fallidos también cuentan).
     */
    'limites' => [
        'pujas_por_minuto' => (int) env('COLLIERS_PUJAS_POR_MINUTO', 30),
        'estado_por_minuto' => (int) env('COLLIERS_ESTADO_POR_MINUTO', 60),
        'registros_por_hora' => (int) env('COLLIERS_REGISTROS_POR_HORA', 20),
    ],

    /*
     * Seguridad de transporte y cabeceras (QA 21/10, 2.1 y 2.2). La redirección de http a https del servidor la
     * configura Jonas; esta es la de la aplicación. Por defecto activa fuera de local y pruebas (Laragon va por http).
     * CSP: 'activa' | 'reporte' (Content-Security-Policy-Report-Only: no bloquea, solo avisa en la consola) | 'apagada',
     * para poder aflojarla desde el .env sin un deploy si algo legítimo quedara bloqueado.
     */
    'seguridad' => [
        'forzar_https' => (bool) env('COLLIERS_FORZAR_HTTPS', ! in_array(env('APP_ENV', 'production'), ['local', 'testing'], true)),
        'hsts_segundos' => (int) env('COLLIERS_HSTS_SEGUNDOS', 31536000),
        'csp' => env('COLLIERS_CSP', 'activa'),
        // Segundo factor (22/10): con el correo en modo «log» el código queda en un archivo y no llega a nadie. Solo en
        // local y pruebas eso cuenta como enviado (los recorridos leen el código del log); en el servidor, no.
        // COLLIERS_CODIGO_POR_LOG=true solo para tools/comparar/ingreso-servidor.mjs (APP_ENV=staging en local).
        'codigo_por_log' => (bool) env('COLLIERS_CODIGO_POR_LOG', in_array(env('APP_ENV', 'production'), ['local', 'testing'], true)),
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

    /*
     * Sitio corporativo de Colliers en producción. Los ítems de la cabecera y del pie que no son pantallas de esta
     * aplicación enlazan ahí (App\Support\Sitio::produccion). Las redes son las que publica ese sitio.
     */
    'sitio_produccion' => [
        'url' => env('COLLIERS_SITIO_PRODUCCION', 'https://activosespeciales.colliers.cl'),
        'paginas' => [
            'servicios' => 'servicios/',
            'nosotros' => 'nosotros/',
            'expertos' => 'expertos/',
            'privacidad' => 'politica-de-privacidad',
            'terminos' => 'terminos-y-condiciones/',
        ],
        'redes' => [
            'linkedin' => 'https://cl.linkedin.com/company/colliers-chile',
            'facebook' => 'https://www.facebook.com/p/Colliers-Chile-100088146259403/',
            'instagram' => 'https://www.instagram.com/collierschile/',
            'youtube' => 'https://www.youtube.com/@collierscl',
        ],
    ],

    /* Zona horaria en que se muestran las fechas. En base de datos todo se guarda en UTC. */
    'zona_visualizacion' => env('COLLIERS_ZONA_VISUALIZACION', 'America/Santiago'),

];
