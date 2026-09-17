<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;

/**
 * Crea una cuenta de administración o de martillero desde la terminal. El panel todavía no tiene pantalla de usuarios
 * internos (no hay diseño): publicar un remate exige martillero, así que sin esto no se puede probar en el sandbox.
 * La clave es aleatoria, se muestra una sola vez y se exige cambiarla en el primer ingreso (/admin/ingresar).
 */
class CrearUsuario extends Command
{
    protected $signature = 'colliers:crear-usuario {rol : admin o martillero} {email} {nombre*}';

    protected $description = 'Crea un administrador o martillero con clave temporal';

    public function handle(): int
    {
        $rol = $this->argument('rol');
        $email = mb_strtolower(trim($this->argument('email')));
        $nombre = trim(implode(' ', $this->argument('nombre')));

        $validacion = Validator::make(['rol' => $rol, 'email' => $email, 'nombre' => $nombre], [
            'rol' => ['required', 'in:' . User::ROL_ADMIN . ',' . User::ROL_MARTILLERO],
            'email' => ['required', 'email', 'unique:users,email'],
            'nombre' => ['required', 'max:120'],
        ]);
        if ($validacion->fails()) {
            foreach ($validacion->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $clave = Str::password(16, symbols: false);
        $user = User::create(['name' => $nombre, 'email' => $email, 'password' => $clave, 'rol' => $rol, 'estado' => User::ESTADO_ACTIVO, 'debe_cambiar_clave' => true]);
        $user->forceFill(['email_verified_at' => now()])->save();

        $this->info(($rol === User::ROL_ADMIN ? 'Administrador' : 'Martillero') . " creado: {$email}");
        $this->warn("Clave temporal (se muestra una sola vez): {$clave}");
        $this->line('Ingreso: ' . rtrim((string) config('app.url'), '/') . '/admin/ingresar (pedirá cambiar la clave).');

        return self::SUCCESS;
    }
}
