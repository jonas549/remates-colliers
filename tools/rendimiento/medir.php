<?php

/*
 * Rendimiento del camino de la puja con y sin OPcache (docs/RENDIMIENTO-SIN-OPCACHE.md).
 *
 * Uso (MySQL/MariaDB de Laragon encendido):
 *   1. Servidor, en otra terminal. Peor caso del sandbox: sin OPcache y con 1 núcleo.
 *        CONCURRENCIA_OPCACHE=0 CONCURRENCIA_NUCLEOS=1 php tools/concurrencia/servidor.php
 *   2. php tools/rendimiento/medir.php [postores=10] [rondas=5]
 *
 * Recrea la base colliers_concurrencia (la de las pruebas de concurrencia, nunca la de desarrollo).
 * Mide, desde el cliente (incluye la cola de espera del servidor):
 *   - Un archivo estático: referencia sin PHP (así se sirve el JSON que consultan los espectadores cada ~1 s).
 *   - /hora con Laravel y /hora.php sin framework (sincronización del reloj).
 *   - Una puja sola y N pujas simultáneas de postores distintos (el caso comprometido: 10 postores), y cuánto después
 *     del envío quedó sellada la hora de recepción.
 *   - N consultas simultáneas al endpoint de estado (lo que pasa en el instante del cierre).
 * Y, en un proceso de consola sin OPcache, cuántos archivos y KB de PHP compila cada petición.
 */

use App\Models\Configuracion;
use App\Models\Garantia;
use App\Models\Lote;
use App\Models\Postor;
use App\Models\Remate;
use App\Models\User;
use App\Subastas\Difusion\Emisor;
use App\Subastas\EstadoRemate;
use App\Support\Rut;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Artisan;

$e = require __DIR__ . '/../concurrencia/entorno.php';
$cantidad = max(1, (int) ($argv[1] ?? 10));
$rondas = max(1, (int) ($argv[2] ?? 5));

