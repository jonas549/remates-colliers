<?php

/*
 * Pruebas de concurrencia del Bloque J (CLAUDE.md §5): peticiones HTTP realmente simultáneas (curl_multi)
 * contra Apache + mod_php + MySQL/MariaDB. Verifica invariantes contra la base, no contra las respuestas.
 *
 * Uso:
 *   1. MySQL/MariaDB de Laragon encendido.
 *   2. php tools/concurrencia/servidor.php            (en otra terminal; queda corriendo)
 *   3. php tools/concurrencia/prueba.php [postores]   (20 por defecto)
 *
 * Escenarios:
 *   A. Mismo monto en el mismo instante, 5 rondas: en cada ronda gana exactamente una puja.
 *   B. Ráfagas con montos aleatorios y conocimiento desfasado, con vaciado de caché a mitad: la secuencia de
 *      pujas aceptadas es válida (incrementos, sin autosuperarse) y el lote coincide con la última puja.
 *   C. Pujas en ráfaga durante el cierre + liquidación disputada por 20 peticiones: ninguna puja recibida en o
 *      después de T, nada se adjudica antes de T + margen, exactamente una adjudicación y es la última puja.
 */

use App\Models\Adjudicacion;
use App\Models\Configuracion;
use App\Models\Garantia;
use App\Models\Lote;
use App\Models\Postor;
use App\Models\Puja;
use App\Models\PujaIntento;
use App\Models\Remate;
use App\Models\User;
use App\Subastas\EstadoRemate;
use App\Support\Rut;
use Carbon\CarbonImmutable;
use Illuminate\Auth\SessionGuard;
use Illuminate\Cookie\CookieValuePrefix;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

$e = require __DIR__ . '/entorno.php';
$cantidad = max(2, (int) ($argv[1] ?? 20));

foreach ($e['variables'] as $clave => $valor) {
    putenv("{$clave}={$valor}");
    $_ENV[$clave] = $_SERVER[$clave] = $valor;
}

$v = $e['variables'];
$pdo = new PDO("mysql:host={$v['DB_HOST']};port={$v['DB_PORT']}", $v['DB_USERNAME'], $v['DB_PASSWORD']);
$pdo->exec("CREATE DATABASE IF NOT EXISTS `{$v['DB_DATABASE']}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
$motor = $pdo->query('SELECT VERSION()')->fetchColumn();

// La caché de configuración anterior podría tener otro entorno: se regenera en cada corrida.
@unlink($e['raiz'] . '/' . $v['APP_CONFIG_CACHE']);
require $e['raiz'] . '/vendor/autoload.php';
$app = require $e['raiz'] . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
Artisan::call('config:cache');

$fallas = 0;
function comprobar(string $nombre, bool $ok, string $detalle = ''): void
{
    global $fallas;
    $fallas += $ok ? 0 : 1;
    echo ($ok ? '  OK    ' : '  FALLA ') . $nombre . ($detalle !== '' ? "  ({$detalle})" : '') . "\n";
}

/** Dispara todas las peticiones a la vez y espera todas. @return array<int, array{estado:int, cuerpo:mixed, ms:float}> */
function enParalelo(array $peticiones): array
{
    global $e;
    $multi = curl_multi_init();
    $manejadores = [];
    foreach ($peticiones as $i => $p) {
        $h = curl_init($e['url'] . $p['ruta']);
        $cabeceras = ['Accept: application/json'];
        if (isset($p['sesion'])) {
            $cabeceras[] = 'Cookie: ' . $p['sesion']['cookie'];
            $cabeceras[] = 'X-CSRF-TOKEN: ' . $p['sesion']['token'];
        }
        if (isset($p['json'])) {
            $cabeceras[] = 'Content-Type: application/json';
            curl_setopt($h, CURLOPT_POSTFIELDS, json_encode($p['json']));
        }
        curl_setopt_array($h, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HTTPHEADER => $cabeceras, CURLOPT_TIMEOUT => 60, CURLOPT_USERAGENT => 'colliers-concurrencia']);
        curl_multi_add_handle($multi, $h);
        $manejadores[$i] = $h;
    }
    do {
        $estado = curl_multi_exec($multi, $activos);
        if ($activos) {
            curl_multi_select($multi, 0.05);
        }
    } while ($activos && $estado === CURLM_OK);

    $resultados = [];
    foreach ($manejadores as $i => $h) {
        $resultados[$i] = [
            'estado' => curl_getinfo($h, CURLINFO_RESPONSE_CODE),
            'cuerpo' => json_decode((string) curl_multi_getcontent($h), true),
            'ms' => curl_getinfo($h, CURLINFO_TOTAL_TIME) * 1000,
        ];
        curl_multi_remove_handle($multi, $h);
        curl_close($h);
    }
    curl_multi_close($multi);

    return $resultados;
}

