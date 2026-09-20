<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cuando el envío pesa más que `post_max_size`, PHP descarta el cuerpo ENTERO: no llegan los campos, ni los archivos,
 * ni el token CSRF. El formulario volvía en blanco (o con un 419) sin decir por qué, y el postor tenía que adivinar.
 *
 * Este middleware lo detecta antes que el CSRF y devuelve al formulario con un mensaje que dice cuánto acepta el
 * servidor. Va prepuesto al grupo `web` para correr antes de VerifyCsrfToken.
 */
class SubidaDemasiadoGrande
{
    public function handle(Request $request, Closure $next): Response
    {
        // `colliers.limite_post` existe para poder probarlo y para cuando ini_get() no refleja el límite real del hosting.
        $limite = self::aBytes((string) (config('colliers.limite_post') ?: ini_get('post_max_size')));
        $enviado = (int) $request->server('CONTENT_LENGTH', 0);

        // PHP vacía $_POST y $_FILES al pasarse del límite: si venía contenido y no llegó nada, fue eso.
        $sePaso = $limite > 0 && $enviado > $limite && $request->isMethod('POST')
            && $request->request->count() === 0 && $request->files->count() === 0;

        if (! $sePaso) {
            return $next($request);
        }

        $mensaje = 'El envío pesa ' . self::enMb($enviado) . ' y este servidor acepta hasta ' . self::enMb($limite)
            . ' por formulario. Adjunta archivos más livianos (puedes reducir las fotos o guardar el PDF con menos calidad) e inténtalo de nuevo.';

        if ($request->expectsJson()) {
            return response()->json(['ok' => false, 'mensaje' => $mensaje], 413);
        }

        // Página propia y no un mensaje en sesión: este middleware corre antes de StartSession (el token tampoco llegó).
        return response()->view('errors.413', [
            'mensaje' => $mensaje,
            'volver' => $request->headers->get('referer') ?: url('/'),
        ], 413);
    }

    public static function aBytes(string $valor): int
    {
        $valor = trim($valor);
        $numero = (float) $valor;

        return (int) match (strtolower(substr($valor, -1))) {
            'g' => $numero * 1024 ** 3,
            'm' => $numero * 1024 ** 2,
            'k' => $numero * 1024,
            default => $numero,
        };
    }

    private static function enMb(int $bytes): string
    {
        return round($bytes / 1024 ** 2, 1) . ' MB';
    }
}
