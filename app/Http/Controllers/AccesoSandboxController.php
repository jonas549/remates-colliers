<?php

namespace App\Http\Controllers;

use App\Http\Middleware\AccesoSandbox;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class AccesoSandboxController extends Controller
{
    public function formulario()
    {
        return view('acceso');
    }

    public function ingresar(Request $request)
    {
        $clave = (string) config('colliers.acceso.clave');
        if ($clave === '') {
            return redirect('/');
        }

        $llave = 'acceso-sandbox:' . $request->ip();
        if (RateLimiter::tooManyAttempts($llave, 10)) {
            $segundos = RateLimiter::availableIn($llave);

            return back()->withErrors(['clave' => "Demasiados intentos. Vuelve a intentar en {$segundos} segundos."]);
        }

        $request->validate(['clave' => ['required', 'string']], ['clave.required' => 'Ingresa la clave de acceso.']);

        if (! hash_equals($clave, (string) $request->input('clave'))) {
            RateLimiter::hit($llave, 60);

            return back()->withErrors(['clave' => 'La clave no es correcta.']);
        }

        RateLimiter::clear($llave);
        $minutos = max(1, (int) config('colliers.acceso.dias')) * 24 * 60;

        return redirect()->intended('/')
            ->withCookie(cookie(AccesoSandbox::COOKIE, AccesoSandbox::huella($clave), $minutos, httpOnly: true, sameSite: 'lax'));
    }
}