/** Sesión autenticada real (tabla sessions + cookie cifrada), igual a la que deja un login. */
function sesionPara(User $user): array
{
    $id = Str::random(40);
    $token = Str::random(40);
    $datos = ['_token' => $token, 'login_web_' . sha1(SessionGuard::class) => $user->id, '_flash' => ['old' => [], 'new' => []]];
    DB::table('sessions')->insert([
        'id' => $id, 'user_id' => $user->id, 'ip_address' => '127.0.0.1', 'user_agent' => 'colliers-concurrencia',
        // Mismo formato que escribe Laravel según config/session.php (este proyecto usa `json`).
        'payload' => base64_encode(config('session.serialization') === 'json' ? json_encode($datos) : serialize($datos)),
        'last_activity' => time(),
    ]);
    $nombre = config('session.cookie');
    $valor = encrypt(CookieValuePrefix::create($nombre, app('encrypter')->getKey()) . $id, false);

    return ['cookie' => $nombre . '=' . rawurlencode($valor), 'token' => $token];
}

function crearRemate(string $slug, CarbonImmutable $abre, CarbonImmutable $cierra, array $postores): Lote
{
    $remate = Remate::create(['folio' => 'C-' . strtoupper($slug), 'slug' => $slug, 'titulo' => "Concurrencia {$slug}", 'estado' => Remate::ESTADO_PUBLICADO]);
    $lote = Lote::create(['remate_id' => $remate->id, 'titulo' => "Lote {$slug}", 'precio_base' => 100000000, 'abre_en' => $abre, 'cierra_en' => $cierra]);
    foreach ($postores as $user) {
        Garantia::paraRemate($remate, $user)->forceFill(['estado' => Garantia::ESTADO_APROBADA])->save();
    }

    return $lote;
}

function contar(array $resultados): array
{
    $c = [];
    foreach ($resultados as $r) {
        $clave = $r['estado'] . (isset($r['cuerpo']['motivo']) ? ':' . $r['cuerpo']['motivo'] : '');
        $c[$clave] = ($c[$clave] ?? 0) + 1;
    }
    ksort($c);

    return $c;
}

function resumen(array $c): string
{
    return implode(', ', array_map(fn ($k, $n) => "{$k}×{$n}", array_keys($c), $c));
}

/** Invariantes de la secuencia de pujas aceptadas de un lote. */
function verificarSecuencia(Lote $lote, string $prefijo): void
{
    $lote->refresh();
    $pujas = Puja::where('lote_id', $lote->id)->orderBy('id')->get();
    $incremento = $lote->remate->incrementoMinimo();
    $errores = [];
    $anterior = null;
    foreach ($pujas as $p) {
        if ($anterior === null && $p->monto < $lote->precio_base) {
            $errores[] = "primera puja {$p->id} bajo el precio base";
        }
        if ($anterior !== null && $p->monto < $anterior->monto + $incremento) {
            $errores[] = "puja {$p->id} ({$p->monto}) no supera a {$anterior->id} ({$anterior->monto}) + incremento";
        }
        if ($anterior !== null && $p->user_id === $anterior->user_id) {
            $errores[] = "puja {$p->id}: el postor se superó a sí mismo";
        }
        $anterior = $p;
    }
    comprobar("{$prefijo}: secuencia de {$pujas->count()} pujas aceptadas válida", $errores === [], implode('; ', array_slice($errores, 0, 3)));
    comprobar("{$prefijo}: precio actual y ganador = última puja", $anterior !== null && $lote->precio_actual === $anterior->monto && $lote->ganador_id === $anterior->user_id,
        "lote {$lote->precio_actual}/{$lote->ganador_id}, última " . ($anterior?->monto ?? '-') . '/' . ($anterior?->user_id ?? '-'));
    comprobar("{$prefijo}: total de pujas del lote = filas en pujas", $lote->total_pujas === $pujas->count(), "{$lote->total_pujas} vs {$pujas->count()}");
    comprobar("{$prefijo}: precio actual = monto máximo", $lote->precio_actual === (int) Puja::where('lote_id', $lote->id)->max('monto'));
}

