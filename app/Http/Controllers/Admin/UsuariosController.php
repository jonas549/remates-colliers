<?php

namespace App\Http\Controllers\Admin;

use App\Autenticacion\Sesiones;
use App\Http\Controllers\Controller;
use App\Models\AccessLog;
use App\Models\Postor;
use App\Models\PostorDocumento;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Acciones de administración sobre cuentas (solo rol admin). Los botones van en la ficha del postor (Bloque G);
 * hasta entonces son endpoints.
 */
class UsuariosController extends Controller
{
    /**
     * Restablece la contraseña de otra cuenta: genera una clave temporal que se muestra UNA vez, obliga a cambiarla
     * al ingresar, levanta el bloqueo y cierra todas sus sesiones.
     */
    public function restablecerClave(Request $request, User $usuario): JsonResponse
    {
        abort_if($usuario->is($request->user()), 422, 'Para tu propia cuenta usa «Cambiar contraseña».');

        $temporal = Str::password(14, symbols: false);
        $usuario->forceFill(['password' => $temporal, 'debe_cambiar_clave' => true, 'intentos_fallidos' => 0, 'bloqueado_hasta' => null])->save();
        $cerradas = Sesiones::cerrarTodas($usuario);
        $this->anotar($request, $usuario, 'clave_restablecida_por_admin', ['sesiones_cerradas' => $cerradas]);

        return response()->json([
            'clave_temporal' => $temporal,
            'sesiones_cerradas' => $cerradas,
            'mensaje' => 'Entrega la clave temporal por un canal seguro. Se pedirá cambiarla al ingresar.',
        ]);
    }

    public function desbloquear(Request $request, User $usuario): JsonResponse
    {
        $usuario->forceFill(['intentos_fallidos' => 0, 'bloqueado_hasta' => null])->save();
        $this->anotar($request, $usuario, 'desbloqueo_por_admin', []);

        return response()->json(['desbloqueado' => true]);
    }

    public function cerrarSesiones(Request $request, User $usuario): JsonResponse
    {
        $cerradas = Sesiones::cerrarTodas($usuario, $usuario->is($request->user()) ? $request->session()->getId() : null);
        $this->anotar($request, $usuario, 'sesiones_cerradas_por_admin', ['cantidad' => $cerradas]);

        return response()->json(['sesiones_cerradas' => $cerradas]);
    }

    /** Documento de un postor: se busca DENTRO del postor de la ruta, nunca solo por su id. */
    public function documento(Postor $postor, int $documento): StreamedResponse
    {
        $modelo = PostorDocumento::where('postor_id', $postor->id)->findOrFail($documento);
        Gate::authorize('descargar', $modelo);

        return Storage::disk('local')->download($modelo->ruta, $modelo->nombre_original);
    }

    private function anotar(Request $request, User $afectado, string $evento, array $detalle): void
    {
        AccessLog::create([
            'user_id' => $afectado->id, 'email' => $afectado->email, 'evento' => $evento,
            'ip' => $request->ip(), 'user_agent' => mb_substr((string) $request->userAgent(), 0, 512),
            'detalle' => $detalle + ['por_user_id' => $request->user()->id],
        ]);
    }
}
