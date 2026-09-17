<?php

/*
 * Mide la capacidad real del servidor (sandbox o producción) SIN TOCAR DATOS: solo peticiones GET.
 * Se ejecuta desde un computador con PHP (no en el servidor):
 *
 *   php tools/sandbox/medir-servidor.php https://rematescolliers.sandboxdelta.com [simultaneas=10] [rondas=5]
 *
 * Compara tres cosas que el servidor sirve de forma muy distinta:
 *   - /robots.txt  archivo estático: latencia de red pura (así se sirve el JSON que consultan los espectadores).
 *   - /hora.php    PHP sin framework: costo de lanzar PHP (docs/RENDIMIENTO-SIN-OPCACHE.md).
 *   - /up          Laravel completo: lo que cuesta cualquier página o puja (sin OPcache, ~6 MB de PHP compilado).
 * No necesita la clave de acceso: las tres rutas están fuera de ella.
 *
 * Cómo leerlo: si N peticiones simultáneas a /up tardan ~N veces lo que tarda una sola, el servidor está atendiendo
 * con UN núcleo; si tardan lo mismo, hay núcleos de sobra. Las pujas cuestan un poco más que /up.
 */

$base = rtrim($argv[1] ?? '', '/');
$simultaneas = max(1, (int) ($argv[2] ?? 10));
$rondas = max(1, (int) ($argv[3] ?? 5));
if (! preg_match('#^https?://#', $base)) {
    fwrite(STDERR, "Uso: php tools/sandbox/medir-servidor.php https://dominio [simultaneas=10] [rondas=5]\n");
    exit(2);
}

/** @return array<int, array{estado:int, ms:float}> */
function disparar(string $url, int $cantidad): array
{
    $multi = curl_multi_init();
    $manejadores = [];
    for ($i = 0; $i < $cantidad; $i++) {
        $h = curl_init($url . (str_contains($url, '?') ? '&' : '?') . 'm=' . bin2hex(random_bytes(4)));
        curl_setopt_array($h, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 120, CURLOPT_USERAGENT => 'colliers-medicion', CURLOPT_FOLLOWLOCATION => false]);
        curl_multi_add_handle($multi, $h);
        $manejadores[] = $h;
    }
    do {
        $estado = curl_multi_exec($multi, $activos);
        if ($activos) {
            curl_multi_select($multi, 0.05);
        }
    } while ($activos && $estado === CURLM_OK);

    $resultados = [];
    foreach ($manejadores as $h) {
        // Tiempo desde que la conexión está lista hasta el primer byte: descuenta DNS, TCP y TLS.
        $resultados[] = [
            'estado' => curl_getinfo($h, CURLINFO_RESPONSE_CODE),
            'ms' => (curl_getinfo($h, CURLINFO_STARTTRANSFER_TIME) - curl_getinfo($h, CURLINFO_APPCONNECT_TIME) ?: curl_getinfo($h, CURLINFO_STARTTRANSFER_TIME)) * 1000,
        ];
        curl_multi_remove_handle($multi, $h);
        curl_close($h);
    }
    curl_multi_close($multi);

    return $resultados;
}

function mediana(array $valores): float
{
    sort($valores);

    return $valores === [] ? 0.0 : $valores[intdiv(count($valores), 2)];
}

echo "Servidor: {$base} · {$simultaneas} simultáneas · {$rondas} rondas\n\n";
disparar($base . '/up', 2); // precalentamiento
$tabla = [];
foreach (['/robots.txt' => 'Estático (red pura)', '/hora.php' => 'PHP sin framework', '/up' => 'Laravel completo'] as $ruta => $nombre) {
    $sola = [];
    $juntas = [];
    $codigos = [];
    for ($r = 0; $r < $rondas; $r++) {
        foreach (disparar($base . $ruta, 1) as $x) {
            $sola[] = $x['ms'];
            $codigos[$x['estado']] = true;
        }
        foreach (disparar($base . $ruta, $simultaneas) as $x) {
            $juntas[] = $x['ms'];
            $codigos[$x['estado']] = true;
        }
    }
    $tabla[$ruta] = [mediana($sola), mediana($juntas), max($juntas)];
    printf("  %-22s una: %5.0f ms · %d juntas: mediana %5.0f ms, máx %5.0f ms  [HTTP %s]\n",
        $nombre, $tabla[$ruta][0], $simultaneas, $tabla[$ruta][1], $tabla[$ruta][2], implode(',', array_keys($codigos)));
}

$red = $tabla['/robots.txt'][0];
$una = max(1, $tabla['/up'][0] - $red);
$juntas = max(1, $tabla['/up'][1] - $red);
$nucleos = $simultaneas * $una / $juntas;
echo "\nLaravel por petición (sin red): ~" . round($una) . " ms.\n";
printf("Núcleos efectivos estimados: %.1f (con %d peticiones juntas cada una tardó %.1f veces lo que tarda sola).\n", min($simultaneas, $nucleos), $simultaneas, $juntas / $una);
echo "Con OPcache activo Laravel suele quedar bajo 60 ms por petición; muy por encima de 200 ms indica OPcache apagado.\n";