function esperarHasta(CarbonImmutable $momento): void
{
    $faltan = $momento->getTimestampMs() - CarbonImmutable::now('UTC')->getTimestampMs();
    if ($faltan > 0) {
        usleep($faltan * 1000);
    }
}

// ── Preparación ─────────────────────────────────────────────────────────────────────────────────────────
echo "Concurrencia · {$cantidad} postores · base {$v['DB_DATABASE']} en {$motor} · servidor {$e['url']}\n";
Artisan::call('migrate:fresh', ['--force' => true]);

$prueba = @file_get_contents($e['url'] . '/hora');
if ($prueba === false || ! str_contains($prueba, 'servidor_ms')) {
    fwrite(STDERR, "El servidor de prueba no responde en {$e['url']}. Ejecuta antes: php tools/concurrencia/servidor.php\n");
    exit(2);
}
Configuracion::sembrarDefectos();
@array_map('unlink', glob($e['variables']['COLLIERS_TIEMPO_REAL_CARPETA'] . '/*') ?: []);

$postores = [];
$sesiones = [];
for ($i = 1; $i <= $cantidad; $i++) {
    $user = User::create(['name' => "Postor {$i}", 'email' => "postor{$i}@concurrencia.test", 'password' => 'clave-de-prueba-123', 'rol' => User::ROL_POSTOR, 'estado' => User::ESTADO_ACTIVO]);
    $cuerpo = (string) (20000000 + $i);
    Postor::create(['user_id' => $user->id, 'nombres' => 'Postor', 'apellidos' => (string) $i, 'rut' => $cuerpo . Rut::digitoVerificador($cuerpo)])
        ->forceFill(['estado' => Postor::ESTADO_APROBADO])->save();
    $postores[] = $user;
    $sesiones[$user->id] = sesionPara($user);
}
$ahora = CarbonImmutable::now('UTC');
$incremento = (int) Configuracion::valor('incremento_minimo');
$margen = (int) Configuracion::valor('margen_liquidacion_segundos');
$errores5xx = 0;

// Precalentamiento: carga de clases y conexiones del servidor antes de medir.
enParalelo(array_fill(0, 8, ['ruta' => '/hora']));

// ── A. Mismo monto en el mismo instante ─────────────────────────────────────────────────────────────────
echo "\nA. Mismo monto en el mismo instante ({$cantidad} pujas simultáneas por ronda)\n";
$loteA = crearRemate('mismo-monto', $ahora->subMinute(), $ahora->addMinutes(30), $postores);
$rutaA = "/remates/mismo-monto/lotes/{$loteA->id}/pujas";
for ($ronda = 1; $ronda <= 5; $ronda++) {
    $loteA->refresh();
    $monto = EstadoRemate::pujaMinima($loteA, $incremento);
    $antes = Puja::where('lote_id', $loteA->id)->count();
    $r = enParalelo(array_map(fn (User $u) => ['ruta' => $rutaA, 'sesion' => $sesiones[$u->id], 'json' => ['monto' => $monto]], $postores));
    $c = contar($r);
    $errores5xx += count(array_filter($r, fn ($x) => $x['estado'] >= 500 || $x['estado'] === 0));
    $aceptadas = count(array_filter($r, fn ($x) => $x['estado'] === 201));
    comprobar("ronda {$ronda}: exactamente una aceptada de {$cantidad} con monto {$monto}", $aceptadas === 1 && Puja::where('lote_id', $loteA->id)->count() === $antes + 1, resumen($c));
}
verificarSecuencia($loteA, 'A');
comprobar('A: cada rechazo quedó registrado en puja_intentos', PujaIntento::where('lote_id', $loteA->id)->count() === 5 * ($cantidad - 1),
    PujaIntento::where('lote_id', $loteA->id)->count() . ' intentos');

