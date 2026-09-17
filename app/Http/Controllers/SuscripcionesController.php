<?php

namespace App\Http\Controllers;

use App\Models\Remate;
use App\Models\Suscripcion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * «Avísame» del sitio público (Bloque M): remates nuevos (listado) o recordatorio de un remate (detalle). Sin doble
 * confirmación (supuesto): cada correo trae el enlace de baja. Responde JSON al formulario del sitio y redirección sin JS.
 */
class SuscripcionesController extends Controller
{
    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $datos = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'remate' => ['nullable', 'string', 'max:120'],
        ], [], ['email' => 'correo electrónico']);

        $remate = filled($datos['remate'] ?? null) ? Remate::where('slug', $datos['remate'])->whereIn('estado', [Remate::ESTADO_PUBLICADO, Remate::ESTADO_EN_CURSO])->first() : null;
        abort_if(filled($datos['remate'] ?? null) && $remate === null, 404);

        $email = mb_strtolower(trim($datos['email']));
        $suscripcion = Suscripcion::where('email', $email)->where('remate_id', $remate?->id)->first()
            ?? new Suscripcion(['email' => $email, 'remate_id' => $remate?->id, 'token' => Str::random(40)]);
        $suscripcion->fill(['baja_en' => null, 'ip' => $request->ip(), 'user_id' => $request->user()?->id ?? $suscripcion->user_id])->save();

        $mensaje = $remate
            ? 'Listo: te avisaremos por correo antes de que comience este remate.'
            : 'Listo: te avisaremos por correo cuando se publique un remate nuevo.';

        return $request->expectsJson() ? response()->json(['ok' => true, 'mensaje' => $mensaje]) : back()->with('suscripcion', $mensaje);
    }

    public function baja(string $token): View
    {
        $suscripcion = Suscripcion::where('token', $token)->firstOrFail();
        $suscripcion->update(['baja_en' => $suscripcion->baja_en ?? now('UTC')]);

        return view('suscripciones.baja', ['suscripcion' => $suscripcion->load('remate')]);
    }
}
