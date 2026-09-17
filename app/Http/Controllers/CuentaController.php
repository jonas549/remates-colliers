<?php

namespace App\Http\Controllers;

use App\Autenticacion\Sesiones;
use App\Demo\EstadoCuentaDemo;
use App\Publico\Catalogo;
use App\Models\AccessLog;
use App\Models\Garantia;
use App\Models\Postor;
use App\Models\PostorDocumento;
use App\Models\Remate;
use App\Models\User;
use App\Postores\EstadoCuenta;
use App\Postores\InscripcionGarantias;
use App\Support\Formato;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Área del usuario autenticado: estado de cuenta, cambio de contraseña, sesiones activas y documentos propios. */
class CuentaController extends Controller
{
    /**
     * Estado de cuenta con la cuenta y la garantía reales (Bloques G y H). ?remate=slug muestra esa inscripción.
     * Solo en local, ?estado= muestra las variantes del prototipo con sus datos fijos (comparación visual 1:1).
     */
    public function estado(Request $request): View
    {
        if (app()->isLocal() && $request->filled('estado')) {
            return view('cuenta.estado', EstadoCuentaDemo::vista($request->string('estado')->toString()));
        }

        return view('cuenta.estado', EstadoCuenta::para($request->user(), $request->query('remate')));
    }

    /** «Inscribirme» en un remate: nace la garantía pendiente y el postor ve cómo constituirla. */
    public function inscribirme(Request $request, Remate $remate, InscripcionGarantias $inscripcion): RedirectResponse
    {
        try {
            $garantia = $inscripcion->inscribir($request->user(), $remate);
        } catch (DomainException $e) {
            return redirect()->route('cuenta.estado')->with('error', $e->getMessage());
        }

        return redirect()->route('cuenta.estado', ['remate' => $remate->slug])
            ->with('estado', 'Quedaste inscrito en ' . $remate->folio . '. La garantía es de ' . Formato::clp($garantia->monto) . ': constitúyela y sube el comprobante.');
    }

    public function subirComprobante(Request $request, Garantia $garantia, InscripcionGarantias $inscripcion): RedirectResponse
    {
        abort_unless($garantia->user_id === $request->user()->id, 404);
        $datos = $request->validate([
            'medio' => ['required', Rule::in([Garantia::MEDIO_TRANSFERENCIA, Garantia::MEDIO_VALE_VISTA])],
            'comprobante' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ], ['comprobante.max' => 'El comprobante puede pesar hasta 10 MB.'], ['medio' => 'medio', 'comprobante' => 'comprobante']);

        try {
            $inscripcion->subirComprobante($garantia, $request->file('comprobante'), $datos['medio']);
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('cuenta.estado', ['remate' => $garantia->remate->slug])
            ->with('estado', 'Recibimos tu comprobante. Colliers lo revisará y te avisará por correo.');
    }

    public function comprobante(Request $request, Garantia $garantia): StreamedResponse
    {
        abort_unless($garantia->user_id === $request->user()->id && filled($garantia->comprobante_ruta), 404);

        return Storage::disk('local')->download($garantia->comprobante_ruta, $garantia->comprobante_nombre ?: 'comprobante');
    }

    public function clave(Request $request): View
    {
        return view('auth.cambiar-clave', [
            'proximo' => Catalogo::destacado(),
            'obligatorio' => (bool) $request->user()->debe_cambiar_clave,
        ]);
    }

    public function sesiones(Request $request): View
    {
        return view('cuenta.sesiones', [
            'proximo' => Catalogo::destacado(),
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

    private function anotar(Request $request, string $evento, array $detalle): void
    {
        AccessLog::create([
            'user_id' => $request->user()->id, 'email' => $request->user()->email, 'evento' => $evento,
            'ip' => $request->ip(), 'user_agent' => mb_substr((string) $request->userAgent(), 0, 512), 'detalle' => $detalle,
        ]);
    }
}
