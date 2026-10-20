<?php

namespace App\Http\Controllers;

use App\Support\Idioma;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Conmutador EN / ES: recuerda la elección (cookie de un año) y lleva a la misma página en el otro idioma. */
class IdiomaController extends Controller
{
    public function __invoke(Request $request, string $idioma): RedirectResponse
    {
        return redirect(Idioma::destino($idioma, (string) $request->query('a', '/')))
            ->withCookie(cookie()->forever(Idioma::COOKIE, $idioma, httpOnly: true, sameSite: 'lax'));
    }
}
