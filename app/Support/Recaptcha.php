<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Captcha del registro: Google reCAPTCHA v2, la casilla «No soy un robot» (22/10, reemplaza a Turnstile). El registro
 * sube documentos de identidad y dispara un correo por cuenta: sin captcha queda abierto a registros automáticos y a
 * envíos masivos desde el SMTP de Colliers.
 *
 * Sin las dos claves en el .env (RECAPTCHA_SITE_KEY y RECAPTCHA_SECRET_KEY) no se muestra ni se exige: así funcionan
 * el desarrollo local y las pruebas. Con las claves, la verificación es en el servidor, antes de validar el resto del
 * formulario, y si Google no responde el registro se rechaza (falla cerrado) y queda en el log.
 */
class Recaptcha
{
    public const CAMPO = 'g-recaptcha-response';

    public const SCRIPT = 'https://www.google.com/recaptcha/api.js';

    private const VERIFICAR = 'https://www.google.com/recaptcha/api/siteverify';

    /** Lo que la CSP tiene que abrir cuando está activo (CabecerasSeguridad). */
    public const CSP_SCRIPT = ['https://www.google.com/recaptcha/', 'https://www.gstatic.com/recaptcha/'];

    public const CSP_FRAME = ['https://www.google.com/recaptcha/', 'https://recaptcha.google.com/recaptcha/'];

    /** El script hace un fetch desde la página a www.google.com/recaptcha/api2/clr (visto en Chrome con seguridad.mjs). */
    public const CSP_CONNECT = ['https://www.google.com/recaptcha/'];

    public static function activo(): bool
    {
        return filled(config('services.recaptcha.site_key')) && filled(config('services.recaptcha.secret_key'));
    }

    public static function claveSitio(): string
    {
        return (string) config('services.recaptcha.site_key');
    }

    /** Script del widget en el idioma de la página (hl=es / hl=en). */
    public static function script(): string
    {
        return self::SCRIPT . '?hl=' . app()->getLocale();
    }

    public static function verificar(?string $token, ?string $ip): bool
    {
        if (! self::activo()) {
            return true;
        }
        if (blank($token) || strlen($token) > 4096) {
            return false;
        }

        try {
            $respuesta = Http::asForm()->timeout(8)->post(self::VERIFICAR, array_filter([
                'secret' => config('services.recaptcha.secret_key'),
                'response' => $token,
                'remoteip' => $ip,
            ]));
            if ($respuesta->json('success') === true) {
                return true;
            }
            Log::notice('reCAPTCHA rechazó un registro', ['codigos' => $respuesta->json('error-codes'), 'ip' => $ip]);
        } catch (Throwable $e) {
            Log::warning('reCAPTCHA no respondió: el registro se rechaza', ['error' => $e->getMessage()]);
        }

        return false;
    }
}
