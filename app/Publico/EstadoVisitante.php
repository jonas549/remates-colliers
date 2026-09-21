<?php

namespace App\Publico;

use App\Models\Garantia;
use App\Models\Remate;
use App\Postores\Habilitacion;
use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * Qué ve quien navega el sitio público (Bloque N), con las mismas cuatro variantes del diseño:
 * visitante | registrado (postor sin garantía aprobada ni en revisión) | en-revision | aprobada.
 * En el listado manda la inscripción más relevante del postor; en el detalle, la de ESE remate.
 * Solo en local, ?sesion= fuerza una variante (arnés de comparación visual).
 */
class EstadoVisitante
{
    public static function para(?User $user, ?Remate $remate = null): array
    {
        $esPostor = $user?->rol === User::ROL_POSTOR;
        $datos = [
            'sesion' => 'visitante',
            'logueado' => $user !== null,
            'esAdministracion' => (bool) $user?->esAdministracion(),
            'remate' => null,
            'garantia' => null,
            'cuentaAprobada' => $esPostor && $user->postor?->estaAprobado(),
        ];

        if (app()->isLocal() && in_array(request('sesion'), ['registrado', 'en-revision', 'aprobada'], true)) {
            return ['sesion' => request('sesion'), 'logueado' => true] + $datos;
        }
        if (! $esPostor) {
            return $datos;
        }

        // Con el modo «habilitación única» la garantía no pertenece a este remate: igual habilita, así que entra.
        $unico = Habilitacion::modo() === Habilitacion::MODO_UNICO;
        $garantias = Garantia::with('remate.lotes')->where('user_id', $user->id)
            ->when($remate && ! $unico, fn ($q) => $q->where('remate_id', $remate->id))
            ->when($remate && $unico, fn ($q) => $q->where(fn ($s) => $s->where('remate_id', $remate->id)->orWhere('alcance', Garantia::ALCANCE_UNICO)))
            ->get();
        $ahora = CarbonImmutable::now('UTC');
        $abiertas = $garantias->filter(fn (Garantia $g) => $g->esUnica()
            || in_array($g->remate->estadoVisible($ahora), [Remate::VISTA_EN_VIVO, Remate::VISTA_PROXIMO], true));
        $garantia = $abiertas->firstWhere('estado', Garantia::ESTADO_APROBADA)
            ?? $abiertas->firstWhere('estado', Garantia::ESTADO_EN_REVISION)
            ?? ($remate ? $garantias->first() : null);

        return [
            'sesion' => match ($garantia?->estado) {
                Garantia::ESTADO_APROBADA => 'aprobada',
                Garantia::ESTADO_EN_REVISION => 'en-revision',
                default => 'registrado',
            },
            'remate' => $garantia ? ['titulo' => $garantia->remate->titulo, 'slug' => $garantia->remate->slug, 'enVivo' => $garantia->remate->estadoVisible($ahora) === Remate::VISTA_EN_VIVO] : null,
            'garantia' => $garantia?->estado,
        ] + $datos;
    }
}
