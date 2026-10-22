<?php

namespace App\Http\Middleware;

use App\Autenticacion\Permisos;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restringe una ruta del panel a un permiso: `permiso:remates.editar` (App\Autenticacion\Permisos). Sin sesión lleva al
 * acceso de administración; con sesión y sin el permiso responde 403. Esconder un botón no basta: esto es lo que protege.
 */
class Permiso
{
    public function handle(Request $request, Closure $next, string $permiso): Response
    {
        $user = $request->user();

        if ($user === null) {
            return $request->expectsJson()
                ? response()->json(['message' => __('acceso.mensajes.no_autenticado')], 401)
                : redirect()->guest(route('admin.ingresar'));
        }

        abort_unless(Permisos::permite($user, $permiso), 403);

        return $next($request);
    }
}
