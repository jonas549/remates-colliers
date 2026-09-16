<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'rol', 'estado', 'debe_cambiar_clave'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const ROL_ADMIN = 'admin';

    public const ROL_MARTILLERO = 'martillero';

    public const ROL_POSTOR = 'postor';

    public const ESTADO_ACTIVO = 'activo';

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
        ];
    }

    public function esAdmin(): bool
    {
        return $this->rol === self::ROL_ADMIN;
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
