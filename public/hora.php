<?php

/*
 * Hora oficial del servidor SIN arrancar Laravel (docs/RENDIMIENTO-SIN-OPCACHE.md).
 *
 * El cronómetro del navegador se sincroniza contra esto varias veces por minuto, por cada postor y espectador.
 * Sin OPcache, cada petición a Laravel compila ~6 MB de PHP (~350 ms de CPU); este archivo no carga nada.
 * Misma respuesta que la ruta /hora (TiempoRealController::hora), que se mantiene como respaldo.
 * No expone nada privado: por eso no pasa por la clave de acceso del sandbox, igual que el JSON de estado.
 */

header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('X-Robots-Tag: noindex, nofollow');

echo '{"servidor_ms":' . (int) floor(microtime(true) * 1000) . '}';
