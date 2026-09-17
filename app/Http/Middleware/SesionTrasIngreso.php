<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Un ingreso nunca termina en silencio (17/09, sandbox: la clave pasaba y la pantalla volvía al login sin mensaje).
 *
 * Después de ingresar o registrarse, la redirección lleva `?ingreso=1`. En esa primera petición:
 * - con sesión: se quita la marca de la URL y se sigue normal;
 * - sin sesión: el navegador no presentó la sesión recién creada. Se vuelve al acceso con `?sesion=perdida`, que
 *   muestra el motivo, y se anota en el log lo necesario para diagnosticarlo (nombres de cookies, esquema, host),
 *   nunca valores de cookies.
 *
 * La marca va en la URL y no en una cookie porque lo que falla es justamente que una cookie no llegue.
 */
class SesionTrasIngreso
{
    public const MARCA = 'ingreso';

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->isMethod('GET') || ! $request->query->has(self::MARCA)) {
            return $next($request);
        }

        $limpia = $request->fullUrlWithoutQuery([self::MARCA]);
        if ($request->user() !== null) {
            return redirect()->to($limpia);
        }

        $cookieSesion = (string) config('session.cookie');
        Log::warning('Ingreso sin sesión: el navegador no presentó la sesión creada al ingresar', [
            'url' => $limpia,
            'cookies_recibidas' => array_keys($request->cookies->all()),
            'trae_cookie_de_sesion' => $request->hasCookie($cookieSesion),
            'https_detectado' => $request->isSecure(),
            'host' => $request->getHost(),
            'sesion' => [
                'cookie' => $cookieSesion,
                'secure' => config('session.secure'),
                'domain' => config('session.domain'),
                'same_site' => config('session.same_site'),
                'driver' => config('session.driver'),
            ],
            'ip' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 200),
        ]);

        $acceso = $request->is('admin', 'admin/*') ? 'admin.ingresar' : 'login';

        return redirect()->route($acceso, ['sesion' => 'perdida']);
    }

    /** Agrega la marca a la URL de destino de un ingreso. */
    public static function marcar(string $url): string
    {
        [$sinFragmento, $fragmento] = array_pad(explode('#', $url, 2), 2, null);

        return $sinFragmento . (str_contains($sinFragmento, '?') ? '&' : '?') . self::MARCA . '=1' . ($fragmento !== null ? "#{$fragmento}" : '');
    }
}
