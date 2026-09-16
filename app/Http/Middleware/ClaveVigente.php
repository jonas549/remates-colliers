<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cambio de contraseña obligatorio (`users.debe_cambiar_clave`): el primer administrador creado por
 * `colliers:instalar` y toda cuenta cuya clave restableció un administrador. Hasta cambiarla solo puede ver la
 * pantalla de cambio y cerrar sesión.
 */
class ClaveVigente
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->debe_cambiar_clave) {
            return $request->expectsJson()
                ? response()->json(['message' => 'Debes cambiar tu contraseña antes de continuar.'], 403)
                : redirect()->route('cuenta.clave');
        }

        return $next($request);
    }
}
