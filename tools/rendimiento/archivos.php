<?php

/*
 * Cuánto PHP compila una petición SIN OPcache: arranca la aplicación en un proceso nuevo (el PHP de consola no tiene
 * OPcache), atiende UNA petición y cuenta los archivos PHP cargados y sus bytes. Lo llama tools/rendimiento/medir.php.
 *
 *   php tools/rendimiento/archivos.php GET /hora
 *   php tools/rendimiento/archivos.php POST /remates/x/lotes/1/pujas '{"monto":1}' "<cookie>" "<token csrf>"
 */

$e = require __DIR__ . '/../concurrencia/entorno.php';
chdir($e['raiz']);
foreach ($e['variables'] as $clave => $valor) {
    putenv("{$clave}={$valor}");
    $_ENV[$clave] = $_SERVER[$clave] = $valor;
}

[, $metodo, $ruta] = $argv + [null, 'GET', '/hora'];
$cuerpo = $argv[3] ?? null;
$cookie = $argv[4] ?? null;
$token = $argv[5] ?? null;

$inicio = hrtime(true);
if (($argv[1] ?? '') === 'estatico') {
    // Referencia: el mismo trabajo que hace LiteSpeed con /hora.php, sin framework.
    ob_start();
    require $e['raiz'] . '/public/hora.php';
    ob_end_clean();
    $ms = (hrtime(true) - $inicio) / 1e6;
} else {
    require $e['raiz'] . '/vendor/autoload.php';
    $app = require $e['raiz'] . '/bootstrap/app.php';
    $kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

    $servidor = ['HTTP_ACCEPT' => 'application/json', 'REMOTE_ADDR' => '127.0.0.1', 'HTTP_USER_AGENT' => 'colliers-rendimiento'];
    if ($cuerpo !== null) {
        $servidor['CONTENT_TYPE'] = 'application/json';
    }
    if ($token !== null) {
        $servidor['HTTP_X_CSRF_TOKEN'] = $token;
    }
    $peticion = Illuminate\Http\Request::create($ruta, $metodo, [], [], [], $servidor, $cuerpo);
    if ($cookie !== null) {
        [$nombre, $valor] = explode('=', $cookie, 2);
        $peticion->cookies->set($nombre, rawurldecode($valor));
    }
    ob_start();
    $respuesta = $kernel->handle($peticion);
    $kernel->terminate($peticion, $respuesta);
    $contenido = ob_get_clean() . $respuesta->getContent();
    if (getenv("RENDIMIENTO_DEPURAR")) { fwrite(STDERR, substr($contenido, 0, 400) . "
"); }
    $ms = (hrtime(true) - $inicio) / 1e6;
}

$archivos = get_included_files();
$bytes = array_sum(array_map('filesize', $archivos));
$porPaquete = [];
foreach ($archivos as $archivo) {
    $relativo = str_replace('\\', '/', substr($archivo, strlen($e['raiz']) + 1));
    $partes = explode('/', $relativo);
    $grupo = $partes[0] === 'vendor' && count($partes) > 3 ? $partes[1] . '/' . $partes[2] : $partes[0];
    $porPaquete[$grupo] = ($porPaquete[$grupo] ?? 0) + filesize($archivo);
}
arsort($porPaquete);

echo json_encode([
    'estado' => isset($respuesta) ? $respuesta->getStatusCode() : 200,
    'ms' => round($ms, 1),
    'archivos' => count($archivos),
    'kb' => round($bytes / 1024),
    'paquetes' => array_map(fn ($b) => round($b / 1024), array_slice($porPaquete, 0, 8, true)),
]), "\n";
