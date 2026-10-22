<?php

namespace App\Subastas;

/**
 * Medición por tramos del camino de la puja (QA 22/10: 20 postores a la vez, ~3 s por puja). Apagado por defecto
 * (`COLLIERS_MEDIR_PUJA=true` lo enciende): apagado, cada llamada es una comparación. Encendido, la respuesta de la puja
 * lleva una cabecera `Server-Timing` con cada tramo en milisegundos, que lee tools/rendimiento/tramos.php.
 *
 * No cambia ninguna decisión del motor: solo anota tiempos.
 */
final class CronometroPuja
{
    /** @var array<string, float> tramo => ms acumulados */
    private static array $tramos = [];

    /** @var array<string, float> marcas abiertas */
    private static array $inicios = [];

    public static function activo(): bool
    {
        return (bool) config('colliers.medir_puja');
    }

    public static function iniciar(string $tramo): void
    {
        if (self::activo()) {
            self::$inicios[$tramo] = microtime(true);
        }
    }

    public static function terminar(string $tramo): void
    {
        if (self::activo() && isset(self::$inicios[$tramo])) {
            self::$tramos[$tramo] = (self::$tramos[$tramo] ?? 0) + (microtime(true) - self::$inicios[$tramo]) * 1000;
            unset(self::$inicios[$tramo]);
        }
    }

    /** Tiempo desde que el servidor recibió la petición (REQUEST_TIME_FLOAT) hasta ahora, como tramo. */
    public static function desdeRecepcion(string $tramo): void
    {
        if (self::activo() && isset($_SERVER['REQUEST_TIME_FLOAT'])) {
            self::$tramos[$tramo] = (microtime(true) - (float) $_SERVER['REQUEST_TIME_FLOAT']) * 1000;
        }
    }

    public static function contar(string $tramo): void
    {
        if (self::activo()) {
            self::$tramos[$tramo] = (self::$tramos[$tramo] ?? 0) + 1;
        }
    }

    /** Valor de la cabecera Server-Timing, o null si está apagado. */
    public static function cabecera(): ?string
    {
        if (! self::activo()) {
            return null;
        }
        self::desdeRecepcion('total');
        // Archivos PHP cargados y su peso: sin OPcache, cada uno se compila en cada petición («dur» lleva la cantidad).
        $archivos = get_included_files();
        self::$tramos['archivos'] = count($archivos);
        self::$tramos['kb_php'] = array_sum(array_map(fn ($f) => @filesize($f) ?: 0, $archivos)) / 1024;        $valor = implode(', ', array_map(fn ($t, $ms) => sprintf('%s;dur=%.1f', $t, $ms), array_keys(self::$tramos), self::$tramos));
        self::$tramos = self::$inicios = [];

        return $valor;
    }
}
