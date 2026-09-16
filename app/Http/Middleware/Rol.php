<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restringe una ruta a ciertos roles: `rol:admin,martillero`. Sin sesión redirige al acceso que corresponde
 * (administración o postores); con sesión y otro rol responde 403. Una cuenta inactiva se trata como sin permiso.
 */
class Rol
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if ($user === null) {
            return $request->expectsJson()
                ? response()->json(['message' => 'No autenticado.'], 401)
                : redirect()->guest(route(array_intersect($roles, [User::ROL_ADMIN, User::ROL_MARTILLERO]) ? 'admin.ingresar' : 'login'));
        }

        abort_unless(in_array($user->rol, $roles, true) && $user->estado === User::ESTADO_ACTIVO, 403);

        return $next($request);
    }
}
