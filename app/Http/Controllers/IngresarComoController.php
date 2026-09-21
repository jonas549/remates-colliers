<?php

namespace App\Http\Controllers;

use App\Publico\Catalogo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * «Ingresar como este postor» (postores de prueba, 21/09): abre el acceso con el correo ya escrito.
 *
 * El acceso normal redirige a quien ya tiene sesión, y quien prueba suele tener abierta la de administración: sin esta
 * pantalla, el enlace lo devolvía al panel sin decir nada. Aquí se le explica que el navegador guarda una sola sesión y
 * se le ofrecen las dos salidas: cerrar la actual y seguir, o abrir el enlace en una ventana privada.
 * No da acceso a nada: solo escribe el correo; la clave la pone la persona.
 */
class IngresarComoController extends Controller
{
    public function show(Request $request): View|RedirectResponse
    {
        $usuario = self::correo($request->query('usuario'));
        $actual = $request->user();

        if ($actual === null) {
            return redirect()->route('login', array_filter(['usuario' => $usuario]));
        }
        if ($usuario === '' || strcasecmp($actual->email, $usuario) === 0) {
            return redirect()->route($actual->esAdministracion() ? 'admin.dashboard' : 'cuenta.estado');
        }

        return view('auth.ingresar-como', [
            'actual' => $actual,
            'usuario' => $usuario,
            'enlace' => route('ingresar.como', ['usuario' => $usuario]),
            'proximo' => Catalogo::destacado(),
        ]);
    }

    /** Cierra la sesión actual y abre el acceso con el otro correo ya escrito. */
    public function cambiar(Request $request): RedirectResponse
    {
        $usuario = self::correo($request->input('usuario'));

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login', array_filter(['usuario' => $usuario]));
    }

    private static function correo(mixed $valor): string
    {
        return is_string($valor) && filter_var($valor, FILTER_VALIDATE_EMAIL) ? mb_strtolower($valor) : '';
    }
}