// ── B. Ráfagas aleatorias con vaciado de caché a mitad ──────────────────────────────────────────────────
$rondasB = 15;
echo "\nB. Ráfagas aleatorias ({$rondasB} rondas × {$cantidad} postores, montos con conocimiento desfasado)\n";
$loteB = crearRemate('rafagas', $ahora->subMinute(), $ahora->addMinutes(30), $postores);
$rutaB = "/remates/rafagas/lotes/{$loteB->id}/pujas";
$codigosB = [];
$tiempos = [];
$saltos = [0, $incremento, 5 * $incremento, 10 * $incremento];
mt_srand(20260916);
for ($ronda = 1; $ronda <= $rondasB; $ronda++) {
    $conocido = EstadoRemate::pujaMinima($loteB->refresh(), $incremento);
    $r = enParalelo(array_map(fn (User $u) => ['ruta' => $rutaB, 'sesion' => $sesiones[$u->id], 'json' => ['monto' => $conocido + $saltos[mt_rand(0, 3)]]], $postores));
    foreach ($r as $x) {
        $codigosB[] = $x;
        $tiempos[] = $x['ms'];
    }
    if ($ronda === 8) {
        // Caché de la aplicación (store `database`, compartido con Apache). `optimize:clear` se prueba en
        // MotorPujasTest: aquí borraría la caché de configuración que el servidor multihilo necesita.
        Artisan::call('cache:clear');
        echo "  (caché de la aplicación vaciada después de la ronda 8)\n";
    }
}
$cB = contar($codigosB);
$errores5xx += count(array_filter($codigosB, fn ($x) => $x['estado'] >= 500 || $x['estado'] === 0));
echo '  Respuestas: ' . resumen($cB) . "\n";
verificarSecuencia($loteB, 'B');
$aceptadasB = count(array_filter($codigosB, fn ($x) => $x['estado'] === 201));
comprobar('B: respuestas 201 = pujas en la base', $aceptadasB === Puja::where('lote_id', $loteB->id)->count(), "{$aceptadasB} respuestas");
comprobar('B: respuestas 422 = intentos registrados', count(array_filter($codigosB, fn ($x) => $x['estado'] === 422)) === PujaIntento::where('lote_id', $loteB->id)->count());
$json = json_decode((string) @file_get_contents($e['variables']['COLLIERS_TIEMPO_REAL_CARPETA'] . '/rafagas.json'), true);
comprobar('B: el JSON publicado quedó con el último estado de la base', ($json['lotes'][0]['precio_actual'] ?? null) === $loteB->refresh()->precio_actual
    && ($json['lotes'][0]['total_pujas'] ?? null) === $loteB->total_pujas, 'JSON ' . ($json['lotes'][0]['precio_actual'] ?? 'sin archivo') . " / base {$loteB->precio_actual}");
sort($tiempos);
echo sprintf("  Latencia por puja: mediana %.0f ms · p95 %.0f ms · máx %.0f ms\n", $tiempos[intdiv(count($tiempos), 2)], $tiempos[(int) floor(count($tiempos) * 0.95)], end($tiempos));

// ── C. Cierre con pujas en ráfaga y liquidación disputada ───────────────────────────────────────────────
echo "\nC. Cierre: ráfagas hasta después de T y liquidación disputada por {$cantidad} peticiones\n";
$cierre = CarbonImmutable::now('UTC')->addSeconds(4)->startOfSecond();
$loteC = crearRemate('cierre', $ahora->subMinute(), $cierre, $postores);
$rutaC = "/remates/cierre/lotes/{$loteC->id}/pujas";
$codigosC = [];
$olas = 0;
// Las olas empiezan hasta T + 300 ms: cruzan el cierre, pero terminan antes de medir el margen.
while (CarbonImmutable::now('UTC')->lessThan($cierre->addMilliseconds(300))) {
    $conocido = EstadoRemate::pujaMinima($loteC->refresh(), $incremento);
    foreach (enParalelo(array_map(fn (User $u) => ['ruta' => $rutaC, 'sesion' => $sesiones[$u->id], 'json' => ['monto' => $conocido + $saltos[mt_rand(0, 3)]]], $postores)) as $x) {
        $codigosC[] = $x;
    }
    $olas++;
}
// Una ola que sale siempre DESPUÉS de T: con muchos postores las anteriores pueden terminar todas antes.
esperarHasta($cierre->addMilliseconds(100));
$conocido = EstadoRemate::pujaMinima($loteC->refresh(), $incremento);
foreach (enParalelo(array_map(fn (User $u) => ['ruta' => $rutaC, 'sesion' => $sesiones[$u->id], 'json' => ['monto' => $conocido + 10 * $incremento]], $postores)) as $x) {
    $codigosC[] = $x;
}
$olas++;
echo "  {$olas} olas de pujas, respuestas: " . resumen(contar($codigosC)) . "\n";
$errores5xx += count(array_filter($codigosC, fn ($x) => $x['estado'] >= 500 || $x['estado'] === 0));

