<?php

namespace App\Postores;

use App\Models\Garantia;
use App\Models\Remate;
use App\Models\User;

/**
 * «¿Este postor puede pujar en este remate?», en un solo lugar.
 *
 * La pregunta estaba repetida en el motor de pujas, la sala, la ficha pública, el catálogo, los documentos
 * reservados y el estado de la cuenta: seis copias de la misma condición. Ahora la responde esta clase.
 *
 * Hoy rige el **modo por remate** (acta y decisión del cliente): la garantía se constituye para CADA remate.
 * Jonas pidió (20/09) poder ofrecer además el modo «postor habilitado una vez para todos los remates». Ese modo
 * está pendiente de una definición del cliente —**sobre qué monto se constituye una garantía que no pertenece a
 * ningún remate**— y de sus consecuencias (una sola garantía respaldando varias adjudicaciones, las bases de cada
 * remate como documento legal, el cierre de garantías por remate y la numeración «Postor #N»).
 *
 * Cuando esa definición llegue, el cambio es: agregar la clave de configuración con el modo y hacer que
 * `garantia()` acepte, en modo único, una garantía aprobada de cualquier remate. Nada más consulta la tabla.
 */
final class Habilitacion
{
    /** La garantía se constituye para cada remate (lo vigente). */
    public const MODO_POR_REMATE = 'remate';

    /** Una habilitación sirve para todos los remates. Pendiente del monto; no se activa todavía. */
    public const MODO_UNICO = 'unico';

    public static function modo(): string
    {
        return self::MODO_POR_REMATE;
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

        return Garantia::where('user_id', $user->id)->where('remate_id', $remate->id)->latest('id')->first();
    }

    /** ¿Tiene la garantía de ESTE remate aprobada? No revisa el estado de la cuenta. */
    public static function conGarantiaAprobada(?User $user, Remate $remate): bool
    {
        if ($user === null) {
            return false;
        }

        return Garantia::where('user_id', $user->id)->where('remate_id', $remate->id)
            ->where('estado', Garantia::ESTADO_APROBADA)->exists();
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
