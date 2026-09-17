<?php

/*
 * Funciones compartidas por las pruebas de concurrencia (prueba.php) y la medición de rendimiento
 * (tools/rendimiento/medir.php): peticiones realmente simultáneas y sesiones autenticadas reales.
 * El script que las incluye define antes $e (entorno.php) y arranca la aplicación.
 */

use App\Models\User;
use Illuminate\Auth\SessionGuard;
use Illuminate\Cookie\CookieValuePrefix;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

$fallas = 0;
function comprobar(string $nombre, bool $ok, string $detalle = ''): void
{
    global $fallas;
    $fallas += $ok ? 0 : 1;
    echo ($ok ? '  OK    ' : '  FALLA ') . $nombre . ($detalle !== '' ? "  ({$detalle})" : '') . "\n";
}

/**
 * Dispara todas las peticiones a la vez y espera todas. Una petición con 'en_ms' (hora Unix en ms) sale en ese instante,
 * mientras las anteriores siguen en curso. @return array<int, array{estado:int, cuerpo:mixed, ms:float}>
 */
function enParalelo(array $peticiones): array
{
    global $e;
    $multi = curl_multi_init();
    $manejadores = [];
    $pendientes = [];
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
        curl_setopt_array($h, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HTTPHEADER => $cabeceras, CURLOPT_TIMEOUT => 120, CURLOPT_USERAGENT => 'colliers-concurrencia']);
        $manejadores[$i] = $h;
        if (isset($p['en_ms'])) {
            $pendientes[$i] = $p['en_ms'];
        } else {
            curl_multi_add_handle($multi, $h);
        }
    }
    do {
        foreach ($pendientes as $i => $enMs) {
            if (microtime(true) * 1000 >= $enMs) {
                curl_multi_add_handle($multi, $manejadores[$i]);
                unset($pendientes[$i]);
            }
        }
        $estado = curl_multi_exec($multi, $activos);
        if ($activos) {
            curl_multi_select($multi, 0.005);
        } elseif ($pendientes) {
            usleep(2000);
        }
    } while (($activos || $pendientes) && $estado === CURLM_OK);

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

/** Mediana, p95 y máximo de una lista de milisegundos. */
function percentiles(array $ms): string
{
    sort($ms);
    $n = count($ms);

    return $n === 0 ? 'sin datos' : sprintf('mediana %4.0f ms · p95 %4.0f ms · máx %4.0f ms', $ms[intdiv($n, 2)], $ms[min($n - 1, (int) floor($n * 0.95))], $ms[$n - 1]);
}
