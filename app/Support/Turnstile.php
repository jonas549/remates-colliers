<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Captcha del registro (QA 21/10, 2.3): Cloudflare Turnstile. El registro sube documentos de identidad y dispara un
 * correo por cuenta: sin captcha queda abierto a registros automáticos y a envíos masivos desde el SMTP de Colliers.
 *
 * Sin las dos claves en el .env (TURNSTILE_SITE_KEY y TURNSTILE_SECRET_KEY) no se muestra ni se exige: así funcionan
 * el desarrollo local y las pruebas. Con las claves, la verificación es en el servidor y, si Cloudflare no responde,
 * el registro se rechaza (falla cerrado) y queda en el log.
 */
class Turnstile
{
    public const CAMPO = 'cf-turnstile-response';

    public const SCRIPT = 'https://challenges.cloudflare.com/turnstile/v0/api.js';

    private const VERIFICAR = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    public static function activo(): bool
    {
        return filled(config('services.turnstile.site_key')) && filled(config('services.turnstile.secret_key'));
    }

    public static function claveSitio(): string
    {
        return (string) config('services.turnstile.site_key');
    }

    public static function verificar(?string $token, ?string $ip): bool
    {
        if (! self::activo()) {
            return true;
        }
        if (blank($token) || strlen($token) > 2048) {
            return false;
        }

        try {
            $respuesta = Http::asForm()->timeout(8)->post(self::VERIFICAR, array_filter([
                'secret' => config('services.turnstile.secret_key'),
                'response' => $token,
                'remoteip' => $ip,
            ]));
            if ($respuesta->json('success') === true) {
                return true;
            }
            Log::notice('Turnstile rechazó un registro', ['codigos' => $respuesta->json('error-codes'), 'ip' => $ip]);
        } catch (Throwable $e) {
            Log::warning('Turnstile no respondió: el registro se rechaza', ['error' => $e->getMessage()]);
        }

        return false;
    }
}
