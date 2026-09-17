<?php

/*
 * Servidor para las pruebas de concurrencia del Bloque J: Apache de Laragon con mod_php (PHP multihilo),
 * que atiende peticiones realmente en paralelo. NUNCA `artisan serve` (atiende una a la vez).
 *
 * Uso (queda corriendo; Ctrl+C para detener):
 *   php tools/concurrencia/servidor.php
 * Requiere MySQL/MariaDB de Laragon encendido. Variables opcionales: LARAGON, CONCURRENCIA_URL, CONCURRENCIA_DB_*.
 */

$e = require __DIR__ . '/entorno.php';

$apache = glob($e['laragon'] . '/bin/apache/httpd-*', GLOB_ONLYDIR)[0] ?? null;
$php = str_replace('\\', '/', dirname(PHP_BINARY));
if ($apache === null || ! file_exists($php . '/php8apache2_4.dll')) {
    fwrite(STDERR, "No encontré Apache de Laragon o php8apache2_4.dll (PHP multihilo) en {$php}.\n");
    exit(1);
}

$puerto = parse_url($e['url'], PHP_URL_PORT) ?: 8090;
@mkdir($e['trabajo'], 0777, true);

// php.ini propio con OPcache activo (como un hosting bien configurado). No toca el php.ini de Laragon.
// CONCURRENCIA_OPCACHE=0 lo apaga, como en el sandbox (17/09): sirve para medir el peor caso.
$opcache = getenv('CONCURRENCIA_OPCACHE') !== '0';
$ini = file_get_contents($php . '/php.ini') . "\n\n; ── Agregado por tools/concurrencia/servidor.php ──\n"
    . ($opcache
        ? "zend_extension=opcache\nopcache.enable=1\nopcache.memory_consumption=128\nopcache.max_accelerated_files=20000\nopcache.validate_timestamps=1\n"
        : "opcache.enable=0\n");
file_put_contents($e['trabajo'] . '/php.ini', $ini);

$variables = '';
foreach ($e['variables'] as $clave => $valor) {
    $variables .= sprintf("SetEnv %s \"%s\"\n", $clave, addslashes($valor));
}

$conf = <<<CONF
ServerRoot "{$apache}"
Listen 127.0.0.1:{$puerto}
ServerName localhost
ThreadsPerChild 64

LoadModule authz_core_module modules/mod_authz_core.so
LoadModule access_compat_module modules/mod_access_compat.so
LoadModule dir_module modules/mod_dir.so
LoadModule mime_module modules/mod_mime.so
LoadModule rewrite_module modules/mod_rewrite.so
LoadModule headers_module modules/mod_headers.so
LoadModule env_module modules/mod_env.so
LoadModule log_config_module modules/mod_log_config.so
LoadModule php_module "{$php}/php8apache2_4.dll"
PHPIniDir "{$e['trabajo']}"

TypesConfig conf/mime.types
PidFile "{$e['trabajo']}/httpd.pid"
ErrorLog "{$e['trabajo']}/apache-error.log"
LogFormat "%h %t \"%r\" %>s %Dus" tiempo
CustomLog "{$e['trabajo']}/apache-access.log" tiempo

DocumentRoot "{$e['raiz']}/public"
DirectoryIndex index.php
<Directory "{$e['raiz']}/public">
    AllowOverride All
    Require all granted
</Directory>
<FilesMatch "\.php$">
    SetHandler application/x-httpd-php
</FilesMatch>

{$variables}
CONF;

$archivo = $e['trabajo'] . '/httpd.conf';
file_put_contents($archivo, $conf);
@unlink($e['trabajo'] . '/httpd.pid');

// CONCURRENCIA_NUCLEOS=N limita Apache a N núcleos (afinidad de CPU, la heredan los procesos hijos): simula el
// límite de CPU de un hosting compartido (CloudLinux suele dar 1 o 2 núcleos por cuenta).
$nucleos = (int) getenv('CONCURRENCIA_NUCLEOS');
$afinidad = $nucleos > 0 ? sprintf('%X', (1 << $nucleos) - 1) : null;

echo "Apache de prueba en {$e['url']} (PHP " . PHP_VERSION . ', mod_php multihilo, OPcache ' . ($opcache ? 'activo' : 'APAGADO')
    . ($afinidad ? ", {$nucleos} núcleo(s)" : ', todos los núcleos') . "). Configuración: {$archivo}\n";
$httpd = '"' . $apache . '/bin/httpd.exe" -f "' . $archivo . '"';
passthru($afinidad ? 'cmd /c start "apache-colliers" /b /wait /affinity ' . $afinidad . ' ' . $httpd : $httpd, $salida);
exit($salida);
