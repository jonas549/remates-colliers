<?php

namespace App\Autenticacion;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Sesiones activas de un usuario (driver `database` de sesiones, el del servidor).
 * Cerrar sesiones = borrar sus filas: la próxima petición de ese navegador llega sin sesión.
 * También se renueva el remember_token para invalidar los «Mantener sesión iniciada».
 */
final class Sesiones
{
    public static function disponible(): bool
    {
        return config('session.driver') === 'database';
    }

    /** @return Collection<int, object{id:string, ip:?string, navegador:?string, ultima_actividad:CarbonImmutable, actual:bool}> */
    public static function de(User $user, ?string $sesionActual = null): Collection
    {
        if (! self::disponible()) {
            return collect();
        }

        return DB::table(config('session.table', 'sessions'))
            ->where('user_id', $user->id)
            ->orderByDesc('last_activity')
            ->get()
            ->map(fn ($s) => (object) [
                'id' => $s->id,
                'ip' => $s->ip_address,
                'navegador' => $s->user_agent,
                'ultima_actividad' => CarbonImmutable::createFromTimestamp($s->last_activity, 'UTC'),
                'actual' => $s->id === $sesionActual,
            ]);
    }

    /** Cierra todas las sesiones del usuario salvo `$excepto`. Devuelve cuántas cerró. */
    public static function cerrarTodas(User $user, ?string $excepto = null): int
    {
        $user->forceFill(['remember_token' => \Illuminate\Support\Str::random(60)])->saveQuietly();

        if (! self::disponible()) {
            return 0;
        }

        return DB::table(config('session.table', 'sessions'))
            ->where('user_id', $user->id)
            ->when($excepto, fn ($q) => $q->where('id', '!=', $excepto))
            ->delete();
    }

    /** Cierra una sesión solo si pertenece al usuario (nunca por id sin filtrar por dueño). */
    public static function cerrarUna(User $user, string $id): bool
    {
        if (! self::disponible()) {
            return false;
        }

        return DB::table(config('session.table', 'sessions'))->where('user_id', $user->id)->where('id', $id)->delete() > 0;
    }
}
