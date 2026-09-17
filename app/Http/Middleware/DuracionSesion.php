<?php

namespace App\Http\Middleware;

use App\Models\Configuracion;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Duración de la sesión configurable desde el panel (17/09): ningún valor de negocio debe exigir tocar el `.env`
 * del servidor. Corre antes de StartSession, que es quien lee `session.lifetime` para la cookie y para la caducidad.
 */
class DuracionSesion
{
    public function handle(Request $request, Closure $next): Response
    {
        try {
            $minutos = (int) Configuracion::valor('sesion_minutos');
        } catch (Throwable) {
            return $next($request); // sin base (instalación): se queda lo del .env
        }

        if ($minutos > 0) {
            config(['session.lifetime' => max(15, min(720, $minutos))]);
        }

        return $next($request);
    }
}
