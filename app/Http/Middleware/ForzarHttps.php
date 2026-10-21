<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * QA 21/10, 2.1: http:// respondía 200 (ingreso, registro con documentos de identidad y la sala viajaban sin cifrar).
 * Parte de la aplicación: toda petición por http se redirige a la misma dirección en https (301 para GET/HEAD; 308 para
 * el resto, que conserva el método y el cuerpo). La redirección del servidor (LiteSpeed/cPanel) la configura Jonas.
 *
 * `X-Forwarded-Proto: https` también cuenta como https: si el hosting termina el TLS en un proxy, la petición llega por
 * http y sin esto la redirección no terminaría nunca. Falsearla solo le sirve a quien la falsea para no ser redirigido.
 * `hora.php` y `presencia.php` no pasan por Laravel: los llama la página, que ya está en https.
 */
class ForzarHttps
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('colliers.seguridad.forzar_https') || self::esSegura($request)) {
            return $next($request);
        }

        return redirect()->secure($request->getRequestUri(), $request->isMethod('GET') || $request->isMethod('HEAD') ? 301 : 308);
    }

    public static function esSegura(Request $request): bool
    {
        return $request->secure() || strtolower((string) $request->header('X-Forwarded-Proto')) === 'https';
    }
}
