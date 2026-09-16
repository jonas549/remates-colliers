<?php

/*
 * Entorno compartido por el servidor de prueba (Apache) y el script que dispara las pujas.
 * Base MySQL/MariaDB propia: la prueba la borra y la recrea. Nunca apunta a la base de desarrollo.
 */

$raiz = str_replace('\\', '/', realpath(__DIR__ . '/../..'));
$trabajo = $raiz . '/storage/framework/concurrencia';

return [
    'raiz' => $raiz,
    'trabajo' => $trabajo,
    'url' => getenv('CONCURRENCIA_URL') ?: 'http://127.0.0.1:8090',
    'laragon' => str_replace('\\', '/', getenv('LARAGON') ?: 'C:/laragon'),
    'variables' => [
        // Configuración en caché, como en el servidor (`optimize`). Además es necesario: con PHP multihilo
        // (mod_php) leer el .env en cada petición no es seguro entre hilos (putenv es global al proceso) y
        // algunas peticiones pierden APP_KEY o APP_NAME. En producción LiteSpeed usa procesos, no hilos.
        // Relativa a la raíz: en Windows Laravel no reconoce «F:/…» como absoluta y le antepone la raíz.
        'APP_CONFIG_CACHE' => 'storage/framework/concurrencia/config.php',
        'APP_ENV' => 'local',
        'APP_DEBUG' => 'false',
        'LOG_LEVEL' => 'error',
        'DB_CONNECTION' => 'mysql',
        'DB_HOST' => getenv('CONCURRENCIA_DB_HOST') ?: '127.0.0.1',
        'DB_PORT' => getenv('CONCURRENCIA_DB_PORT') ?: '3306',
        'DB_DATABASE' => 'colliers_concurrencia',
        'DB_USERNAME' => getenv('CONCURRENCIA_DB_USER') ?: 'root',
        'DB_PASSWORD' => getenv('CONCURRENCIA_DB_PASSWORD') ?: '',
        'SESSION_DRIVER' => 'database',
        'CACHE_STORE' => 'database',
        'QUEUE_CONNECTION' => 'sync',
        'COLLIERS_ACCESO_CLAVE' => '',
        // La prueba mide concurrencia, no el límite por minuto (ese tiene su prueba en MotorPujasTest).
        'COLLIERS_PUJAS_POR_MINUTO' => '100000',
        'COLLIERS_ESTADO_POR_MINUTO' => '100000',
        'COLLIERS_TIEMPO_REAL_CARPETA' => $trabajo . '/tiempo-real',
    ],
];
