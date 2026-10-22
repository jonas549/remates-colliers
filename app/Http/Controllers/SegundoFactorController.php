<?php

namespace App\Http\Controllers;

use App\Autenticacion\SegundoFactor;
use App\Publico\Catalogo;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Laravel\Fortify\Contracts\LoginResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Segunda pantalla del ingreso al panel (22/10): el código que llegó por correo. Sin un ingreso pendiente (la clave
 * no se validó, o pasaron 30 minutos) vuelve al acceso de administración.
 */
class SegundoFactorController extends Controller
{
    public function __construct(private readonly SegundoFactor $segundoFactor) {}

    public function show(Request $request): View|RedirectResponse
    {
        $user = $this->segundoFactor->pendiente($request);
        if ($user === null) {
            return $this->volverAlAcceso($request);
        }

        return view('auth.codigo-ingreso', [
            'proximo' => Catalogo::destacado(),
            'correo' => SegundoFactor::correoEnmascarado($user),
            'minutos' => max(1, (int) \App\Models\Configuracion::valor('dos_pasos_minutos')),
            'diasRecordar' => SegundoFactor::diasRecordar(),
        ]);
    }

    public function verificar(Request $request, StatefulGuard $guard): Response
    {
        $user = $this->segundoFactor->pendiente($request);
        if ($user === null) {
            return $this->volverAlAcceso($request);
        }
        $codigo = preg_replace('/\D/', '', (string) $request->input('codigo'));

        if ($error = $this->segundoFactor->verificar($request, $user, $codigo)) {
            return redirect()->route('admin.ingresar.codigo')->withErrors(['codigo' => $error]);
        }

        $recordarSesion = $this->segundoFactor->recordarSesion($request);
        $this->segundoFactor->olvidarPendiente($request);
        $guard->login($user, $recordarSesion);
        $request->session()->regenerate();
        if ($request->boolean('recordar_dispositivo')) {
            $this->segundoFactor->recordarDispositivo($request, $user);
        }

        return app(LoginResponse::class)->toResponse($request);
    }

    public function reenviar(Request $request): RedirectResponse
    {
        $user = $this->segundoFactor->pendiente($request);
        if ($user === null) {
            return $this->volverAlAcceso($request);
        }
        $error = $this->segundoFactor->enviar($request, $user);

        return redirect()->route('admin.ingresar.codigo')->with($error ? 'error_envio' : 'reenviado', $error ?? true);
    }

    /** «Ingresar con otra cuenta»: abandona el ingreso pendiente. */
    public function cancelar(Request $request): RedirectResponse
    {
        $this->segundoFactor->olvidarPendiente($request);

        return redirect()->route('admin.ingresar');
    }

    private function volverAlAcceso(Request $request): RedirectResponse
    {
        $this->segundoFactor->olvidarPendiente($request);

        return redirect()->route('admin.ingresar')->withErrors(['usuario' => __('acceso.dos_pasos.sin_pendiente')]);
    }
}
