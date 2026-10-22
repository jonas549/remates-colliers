<?php

namespace App\Actions\Fortify;

use App\Autenticacion\AutenticarUsuario;
use App\Autenticacion\SegundoFactor;
use Closure;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Http\Request;
use Laravel\Fortify\LoginRateLimiter;

/**
 * Paso del ingreso de Fortify que reemplaza a AttemptToAuthenticate (22/10). Valida usuario y clave con
 * AutenticarUsuario (bloqueo, portal, bitácora) y:
 * - si la cuenta exige segundo factor y este navegador no está recordado, NO abre sesión: envía el código y lleva a
 *   la pantalla para escribirlo (también si el envío falló: ahí se explica y se puede reintentar);
 * - si no, abre la sesión como siempre.
 */
class IngresoConSegundoFactor
{
    public function __construct(
        private readonly StatefulGuard $guard,
        private readonly LoginRateLimiter $limitador,
        private readonly SegundoFactor $segundoFactor,
    ) {}

    public function handle(Request $request, Closure $next)
    {
        $user = app(AutenticarUsuario::class)($request);

        if (SegundoFactor::exige($user) && $this->segundoFactor->dispositivoRecordado($request, $user) === null) {
            $this->limitador->clear($request);
            $error = $this->segundoFactor->iniciar($request, $user, $request->boolean('remember'));

            return redirect()->route('admin.ingresar.codigo')->with($error ? 'error_envio' : 'enviado', $error ?? true);
        }

        $this->guard->login($user, $request->boolean('remember'));
        $this->limitador->clear($request);

        return $next($request);
    }
}
