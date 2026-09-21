<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Fuera de producción (sandbox con APP_ENV=staging, local) ningún buscador indexa el sitio: el sandbox tiene datos de
 * prueba y es de acceso libre (la clave de acceso se quitó el 21/09, a pedido de Jonas). En producción no se toca:
 * los remates son públicos.
 */
class NoIndexar
{
    public function handle(Request $request, Closure $next): Response
    {
        $respuesta = $next($request);
        if (! app()->isProduction()) {
            $respuesta->headers->set('X-Robots-Tag', 'noindex, nofollow');
        }

        return $respuesta;
    }
}
