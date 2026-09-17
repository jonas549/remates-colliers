<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Response;

/**
 * Protege todo el sitio con una clave mientras el subdominio es un sandbox público.
 * Activo solo si COLLIERS_ACCESO_CLAVE tiene valor; en producción se deja vacío.
 *
 * La cookie guarda un hash de la clave (no la clave): cambiar la clave en el .env invalida todos los accesos.
 *
 * Nunca falla en silencio (17/09):
 * - Una sesión ya autenticada pasó por esta clave para ingresar: si la cookie falta, pasa y se le vuelve a emitir.
 * - Una petición JSON (puja, acciones del panel) recibe 403 con el motivo, no un redirect que `fetch` seguiría
 *   hasta un HTML 200 y el panel mostraría como éxito.
 * - Quien venía navegando y pierde la cookie ve en /acceso por qué se le pide la clave, y vuelve a donde estaba.
 */
class AccesoSandbox
{
    public const COOKIE = 'colliers_acceso';

    public const MENSAJE_VENCIDO = 'Tu acceso a este sitio de prueba venció o no se encontró en este navegador. Ingresa la clave de acceso para continuar.';

    public function handle(Request $request, Closure $next): Response
    {
        $clave = config('colliers.acceso.clave');
        if (blank($clave)) {
            return $next($request);
        }

        $libre = $request->routeIs('acceso.*') || $request->is('up');
        $conCookie = hash_equals(self::huella($clave), (string) $request->cookie(self::COOKIE));
        // Solo se consulta la sesión si falta la cookie: el camino normal (y el de la puja) no agrega trabajo.
        $conSesion = ! $conCookie && ! $libre && $request->user() !== null;

        if (! $libre && ! $conCookie && ! $conSesion) {
            return $this->pedirClave($request);
        }

        $respuesta = $next($request);
        // Mientras el sandbox está protegido, que no lo indexen los buscadores.
        $respuesta->headers->set('X-Robots-Tag', 'noindex, nofollow');
        if ($conSesion) {
            $respuesta->headers->setCookie(self::cookie($clave));
        }

        return $respuesta;
    }

    public static function huella(string $clave): string
    {
        return hash_hmac('sha256', $clave, (string) config('app.key'));
    }

    public static function cookie(string $clave): Cookie
    {
        $minutos = max(1, (int) config('colliers.acceso.dias')) * 24 * 60;

        return cookie(self::COOKIE, self::huella($clave), $minutos, httpOnly: true, sameSite: 'lax');
    }

    private function pedirClave(Request $request): Response
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => self::MENSAJE_VENCIDO, 'mensaje' => self::MENSAJE_VENCIDO, 'motivo' => 'acceso_sandbox'], 403);
        }

        // Al ingresar la clave vuelve a la página pedida; si era un envío de formulario, a la página del formulario.
        $destino = $request->isMethod('GET') ? $request->fullUrl() : url()->previous();
        if (str_starts_with($destino, $request->getSchemeAndHttpHost())) {
            $request->session()->put('url.intended', $destino);
        }

        $redirect = redirect()->route('acceso.formulario');
        // Primera visita: solo el formulario. Venía navegando (tiene sesión): se explica por qué se pide otra vez.
        if ($request->hasCookie((string) config('session.cookie'))) {
            $redirect->with('acceso_aviso', self::MENSAJE_VENCIDO);
        }

        return $redirect;
    }
}
