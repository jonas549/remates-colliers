<?php

namespace App\Http\Middleware;

use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Marca la hora de recepción de la petición, con microsegundos, lo antes posible (middleware global).
 * Una puja es válida si se RECIBIÓ antes del cierre, aunque termine de procesarse después (CLAUDE.md §3):
 * esta es la hora que se compara, no la del momento en que se obtiene el bloqueo del lote.
 */
class HoraRecepcion
{
    public const ATRIBUTO = 'colliers.hora_recepcion';

    public function handle(Request $request, Closure $next): Response
    {
        $request->attributes->set(self::ATRIBUTO, CarbonImmutable::now('UTC'));

        return $next($request);
    }

    public static function de(Request $request): CarbonImmutable
    {
        return $request->attributes->get(self::ATRIBUTO) ?? CarbonImmutable::now('UTC');
    }
}
