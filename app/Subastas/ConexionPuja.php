<?php

namespace App\Subastas;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Conexión a la base con reintento para la petición de puja (21/09, riesgo del Bloque R).
 *
 * En el hosting compartido MariaDB rechazó conexiones (1040) y se reinició (2002). Sin reintento, una puja recibida en
 * ese instante falla aunque la base vuelva en un segundo. La hora de recepción NO se toca: la sella HoraRecepcion desde
 * REQUEST_TIME_FLOAT, que PHP fija al recibir la petición; esperar aquí solo retrasa la respuesta.
 *
 * Solo errores de CONEXIÓN; cualquier otro error sale de inmediato. Presupuesto acotado (config colliers.conexion_puja):
 * sirve para un pico de segundos, no para una caída larga.
 */
class ConexionPuja
{
    /** 1040 límite global · 1203/1226 límite de la cuenta · 2002 sin socket o puerto · 2006 servidor desaparecido. */
    public const CODIGOS = [1040, 1203, 1226, 2002, 2006];

    /**
     * Abre la conexión por defecto reintentando ante errores de conexión. Si el presupuesto se agota, lanza el último
     * error. Devuelve cuántos intentos hizo.
     */
    public function abrir(): int
    {
        $presupuesto = max(0, (int) config('colliers.conexion_puja.reintento_ms'));
        $pausa = max(1, (int) config('colliers.conexion_puja.espera_ms'));

        $esperado = 0;
        $intentos = 0;
        while (true) {
            $intentos++;
            try {
                $this->conectar();
                if ($intentos > 1) {
                    Log::warning('Puja: la base rechazó la conexión y se recuperó con reintento.', ['intentos' => $intentos, 'espera_ms' => $esperado]);
                }

                return $intentos;
            } catch (Throwable $e) {
                if (self::codigo($e) === null || $esperado + $pausa > $presupuesto) {
                    if (self::codigo($e) !== null) {
                        Log::error('Puja: la base no aceptó la conexión tras reintentar.', ['intentos' => $intentos, 'espera_ms' => $esperado, 'error' => $e->getMessage()]);
                    }
                    throw $e;
                }
                // Laravel guarda el resolvedor de la conexión mientras no abre: se vuelve a llamar tal cual.
                $this->esperar($pausa);
                $esperado += $pausa;
            }
        }
    }

    protected function conectar(): void
    {
        DB::connection()->getPdo();
    }

    protected function esperar(int $ms): void
    {
        usleep($ms * 1000);
    }

    /** Código del driver si el error es de conexión (reintentable); null si es cualquier otro. */
    public static function codigo(Throwable $e): ?int
    {
        for ($actual = $e; $actual !== null; $actual = $actual->getPrevious()) {
            // PDO arma el mensaje como «SQLSTATE[08004] [1040] Too many connections».
            if (preg_match('/SQLSTATE\[[0-9A-Z]{5}\] \[(\d{4})\]/', $actual->getMessage(), $m) && in_array((int) $m[1], self::CODIGOS, true)) {
                return (int) $m[1];
            }
            $info = property_exists($actual, 'errorInfo') ? ($actual->errorInfo[1] ?? null) : null;
            if (is_int($info) && in_array($info, self::CODIGOS, true)) {
                return $info;
            }
        }

        return null;
    }

    /**
     * Margen de liquidación mínimo para que una puja recibida antes de T alcance a tomar el bloqueo del lote aunque
     * haya tenido que reintentar la conexión: reintento + procesamiento, redondeado a segundos hacia arriba.
     */
    public static function margenSeguroSegundos(): int
    {
        $ms = max(0, (int) config('colliers.conexion_puja.reintento_ms')) + max(0, (int) config('colliers.conexion_puja.procesamiento_ms'));

        return max(1, (int) ceil($ms / 1000));
    }
}
