<?php

namespace App\Http\Controllers;

use App\Publico\Catalogo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * «Ingresar como este postor / martillero» (subastas de prueba, 21/09): abre el acceso con el correo ya escrito.
 *
 * El acceso normal redirige a quien ya tiene sesión, y quien prueba suele tener abierta la de administración: sin esta
 * pantalla, el enlace lo devolvía al panel sin decir nada. Aquí se le explica que el navegador guarda una sola sesión y
 * se le ofrecen las dos salidas: cerrar la actual y seguir, o abrir el enlace en una ventana privada.
 * No da acceso a nada: solo escribe el correo; la clave la pone la persona. `portal=admin` usa el acceso de
 * administración (martilleros); el acceso a usar viene en el enlace, no se deduce del correo (no revela quién es quién).
 */
class IngresarComoController extends Controller
{
    public function show(Request $request): View|RedirectResponse
    {
        $usuario = self::correo($request->query('usuario'));
        $portal = self::portal($request->query('portal'));
        $actual = $request->user();

        if ($actual === null) {
            return redirect()->route($portal, array_filter(['usuario' => $usuario]));
        }
        if ($usuario === '' || strcasecmp($actual->email, $usuario) === 0) {
            return redirect()->route($actual->esAdministracion() ? 'admin.dashboard' : 'cuenta.estado');
        }

        return view('auth.ingresar-como', [
            'actual' => $actual,
            'usuario' => $usuario,
            'portal' => $portal === 'admin.ingresar' ? 'admin' : null,
            'enlace' => route('ingresar.como', array_filter(['usuario' => $usuario, 'portal' => $portal === 'admin.ingresar' ? 'admin' : null])),
            'proximo' => Catalogo::destacado(),
        ]);
    }

    /** Cierra la sesión actual y abre el acceso con el otro correo ya escrito. */
    public function cambiar(Request $request): RedirectResponse
    {
        $usuario = self::correo($request->input('usuario'));
        $portal = self::portal($request->input('portal'));

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route($portal, array_filter(['usuario' => $usuario]));
    }

    private static function correo(mixed $valor): string
    {
        return is_string($valor) && filter_var($valor, FILTER_VALIDATE_EMAIL) ? mb_strtolower($valor) : '';
    }

    private static function portal(mixed $valor): string
    {
        return $valor === 'admin' ? 'admin.ingresar' : 'login';
    }
}
