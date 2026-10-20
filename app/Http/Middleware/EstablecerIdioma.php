<?php

namespace App\Http\Middleware;

use App\Support\Idioma;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware GLOBAL: el idioma sale del prefijo de la URL (/en → inglés; sin prefijo → español). Es global, y no de ruta,
 * para que también las páginas de error (un 404 no tiene ruta) salgan en el idioma de la URL.
 * Quien entra por la portada en español y la última vez eligió inglés en el conmutador, va a /en.
 */
class EstablecerIdioma
{
    private static ?string $home = null;

    public function handle(Request $request, Closure $next): Response
    {
        $idioma = Idioma::dePath($request->path());
        App::setLocale($idioma);

        // Destino de Fortify que es una ruta fija en config/fortify.php, no un nombre de ruta.
        self::$home ??= (string) config('fortify.home');
        config(['fortify.home' => $idioma === Idioma::INGLES ? '/' . Idioma::PREFIJO . self::$home : self::$home]);

        if ($idioma === Idioma::ESPANOL && $request->isMethod('GET') && $request->path() === '/' && $request->cookie(Idioma::COOKIE) === Idioma::INGLES) {
            return redirect(Idioma::destino(Idioma::INGLES, '/' . ($request->getQueryString() ? '?' . $request->getQueryString() : '')));
        }

        return $next($request);
    }
}
