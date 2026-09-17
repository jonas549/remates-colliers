<?php

namespace App\Autenticacion;

use App\Models\AccessLog;
use App\Models\Configuracion;
use App\Models\Postor;
use App\Models\User;
use App\Support\Rut;
use App\Support\Sitio;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Fortify;

/**
 * Ingreso con correo o RUT (diseño del Login) y bloqueo tras intentos fallidos.
 *
 * - Dos accesos separados: /ingresar solo para postores y /admin/ingresar solo para administración (admin y martillero).
 * - El contador de intentos y el bloqueo viven en la tabla `users` (no en caché): vaciar la caché no desbloquea.
 * - Valores configurables: login_intentos_maximos (5, diseño) y login_bloqueo_minutos (15, decisión del 16/09).
 * - Cada intento queda en `access_logs`.
 */
class AutenticarUsuario
{
    public const ROLES_ADMINISTRACION = [User::ROL_ADMIN, User::ROL_MARTILLERO];

    public function __invoke(Request $request): ?User
    {
        $identificador = trim((string) $request->input(Fortify::username()));
        $clave = (string) $request->input('password');
        $portal = $request->routeIs('admin.ingresar*') ? 'administracion' : 'postores';
        $ahora = CarbonImmutable::now('UTC');

        $user = $this->buscar($identificador);
        if ($user === null) {
            $this->registrar($request, null, $identificador, 'ingreso_fallido', ['motivo' => 'usuario_inexistente', 'portal' => $portal]);
            $this->fallar('Usuario o contraseña incorrectos.');
        }

        if ($user->bloqueado_hasta !== null && $ahora->lessThan($user->bloqueado_hasta)) {
            $this->registrar($request, $user, $identificador, 'ingreso_bloqueado', ['portal' => $portal]);
            $hasta = $user->bloqueado_hasta->setTimezone(config('colliers.zona_visualizacion'))->format('H:i');
            $this->fallar("La cuenta está bloqueada por seguridad hasta las {$hasta}. Si necesitas ingresar antes, escribe a " . Sitio::correo() . '.');
        }

        if (! Hash::check($clave, $user->password)) {
            $this->intentoFallido($request, $user, $identificador, $portal, $ahora);
        }

        $esAdministracion = in_array($user->rol, self::ROLES_ADMINISTRACION, true);
        if ($portal === 'administracion' && ! $esAdministracion) {
            $this->registrar($request, $user, $identificador, 'ingreso_fallido', ['motivo' => 'portal_equivocado', 'portal' => $portal]);
            $this->fallar('Esta cuenta es de postor: ingresa por el acceso de postores.');
        }
        if ($portal === 'postores' && $esAdministracion) {
            $this->registrar($request, $user, $identificador, 'ingreso_fallido', ['motivo' => 'portal_equivocado', 'portal' => $portal]);
            $this->fallar('Esta cuenta es de administración: ingresa por el acceso de administradores.');
        }

        if ($user->estado !== User::ESTADO_ACTIVO) {
            $this->registrar($request, $user, $identificador, 'ingreso_fallido', ['motivo' => 'cuenta_inactiva', 'portal' => $portal]);
            $this->fallar('La cuenta está deshabilitada. Escribe a ' . Sitio::correo() . '.');
        }

        if ($user->intentos_fallidos > 0 || $user->bloqueado_hasta !== null) {
            $user->forceFill(['intentos_fallidos' => 0, 'bloqueado_hasta' => null])->saveQuietly();
        }

        return $user;
    }

    /** Correo (con @) o RUT (del postor, por índice ciego). */
    private function buscar(string $identificador): ?User
    {
        if ($identificador === '') {
            return null;
        }
        if (str_contains($identificador, '@')) {
            return User::where('email', mb_strtolower($identificador))->first();
        }
        if (Rut::esValido($identificador)) {
            return Postor::porRut($identificador)->first()?->user;
        }

        return null;
    }

    private function intentoFallido(Request $request, User $user, string $identificador, string $portal, CarbonImmutable $ahora): never
    {
        $maximo = max(1, (int) Configuracion::valor('login_intentos_maximos'));
        $minutos = max(1, (int) Configuracion::valor('login_bloqueo_minutos'));

        // Incremento en la base (atómico): dos intentos simultáneos no pierden la cuenta.
        User::whereKey($user->id)->increment('intentos_fallidos');
        $intentos = (int) User::whereKey($user->id)->value('intentos_fallidos');

        if ($intentos >= $maximo) {
            $hasta = $ahora->addMinutes($minutos);
            $user->forceFill(['intentos_fallidos' => 0, 'bloqueado_hasta' => $hasta])->saveQuietly();
            $this->registrar($request, $user, $identificador, 'bloqueo', ['intentos' => $intentos, 'minutos' => $minutos, 'portal' => $portal]);
            $this->fallar("Usuario o contraseña incorrectos. Bloqueamos la cuenta por seguridad durante {$minutos} minutos.");
        }

        $this->registrar($request, $user, $identificador, 'ingreso_fallido', ['motivo' => 'clave_incorrecta', 'intentos' => $intentos, 'portal' => $portal]);
        $quedan = $maximo - $intentos;
        $this->fallar('Usuario o contraseña incorrectos. Te ' . ($quedan === 1 ? 'queda 1 intento' : "quedan {$quedan} intentos") . ' antes de que bloqueemos la cuenta por seguridad.');
    }

    private function registrar(Request $request, ?User $user, string $identificador, string $evento, array $detalle): void
    {
        AccessLog::create([
            'user_id' => $user?->id,
            'email' => mb_substr($identificador, 0, 255),
            'evento' => $evento,
            'ip' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 512),
            'detalle' => $detalle,
        ]);
    }

    private function fallar(string $mensaje): never
    {
        throw ValidationException::withMessages([Fortify::username() => $mensaje]);
    }
}
