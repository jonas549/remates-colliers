<?php

namespace App\Postores;

use App\Models\Configuracion;
use App\Models\Garantia;
use App\Models\Remate;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * «¿Este postor puede pujar en este remate?», en un solo lugar. La pregunta estaba repetida en el motor de pujas,
 * la sala, la ficha pública, el catálogo, los documentos reservados y el estado de la cuenta.
 *
 * Dos modos, elegibles desde el panel (Configuración → Garantías):
 *
 * - **Por remate** (por defecto, y lo que dice el acta): la garantía se constituye para CADA remate.
 * - **Habilitación única**: el postor constituye UNA garantía, por un monto fijo que define Colliers, y queda
 *   habilitado para pujar en todos los remates.
 *
 * El modo único exige el monto (`garantia_monto_unico`): una garantía que no pertenece a un remate no tiene precio
 * base sobre el cual calcular el porcentaje. Sin monto, el panel no deja activarlo.
 *
 * Cambiar de modo no borra nada: las garantías por remate ya aprobadas siguen habilitando su remate.
 */
final class Habilitacion
{
    /** La garantía se constituye para cada remate (lo vigente). */
    public const MODO_POR_REMATE = 'remate';

    /** Una habilitación sirve para todos los remates. Pendiente del monto; no se activa todavía. */
    public const MODO_UNICO = 'unico';

    public static function modo(): string
    {
        return Configuracion::valor('garantia_modo') === self::MODO_UNICO ? self::MODO_UNICO : self::MODO_POR_REMATE;
    }

    /** Monto de la habilitación única, o null si Colliers todavía no lo fijó. */
    public static function montoUnico(): ?int
    {
        $monto = (int) Configuracion::valor('garantia_monto_unico');

        return $monto > 0 ? $monto : null;
    }

    /**
     * Garantía de este postor para este remate (la última, si hubo reenvíos). `null` = no está inscrito.
     * Una sola consulta: este camino lo recorre cada puja y el servidor puede no tener OPcache.
     */
    public static function garantia(?User $user, Remate $remate): ?Garantia
    {
        if ($user === null) {
            return null;
        }

        // Modo único: la habilitación del postor, venga del remate que venga. Si además tiene una garantía de ESTE
        // remate (constituida antes de cambiar el modo), vale la que esté aprobada.
        if (self::modo() === self::MODO_UNICO) {
            return self::mejor(Garantia::where('user_id', $user->id)
                ->where(fn ($q) => $q->where('alcance', Garantia::ALCANCE_UNICO)->orWhere('remate_id', $remate->id))
                ->orderByDesc('id')->get());
        }

        return Garantia::where('user_id', $user->id)->where('remate_id', $remate->id)->latest('id')->first();
    }

    /** La aprobada manda; si no hay, la más reciente (para poder explicar en qué estado quedó). */
    private static function mejor(Collection $garantias): ?Garantia
    {
        return $garantias->firstWhere('estado', Garantia::ESTADO_APROBADA) ?? $garantias->first();
    }

    /** ¿Tiene la garantía de ESTE remate aprobada? No revisa el estado de la cuenta. */
    public static function conGarantiaAprobada(?User $user, Remate $remate): bool
    {
        if ($user === null) {
            return false;
        }

        return Garantia::where('user_id', $user->id)->where('estado', Garantia::ESTADO_APROBADA)
            ->when(self::modo() === self::MODO_UNICO,
                fn ($q) => $q->where(fn ($s) => $s->where('alcance', Garantia::ALCANCE_UNICO)->orWhere('remate_id', $remate->id)),
                fn ($q) => $q->where('remate_id', $remate->id))
            ->exists();
    }

    /** Las tres condiciones del acta: registrado, cuenta aprobada por Colliers y garantía aprobada del remate. */
    public static function puedePujar(?User $user, Remate $remate): bool
    {
        return self::habilita($user, self::garantia($user, $remate));
    }

    /** La misma regla cuando la garantía ya está en la mano: no repite la consulta. */
    public static function habilita(?User $user, ?Garantia $garantia): bool
    {
        return $user?->postor?->estaAprobado() === true && $garantia?->estado === Garantia::ESTADO_APROBADA;
    }
}
