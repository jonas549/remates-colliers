<?php

namespace App\Http\Middleware;

use App\Support\Recaptcha;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * QA 21/10, 2.2: cabeceras de seguridad en toda respuesta de Laravel (páginas, errores, JSON y descargas). Los archivos
 * estáticos (JSON del tiempo real, build) los sirve LiteSpeed sin pasar por aquí.
 *
 * La CSP se arma con lo que el sitio usa de verdad:
 * - script: solo los propios. 'unsafe-eval' porque Alpine evalúa las expresiones de las vistas con `new Function`
 *   (quitarlo exige la versión CSP de Alpine y reescribir todas las expresiones). Sin 'unsafe-inline': no hay scripts
 *   ni manejadores en línea (los confirm() del panel se pasaron a Alpine). reCAPTCHA (Google), solo si está configurado.
 * - style: 'unsafe-inline' porque el diseño trae estilos en línea (Bloque T).
 * - img: propias, data:/blob: (vista previa de fotos) y los mosaicos de OpenStreetMap del mapa.
 * - frame: el reproductor de YouTube (la sala y la ficha usan youtube-nocookie.com) y el captcha.
 * - connect: solo el propio dominio (estado JSON, /hora, presencia.php, pujas).
 */
class CabecerasSeguridad
{
    public function handle(Request $request, Closure $next): Response
    {
        $respuesta = $next($request);
        $cabeceras = $respuesta->headers;

        $cabeceras->set('X-Content-Type-Options', 'nosniff');
        $cabeceras->set('X-Frame-Options', 'SAMEORIGIN');
        $cabeceras->set('Referrer-Policy', 'strict-origin-when-cross-origin'); // YouTube exige el origen para el embed
        // Sin cámara, micrófono, ubicación ni pagos. No se tocan autoplay, fullscreen ni encrypted-media: los usa el video.
        $cabeceras->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=(), usb=(), browsing-topics=()');

        $https = config('colliers.seguridad.forzar_https') && ForzarHttps::esSegura($request);
        if ($https && config('colliers.seguridad.hsts_segundos') > 0) {
            $cabeceras->set('Strict-Transport-Security', 'max-age=' . (int) config('colliers.seguridad.hsts_segundos'));
        }

        $modo = config('colliers.seguridad.csp');
        if (in_array($modo, ['activa', 'reporte'], true)) {
            $cabeceras->set($modo === 'activa' ? 'Content-Security-Policy' : 'Content-Security-Policy-Report-Only', self::politica($https));
        }

        return $respuesta;
    }

    public static function politica(bool $https = false): string
    {
        // reCAPTCHA v2 (22/10), solo con claves: el script y su desafío en un frame (www.google.com y www.gstatic.com).
        $captchaScript = Recaptcha::activo() ? ' ' . implode(' ', Recaptcha::CSP_SCRIPT) : '';
        $captchaFrame = Recaptcha::activo() ? ' ' . implode(' ', Recaptcha::CSP_FRAME) : '';
        $captchaConnect = Recaptcha::activo() ? ' ' . implode(' ', Recaptcha::CSP_CONNECT) : '';
        // `npm run dev` en local: el servidor de Vite sirve los módulos y la recarga en caliente por websocket.
        $vite = '';
        $viteWs = '';
        if (Vite::isRunningHot()) {
            $origen = rtrim(trim((string) @file_get_contents(public_path('hot'))), '/');
            $vite = ' ' . $origen;
            $viteWs = ' ' . preg_replace('/^http/', 'ws', $origen);
        }

        $directivas = [
            "default-src 'self'",
            "script-src 'self' 'unsafe-eval'{$captchaScript}{$vite}",
            "style-src 'self' 'unsafe-inline'{$vite}",
            "img-src 'self' data: blob: https://tile.openstreetmap.org",
            "font-src 'self' data:{$vite}",
            "connect-src 'self'{$captchaConnect}{$vite}{$viteWs}",
            "frame-src https://www.youtube-nocookie.com https://www.youtube.com{$captchaFrame}",
            "media-src 'self'",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'self'",
            // Lo bloqueado queda en el log de Laravel («CSP bloqueó un recurso»).
            'report-uri /csp-informe',
        ];
        if ($https) {
            $directivas[] = 'upgrade-insecure-requests';
        }

        return implode('; ', $directivas);
    }
}