foreach ($e['variables'] as $clave => $valor) {
    putenv("{$clave}={$valor}");
    $_ENV[$clave] = $_SERVER[$clave] = $valor;
}
$v = $e['variables'];
$pdo = new PDO("mysql:host={$v['DB_HOST']};port={$v['DB_PORT']}", $v['DB_USERNAME'], $v['DB_PASSWORD']);
$pdo->exec("CREATE DATABASE IF NOT EXISTS `{$v['DB_DATABASE']}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

@unlink($e['raiz'] . '/' . $v['APP_CONFIG_CACHE']);
require $e['raiz'] . '/vendor/autoload.php';
$app = require $e['raiz'] . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
Artisan::call('config:cache');
require __DIR__ . '/../concurrencia/funciones.php';

$hora = @file_get_contents($e['url'] . '/hora');
if ($hora === false || ! str_contains($hora, 'servidor_ms')) {
    fwrite(STDERR, "El servidor de prueba no responde en {$e['url']}. Ejecuta antes: php tools/concurrencia/servidor.php\n");
    exit(2);
}

Artisan::call('migrate:fresh', ['--force' => true]);
Configuracion::sembrarDefectos();

$ahora = CarbonImmutable::now('UTC');
$remate = Remate::create(['folio' => 'M-1', 'slug' => 'medicion', 'titulo' => 'Medición', 'estado' => Remate::ESTADO_PUBLICADO]);
$lote = Lote::create(['remate_id' => $remate->id, 'titulo' => 'Lote', 'precio_base' => 100000000, 'abre_en' => $ahora->subMinute(), 'cierra_en' => $ahora->addHours(2)]);
$sesiones = [];
for ($i = 1; $i <= max(2, $cantidad); $i++) {
    $user = User::create(['name' => "Postor {$i}", 'email' => "p{$i}@medicion.test", 'password' => 'clave-de-prueba-123', 'rol' => User::ROL_POSTOR, 'estado' => User::ESTADO_ACTIVO]);
    $cuerpo = (string) (30000000 + $i);
    Postor::create(['user_id' => $user->id, 'nombres' => 'Postor', 'apellidos' => (string) $i, 'rut' => $cuerpo . Rut::digitoVerificador($cuerpo)])
        ->forceFill(['estado' => Postor::ESTADO_APROBADO])->save();
    Garantia::paraRemate($remate, $user)->forceFill(['estado' => Garantia::ESTADO_APROBADA])->save();
    $sesiones[] = sesionPara($user);
}
app(Emisor::class)->publicarEstado($remate);
$incremento = (int) Configuracion::valor('incremento_minimo');
$rutaPuja = "/remates/medicion/lotes/{$lote->id}/pujas";

// Precalentamiento: conexiones, sesiones y (si hay OPcache) la caché de código.
enParalelo(array_fill(0, 4, ['ruta' => '/hora']));
enParalelo([['ruta' => $rutaPuja, 'sesion' => $sesiones[0], 'json' => ['monto' => 1]]]);

$medir = function (string $nombre, callable $peticiones, int $veces) {
    $ms = [];
    $sello = [];
    $codigos = [];
    for ($i = 0; $i < $veces; $i++) {
        $envio = microtime(true) * 1000;
        foreach (enParalelo($peticiones($i)) as $r) {
            $ms[] = $r['ms'];
            $codigos[$r['estado']] = ($codigos[$r['estado']] ?? 0) + 1;
            // Pujas aceptadas: cuánto después del envío quedó sellada la hora de recepción (validez frente al cierre).
            if (isset($r['cuerpo']['puja']['recibida_en_ms'])) {
                $sello[] = max(0, $r['cuerpo']['puja']['recibida_en_ms'] - $envio);
            }
        }
    }
    ksort($codigos);
    printf("  %-44s %s  [%s]\n", $nombre, percentiles($ms), implode(' ', array_map(fn ($c, $n) => "{$c}×{$n}", array_keys($codigos), $codigos)));
    if ($sello !== []) {
        printf("  %-44s %s\n", '  └ hora de recepción sellada tras el envío', percentiles($sello));
    }
};
$pujaPara = function (int $i) use ($lote, $incremento, $sesiones, $rutaPuja) {
    $minima = EstadoRemate::pujaMinima($lote->refresh(), $incremento);

    return ['ruta' => $rutaPuja, 'sesion' => $sesiones[$i % count($sesiones)], 'json' => ['monto' => $minima + $incremento * (1 + $i % 7)]];
};

echo "Rendimiento · {$cantidad} simultáneas · {$rondas} rondas · servidor {$e['url']}\n\n";
$medir('Archivo estático (referencia sin PHP)', fn () => [['ruta' => '/robots.txt']], 20);
$medir('/hora, una a la vez', fn () => [['ruta' => '/hora']], 20);
if (file_exists($e['raiz'] . '/public/hora.php')) {
    $medir("/hora.php (sin framework), {$cantidad} simultáneas", fn () => array_fill(0, $cantidad, ['ruta' => '/hora.php']), $rondas);
}
$medir("/hora, {$cantidad} simultáneas", fn () => array_fill(0, $cantidad, ['ruta' => '/hora']), $rondas);
$medir('Puja, una a la vez', fn ($i) => [$pujaPara($i)], 10);
$medir("Pujas, {$cantidad} postores simultáneos", fn () => array_map(fn ($i) => $pujaPara($i), range(0, $cantidad - 1)), $rondas);
$medir("Estado (PHP), {$cantidad} simultáneas (cierre)", fn () => array_fill(0, $cantidad, ['ruta' => '/remates/medicion/estado']), $rondas);

echo "\nPHP compilado por petición (proceso nuevo de consola, sin OPcache):\n";
$casos = [
    '/hora' => ['GET', '/hora'],
    '/up' => ['GET', '/up'],
];
if (file_exists($e['raiz'] . '/public/hora.php')) {
    $casos['/hora.php (sin framework)'] = ['estatico'];
}
foreach ($casos as $nombre => $argumentos) {
    $muestras = [];
    for ($i = 0; $i < 5; $i++) {
        $salida = shell_exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/archivos.php') . ' ' . implode(' ', array_map('escapeshellarg', $argumentos)));
        $muestras[] = json_decode((string) $salida, true);
    }
    $validas = array_values(array_filter($muestras));
    if ($validas === []) {
        echo "  {$nombre}: sin resultado\n";
        continue;
    }
    $tiempos = array_column($validas, 'ms');
    sort($tiempos);
    $ultimo = end($validas);
    printf("  %-26s %4d archivos · %5d KB · mediana %4.0f ms · estado %d\n", $nombre, $ultimo['archivos'], $ultimo['kb'], $tiempos[intdiv(count($tiempos), 2)], $ultimo['estado']);
    if ($nombre === '/hora') {
        foreach ($ultimo['paquetes'] as $paquete => $kb) {
            printf("      %-24s %5d KB\n", $paquete, $kb);
        }
    }
}
