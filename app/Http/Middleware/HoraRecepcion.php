<?php

namespace App\Http\Middleware;

use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Hora de recepción de la petición, con microsegundos (middleware global).
 * Una puja es válida si se RECIBIÓ antes del cierre, aunque termine de procesarse después (CLAUDE.md §3).
 *
 * Se toma de REQUEST_TIME_FLOAT, que PHP fija al recibir la petición, ANTES de compilar y arrancar Laravel. Sin OPcache
 * el arranque cuesta ~350 ms de CPU por petición y, con varias pujas simultáneas en un hosting de 1 núcleo, varios
 * segundos: medir la hora en este middleware castigaría a quien pujó a tiempo (docs/RENDIMIENTO-SIN-OPCACHE.md).
 * El cliente no puede fijar ese valor (PHP lo sobrescribe). Si falta o es absurdo, se usa la hora actual.
 */
class HoraRecepcion
{
    public const ATRIBUTO = 'colliers.hora_recepcion';

    /** Más atrás que esto no es una petición en curso: algo anda mal con el valor y se descarta. */
    private const ANTIGUEDAD_MAXIMA_SEGUNDOS = 120;

    public function handle(Request $request, Closure $next): Response
    {
        $request->attributes->set(self::ATRIBUTO, self::medir($request));

        return $next($request);
    }

    public static function de(Request $request): CarbonImmutable
    {
        return $request->attributes->get(self::ATRIBUTO) ?? self::medir($request);
    }

    private static function medir(Request $request): CarbonImmutable
    {
        $ahora = CarbonImmutable::now('UTC');
        // Las pruebas fijan el reloj con Carbon: ahí manda ese reloj, no el de la petición simulada.
        if (CarbonImmutable::hasTestNow()) {
            return $ahora;
        }

        $recibida = $request->server('REQUEST_TIME_FLOAT');
        if (! is_float($recibida) && ! is_numeric($recibida)) {
            return $ahora;
        }
        $fecha = CarbonImmutable::createFromFormat('U.u', sprintf('%.6F', (float) $recibida), 'UTC');
        if (! $fecha instanceof CarbonImmutable) {
            return $ahora;
        }
        $antiguedad = $ahora->getTimestampMs() - $fecha->getTimestampMs();

        return $antiguedad >= 0 && $antiguedad <= self::ANTIGUEDAD_MAXIMA_SEGUNDOS * 1000 ? $fecha : $ahora;
    }
}
