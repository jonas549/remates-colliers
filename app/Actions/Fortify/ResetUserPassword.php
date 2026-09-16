<?php

namespace App\Actions\Fortify;

use App\Autenticacion\Sesiones;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\ResetsUserPasswords;

/** Restablecer la contraseña con el enlace del correo: cierra todas las sesiones y levanta el bloqueo por intentos. */
class ResetUserPassword implements ResetsUserPasswords
{
    use PasswordValidationRules;

    /**
     * @param  array<string, string>  $input
     *
     * @throws ValidationException
     */
    public function reset(User $user, array $input): void
    {
        Validator::make($input, [
            'password' => $this->passwordRules(),
        ])->validate();

        $user->forceFill([
            'password' => $input['password'],
            'debe_cambiar_clave' => false,
            'intentos_fallidos' => 0,
            'bloqueado_hasta' => null,
        ])->save();

        Sesiones::cerrarTodas($user);
    }
}
