<?php

namespace App\Http\Controllers;

use App\Autenticacion\Sesiones;
use App\Demo\EstadoCuentaDemo;
use App\Demo\RematesDemo;
use App\Models\AccessLog;
use App\Models\Garantia;
use App\Models\Postor;
use App\Models\PostorDocumento;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Área del usuario autenticado: estado de cuenta, cambio de contraseña, sesiones activas y documentos propios. */
class CuentaController extends Controller
{
    /**
     * Estado de cuenta. La variante sale de la cuenta y la garantía reales; los textos detallados (remate y monto)
     * siguen siendo los del prototipo hasta los Bloques G y H. En local, ?estado= permite revisar cada variante.
     */
    public function estado(Request $request): View
    {
        $clave = app()->isLocal() && $request->filled('estado') ? $request->string('estado')->toString() : $this->varianteDe($request->user());

        return view('cuenta.estado', ['estado' => EstadoCuentaDemo::para($clave)]);
    }

    public function clave(Request $request): View
    {
        return view('auth.cambiar-clave', [
            'proximo' => RematesDemo::proximoDestacado(),
            'obligatorio' => (bool) $request->user()->debe_cambiar_clave,
        ]);
    }

    public function sesiones(Request $request): View
    {
        return view('cuenta.sesiones', [
            'proximo' => RematesDemo::proximoDestacado(),
            'sesiones' => Sesiones::de($request->user(), $request->session()->getId()),
            'disponible' => Sesiones::disponible(),
        ]);
    }

    public function cerrarSesion(Request $request, string $sesion): RedirectResponse
    {
        abort_if($sesion === $request->session()->getId(), 422, 'Para cerrar la sesión actual usa «Cerrar sesión».');
        abort_unless(Sesiones::cerrarUna($request->user(), $sesion), 404);
        $this->anotar($request, 'sesion_cerrada_remota', ['cantidad' => 1]);

        return back()->with('estado', 'Cerramos esa sesión.');
    }

    public function cerrarOtrasSesiones(Request $request): RedirectResponse
    {
        $cantidad = Sesiones::cerrarTodas($request->user(), $request->session()->getId());
        $this->anotar($request, 'sesion_cerrada_remota', ['cantidad' => $cantidad]);

        return back()->with('estado', $cantidad === 1 ? 'Cerramos 1 sesión.' : "Cerramos {$cantidad} sesiones.");
    }

    public function documento(Request $request, PostorDocumento $documento): StreamedResponse
    {
        Gate::authorize('descargar', $documento);

        return Storage::disk('local')->download($documento->ruta, $documento->nombre_original);
    }

    private function varianteDe(User $user): string
    {
        $postor = $user->postor;
        if ($postor === null || $postor->estado !== Postor::ESTADO_APROBADO) {
            return 'cuenta-revision';
        }

        $garantia = Garantia::where('user_id', $user->id)->orderByDesc('id')->first();

        return match ($garantia?->estado) {
            Garantia::ESTADO_APROBADA => 'aprobada',
            Garantia::ESTADO_EN_REVISION => 'garantia-revision',
            Garantia::ESTADO_RECHAZADA => 'rechazada',
            default => 'garantia-pendiente',
        };
    }

    private function anotar(Request $request, string $evento, array $detalle): void
    {
        AccessLog::create([
            'user_id' => $request->user()->id, 'email' => $request->user()->email, 'evento' => $evento,
            'ip' => $request->ip(), 'user_agent' => mb_substr((string) $request->userAgent(), 0, 512), 'detalle' => $detalle,
        ]);
    }
}