comprobar('C: ninguna puja aceptada recibida en o después de T', Puja::where('lote_id', $loteC->id)->where('recibida_en', '>=', $cierre->format('Y-m-d H:i:s'))->count() === 0);
comprobar('C: hubo pujas rechazadas por llegar después de T', count(array_filter($codigosC, fn ($x) => ($x['cuerpo']['motivo'] ?? '') === 'lote_cerrado')) > 0);

// Antes de T + margen: el estado se consulta pero no se adjudica. Solo es concluyente si las respuestas
// llegaron antes de T + margen; si no, la prueba lo dice en vez de dar un OK o una FALLA falsos.
esperarHasta($cierre->addMilliseconds(400));
$limite = $cierre->addSeconds($margen);
if (CarbonImmutable::now('UTC')->lessThan($limite->subMilliseconds(800))) {
    $temprano = enParalelo(array_fill(0, $cantidad, ['ruta' => '/remates/cierre/estado']));
    $aTiempo = CarbonImmutable::now('UTC')->lessThan($limite);
    comprobar('C: consultas de estado antes de T + margen no adjudican', $aTiempo && Adjudicacion::where('lote_id', $loteC->id)->count() === 0
        && count(array_filter($temprano, fn ($x) => ($x['cuerpo']['lotes'][0]['estado'] ?? '') === 'adjudicado')) === 0,
        resumen(contar($temprano)) . ($aTiempo ? '' : '; NO CONCLUYENTE: respuestas después de T + margen'));
} else {
    comprobar('C: consultas de estado antes de T + margen no adjudican', false, 'NO CONCLUYENTE: las olas terminaron después de T + margen − 800 ms');
}

// Después de T + margen: todos compiten por liquidar.
esperarHasta($cierre->addSeconds($margen)->addMilliseconds(150));
$liquidacion = enParalelo(array_fill(0, $cantidad, ['ruta' => '/remates/cierre/estado']));
$errores5xx += count(array_filter($liquidacion, fn ($x) => $x['estado'] >= 500 || $x['estado'] === 0));
$loteC->refresh();
$ultima = Puja::where('lote_id', $loteC->id)->orderByDesc('id')->first();
comprobar("C: {$cantidad} liquidaciones simultáneas → exactamente una adjudicación", Adjudicacion::where('lote_id', $loteC->id)->count() === 1, resumen(contar($liquidacion)));
$adj = Adjudicacion::where('lote_id', $loteC->id)->first();
// Independiente de los tiempos de la prueba: la fila se creó en T + margen o después (precisión de segundos).
comprobar('C: la adjudicación se materializó en T + margen o después', $adj !== null
    && $adj->created_at->greaterThanOrEqualTo($cierre->addSeconds($margen)), $adj ? 'creada ' . $adj->created_at->format('H:i:s') . ', T ' . $cierre->format('H:i:s') : 'sin adjudicación');
comprobar('C: el adjudicado es la última puja válida', $adj !== null && $ultima !== null && $adj->puja_id === $ultima->id && $adj->user_id === $ultima->user_id && $adj->monto === $ultima->monto && $loteC->estado === Lote::ESTADO_ADJUDICADO);
comprobar('C: todas las respuestas de estado muestran el lote adjudicado', count(array_filter($liquidacion, fn ($x) => ($x['cuerpo']['lotes'][0]['estado'] ?? '') === 'adjudicado')) === $cantidad);
verificarSecuencia($loteC, 'C');

// ── Resultado ───────────────────────────────────────────────────────────────────────────────────────────
echo "\n";
comprobar('Sin errores 5xx ni conexiones fallidas en ninguna petición', $errores5xx === 0, "{$errores5xx} errores");
echo $fallas ? "\n{$fallas} FALLA(S)\n" : "\nTodo OK\n";
exit($fallas ? 1 : 0);
