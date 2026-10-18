<?php

/*
 * Latido de presencia de la sala de pujas, SIN arrancar Laravel (docs/RENDIMIENTO-SIN-OPCACHE.md), como hora.php.
 *
 * El navegador del postor lo llama cada ~15 s mientras tiene la sala abierta, y una vez al cerrarla (sendBeacon), con el
 * token firmado que Laravel le entregó al abrir la sala. No toca la base de datos ni la sesión. Lógica y reglas:
 * App\Subastas\PresenciaSala. Responde solo el número de conectados: nunca nombres.
 */

header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('X-Robots-Tag: noindex, nofollow');

require __DIR__ . '/../app/Subastas/PresenciaSala.php';

use App\Subastas\PresenciaSala;

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo '{"error":"metodo"}';

    return;
}

// Lo deja Laravel (App\Subastas\Presencia::publicarAjustes): clave del token y carpetas.
$ajustes = json_decode((string) @file_get_contents(getenv('COLLIERS_PRESENCIA_AJUSTES') ?: __DIR__ . '/../storage/app/presencia-ajustes.json'), true);
if (! is_array($ajustes) || ! is_string($ajustes['clave'] ?? null)) {
    http_response_code(503);
    echo '{"error":"sin_ajustes"}';

    return;
}

$ahoraMs = (int) floor(microtime(true) * 1000);
$datos = PresenciaSala::verificar((string) ($_POST['token'] ?? ''), $ajustes['clave'], $ahoraMs);
if ($datos === null) {
    http_response_code(403);
    echo '{"error":"token"}';

    return;
}

$presencia = new PresenciaSala((string) $ajustes['carpeta'], (string) $ajustes['carpeta_tiempo_real'], (int) ($ajustes['ventana_ms'] ?? 45000));
$conectados = ($_POST['accion'] ?? 'latido') === 'salir'
    ? $presencia->salir($datos['r'], $datos['s'], $datos['u'], $ahoraMs)
    : $presencia->latido($datos['r'], $datos['s'], $datos['u'], $ahoraMs);

echo '{"conectados":' . $conectados . '}';
