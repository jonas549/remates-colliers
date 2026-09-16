<?php

namespace App\Listeners;

use App\Models\AccessLog;
use App\Models\Postor;
use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Events\Verified;
use Illuminate\Events\Dispatcher;

/**
 * Bitácora de accesos (`access_logs`). Los intentos fallidos y bloqueos los registra AutenticarUsuario, que tiene
 * el detalle del motivo. Aquí: ingresos, salidas, registro, verificación de correo, restablecimiento y exceso de
 * peticiones.
 */
class RegistroDeAccesos
{
    public function subscribe(Dispatcher $eventos): array
    {
        return [
            Login::class => 'ingreso',
            Logout::class => 'salida',
            Registered::class => 'registro',
            Verified::class => 'verificado',
            PasswordReset::class => 'restablecida',
            Lockout::class => 'exceso',
        ];
    }

    public function ingreso(Login $evento): void
    {
        $this->anotar($evento->user, 'ingreso', ['recordar' => $evento->remember]);
    }

    public function salida(Logout $evento): void
    {
        if ($evento->user instanceof User) {
            $this->anotar($evento->user, 'salida');
        }
    }

    public function registro(Registered $evento): void
    {
        $this->anotar($evento->user, 'registro');
    }

    /** Correo verificado: la cuenta del postor entra a la cola de revisión de Colliers. */
    public function verificado(Verified $evento): void
    {
        $postor = $evento->user instanceof User ? $evento->user->postor : null;
        if ($postor?->estado === Postor::ESTADO_REGISTRADO) {
            $postor->forceFill(['estado' => Postor::ESTADO_EN_REVISION])->save();
        }
        $this->anotar($evento->user, 'correo_verificado');
    }

    public function restablecida(PasswordReset $evento): void
    {
        $this->anotar($evento->user, 'clave_restablecida');
    }

    public function exceso(Lockout $evento): void
    {
        AccessLog::create([
            'email' => mb_substr((string) $evento->request->input('usuario', $evento->request->input('email')), 0, 255),
            'evento' => 'exceso_peticiones',
            'ip' => $evento->request->ip(),
            'user_agent' => mb_substr((string) $evento->request->userAgent(), 0, 512),
        ]);
    }

    private function anotar($user, string $evento, array $detalle = []): void
    {
        $request = request();
        AccessLog::create([
            'user_id' => $user?->getAuthIdentifier(),
            'email' => $user->email ?? null,
            'evento' => $evento,
            'ip' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 512),
            'detalle' => $detalle ?: null,
        ]);
    }
}
