<?php

namespace App\Actions\Fortify;

use App\Autenticacion\Sesiones;
use App\Models\AccessLog;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\UpdatesUserPasswords;

/**
 * Cambio de la contraseña propia (voluntario u obligatorio). Exige la contraseña actual, cierra las DEMÁS sesiones
 * del usuario y quita la marca de cambio obligatorio.
 */
class UpdateUserPassword implements UpdatesUserPasswords
{
    use PasswordValidationRules;

    /**
     * @param  array<string, string>  $input
     *
     * @throws ValidationException
     */
    public function update(User $user, array $input): void
    {
        Validator::make($input, [
            'current_password' => ['required', 'string', 'current_password:web'],
            'password' => [...$this->passwordRules(), 'different:current_password'],
        ], [
            'current_password.current_password' => 'La contraseña actual no es correcta.',
            'password.different' => 'La nueva contraseña debe ser distinta de la actual.',
        ])->validateWithBag('updatePassword');

        $user->forceFill([
            'password' => $input['password'],
            'debe_cambiar_clave' => false,
        ])->save();

        $cerradas = Sesiones::cerrarTodas($user, request()->hasSession() ? request()->session()->getId() : null);

        AccessLog::create([
            'user_id' => $user->id, 'email' => $user->email, 'evento' => 'cambio_clave',
            'ip' => request()->ip(), 'user_agent' => mb_substr((string) request()->userAgent(), 0, 512),
            'detalle' => ['sesiones_cerradas' => $cerradas],
        ]);
    }
}
