<?php

/*
 * Dónde se va el tiempo de una puja con N postores simultáneos (QA 22/10: 20 postores, ~3 s por puja en el sandbox).
 *
 * Uso (MySQL/MariaDB de Laragon encendido):
 *   1. Servidor de prueba, en otra terminal (Apache de Laragon, mod_php multihilo):
 *        CONCURRENCIA_OPCACHE=0 CONCURRENCIA_NUCLEOS=2 php tools/concurrencia/servidor.php
 *   2. php tools/rendimiento/tramos.php [rondas=6]
 *
 * Recrea la base colliers_concurrencia (nunca la de desarrollo) y enciende la medición por tramos de la puja
 * (COLLIERS_MEDIR_PUJA, cabecera Server-Timing de App\Subastas\CronometroPuja) solo en su configuración en caché.
 *
 * Escenarios, cada uno con N = 1, 10 y 20 postores distintos:
 *   - «misma puja»: los N envían el mismo monto en el mismo instante (la prueba de QA). Una se acepta, N−1 se rechazan.
 *   - «montos distintos»: cada uno ofrece un monto distinto; se aceptan varias y cada aceptada publica el JSON.
 *
 * Tramos (ms, mediana / p95 sobre todas las pujas de todas las rondas):
 *   cola         cliente − servidor: espera de un hilo/proceso libre y red local.
 *   arranque     desde que PHP recibe la petición hasta el controlador: compilar y arrancar Laravel, middleware,
 *                sesión, usuario. Sin OPcache es casi todo compilación.
 *   transaccion  DB::transaction completa; adentro:
 *     espera_bloqueo  esperar el lockForUpdate del lote (lo que se serializa entre postores).
 *     con_bloqueo     con el lote bloqueado: validar, insertar la puja, actualizar el lote, commit.
 *   intento_rechazado  insertar el rechazo en puja_intentos (fuera de la transacción).
 *   emitir       publicar el JSON (solo aceptadas), fuera de la transacción; adentro:
 *     emisor_espera_archivo  esperar el flock del emisor (serializa publicaciones del mismo remate).
 *     emisor_construir       leer la base y armar el estado.
 *     emisor_escribir        archivo temporal + rename.
 *   respuesta    armar el JSON de respuesta (resumen del lote y alias).
 *   total        desde la recepción hasta enviar la respuesta (servidor).
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
$rondas = max(1, (int) ($argv[1] ?? 6));
$grupos = [1, 10, 20];
$e['variables']['COLLIERS_MEDIR_PUJA'] = 'true';

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

if (! str_contains((string) @file_get_contents($e['url'] . '/hora'), 'servidor_ms')) {
    fwrite(STDERR, "El servidor de prueba no responde en {$e['url']}. Ejecuta antes: php tools/concurrencia/servidor.php\n");
    exit(2);
}

Artisan::call('migrate:fresh', ['--force' => true]);
Configuracion::sembrarDefectos();
$ahora = CarbonImmutable::now('UTC');
$remate = Remate::create(['folio' => 'T-1', 'slug' => 'tramos', 'titulo' => 'Tramos', 'estado' => Remate::ESTADO_PUBLICADO]);
$lote = Lote::create(['remate_id' => $remate->id, 'titulo' => 'Lote', 'precio_base' => 100000000, 'abre_en' => $ahora->subMinute(), 'cierra_en' => $ahora->addHours(2)]);
$sesiones = [];
for ($i = 1; $i <= max($grupos) + 1; $i++) { // +1: el ganador vigente no puja
    $user = User::create(['name' => "Postor {$i}", 'email' => "p{$i}@tramos.test", 'password' => 'clave-de-prueba-123', 'rol' => User::ROL_POSTOR, 'estado' => User::ESTADO_ACTIVO]);
    $cuerpo = (string) (32000000 + $i);
    Postor::create(['user_id' => $user->id, 'nombres' => 'Postor', 'apellidos' => (string) $i, 'rut' => $cuerpo . Rut::digitoVerificador($cuerpo)])
        ->forceFill(['estado' => Postor::ESTADO_APROBADO])->save();
    Garantia::paraRemate($remate, $user)->forceFill(['estado' => Garantia::ESTADO_APROBADA])->save();
    $sesiones[] = sesionPara($user);
}
app(Emisor::class)->publicarEstado($remate);
$incremento = (int) Configuracion::valor('incremento_minimo');
$ruta = "/remates/tramos/lotes/{$lote->id}/pujas";

// Precalentamiento (conexiones y, si hay OPcache, la caché de código).
enParalelo(array_fill(0, 4, ['ruta' => '/hora']));
enParalelo([['ruta' => $ruta, 'sesion' => $sesiones[0], 'json' => ['monto' => 1]]]);

$orden = ['archivos', 'kb_php', 'cola', 'arranque', 'transaccion', 'espera_bloqueo', 'con_bloqueo', 'intento_rechazado', 'emitir',
    'emisor_espera_archivo', 'emisor_construir', 'emisor_escribir', 'respuesta', 'total', 'cliente'];
$mp = function (array $ms): string {
    if ($ms === []) {
        return '      —      ';
    }
    sort($ms);
    $n = count($ms);

    return sprintf('%5.0f / %5.0f', $ms[intdiv($n, 2)], $ms[min($n - 1, (int) floor($n * 0.95))]);
};

$medir = function (string $escenario, int $n) use ($rondas, $sesiones, $ruta, $lote, $incremento, $orden, $mp) {
    $datos = ['aceptada' => [], 'rechazada' => []];
    $estados = [];
    $deadlocks = 0;
    for ($r = 0; $r < $rondas; $r++) {
        $minima = EstadoRemate::pujaMinima($lote->refresh(), $incremento);
        // El ganador vigente no participa (se rechazaría por «ya vas ganando», que no es lo que se mide).
        $participantes = array_values(array_filter($sesiones, fn ($s, $i) => $i + 1 !== $lote->ganador_id, ARRAY_FILTER_USE_BOTH));
        $peticiones = [];
        for ($i = 0; $i < $n; $i++) {
            $monto = $escenario === 'misma puja' ? $minima : $minima + $incremento * $i;
            $peticiones[] = ['ruta' => $ruta, 'sesion' => $participantes[$i], 'json' => ['monto' => $monto]];
        }
        foreach (enParalelo($peticiones) as $res) {
            $estados[$res['estado']] = ($estados[$res['estado']] ?? 0) + 1;
            $tipo = $res['estado'] === 201 ? 'aceptada' : 'rechazada';
            $t = [];
            foreach (array_filter(explode(',', $res['tramos'])) as $parte) {
                if (preg_match('/^\s*([a-z_]+);dur=([\d.]+)/', $parte, $m)) {
                    $t[$m[1]] = (float) $m[2];
                }
            }
            $t['cliente'] = $res['ms'];
            if (isset($t['total'])) {
                $t['cola'] = max(0, $res['ms'] - $t['total']);
            }
            if (($t['intentos_tx'] ?? 1) > 1) {
                $deadlocks++;
            }
            $datos[$tipo][] = $t;
        }
        usleep(300000);
    }
    ksort($estados);
    printf("\n%s · %d postor(es) · %d ronda(s) · respuestas: %s · reintentos por deadlock: %d\n", $escenario, $n, $rondas,
        implode(' ', array_map(fn ($c, $k) => "{$c}×{$k}", array_keys($estados), $estados)), $deadlocks);
    printf("  %-22s %-15s %-15s\n", 'tramo (ms, med / p95)', 'aceptadas', 'rechazadas');
    foreach ($orden as $tramo) {
        $a = array_column($datos['aceptada'], $tramo);
        $b = array_column($datos['rechazada'], $tramo);
        if ($a === [] && $b === []) {
            continue;
        }
        printf("  %-22s %-15s %-15s\n", $tramo, $mp($a), $mp($b));
    }
};

echo "Tramos de la puja · servidor {$e['url']} · {$rondas} rondas por escenario\n";
foreach ($grupos as $n) {
    $medir('misma puja', $n);
}
foreach ([10, 20] as $n) {
    $medir('montos distintos', $n);
}
@unlink($e['raiz'] . '/' . $v['APP_CONFIG_CACHE']);
