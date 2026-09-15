<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Protege todo el sitio con una clave mientras el subdominio es un sandbox público.
 * Activo solo si COLLIERS_ACCESO_CLAVE tiene valor; en producción se deja vacío.
 *
 * La cookie guarda un hash de la clave (no la clave): cambiar la clave en el .env invalida todos los accesos.
 */
class AccesoSandbox
{
    public const COOKIE = 'colliers_acceso';

    public function handle(Request $request, Closure $next): Response
    {
        $clave = config('colliers.acceso.clave');
        if (blank($clave)) {
            return $next($request);
        }

        $libre = $request->routeIs('acceso.*') || $request->is('up');
        $autorizado = hash_equals(self::huella($clave), (string) $request->cookie(self::COOKIE));

        if (! $libre && ! $autorizado) {
            if ($request->isMethod('GET')) {
                $request->session()->put('url.intended', $request->fullUrl());
            }

            return redirect()->route('acceso.formulario');
        }

        $respuesta = $next($request);
        // Mientras el sandbox está protegido, que no lo indexen los buscadores.
        $respuesta->headers->set('X-Robots-Tag', 'noindex, nofollow');

        return $respuesta;
    }

    public static function huella(string $clave): string
    {
        return hash_hmac('sha256', $clave, (string) config('app.key'));
    }
}
