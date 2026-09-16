<?php

namespace App\Models;

use App\Casts\FechaUtc;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'rol', 'estado', 'debe_cambiar_clave'])]
#[Hidden(['password', 'remember_token'])]
/**
 * Credenciales y rol. Los datos del postor van en `postores`. La verificación de correo se exige solo en las rutas de
 * postores (middleware `verified`); las cuentas de administración las crea Colliers.
 * `intentos_fallidos` y `bloqueado_hasta` no son asignables en masa: solo los escribe el ingreso.
 */
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const ROL_ADMIN = 'admin';

    public const ROL_MARTILLERO = 'martillero';

    public const ROL_POSTOR = 'postor';

    public const ESTADO_ACTIVO = 'activo';

    public const ESTADO_INACTIVO = 'inactivo';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'debe_cambiar_clave' => 'boolean',
            'intentos_fallidos' => 'integer',
            'bloqueado_hasta' => FechaUtc::class,
        ];
    }

    public function esAdmin(): bool
    {
        return $this->rol === self::ROL_ADMIN;
    }

    public function esAdministracion(): bool
    {
        return in_array($this->rol, [self::ROL_ADMIN, self::ROL_MARTILLERO], true);
    }

    public function postor(): HasOne
    {
        return $this->hasOne(Postor::class);
    }

    public function garantias(): HasMany
    {
        return $this->hasMany(Garantia::class);
    }

    public function pujas(): HasMany
    {
        return $this->hasMany(Puja::class);
    }
}
