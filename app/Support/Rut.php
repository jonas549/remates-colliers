<?php

namespace App\Support;

use Illuminate\Support\Str;
use RuntimeException;

/**
 * RUT chileno: normalización, dígito verificador, formato e índice ciego.
 *
 * El RUT se guarda cifrado (cast `encrypted`). Para buscar y exigir unicidad sin descifrar se guarda
 * además un índice ciego: HMAC-SHA256 del RUT normalizado con una subclave derivada de APP_KEY.
 * Si APP_KEY cambia, tanto el cifrado como el índice quedan inservibles: APP_KEY se respalda.
 */
final class Rut
{
    /** "12.345.678-k" → "12345678K". Devuelve null si no tiene forma de RUT. */
    public static function normalizar(?string $rut): ?string
    {
        $limpio = strtoupper(preg_replace('/[^0-9kK]/', '', (string) $rut));

        if (! preg_match('/^(\d{1,8})([0-9K])$/', $limpio, $m)) {
            return null;
        }

        return ltrim($m[1], '0') . $m[2];
    }

    public static function digitoVerificador(string $cuerpo): string
    {
        $suma = 0;
        $factor = 2;
        for ($i = strlen($cuerpo) - 1; $i >= 0; $i--) {
            $suma += (int) $cuerpo[$i] * $factor;
            $factor = $factor === 7 ? 2 : $factor + 1;
        }
        $resto = 11 - ($suma % 11);

        return match ($resto) {
            11 => '0',
            10 => 'K',
            default => (string) $resto,
        };
    }

    public static function esValido(?string $rut): bool
    {
        $normal = self::normalizar($rut);
        if ($normal === null || strlen($normal) < 2) {
            return false;
        }

        return self::digitoVerificador(substr($normal, 0, -1)) === substr($normal, -1);
    }

    /** "12345678K" → "12.345.678-K". */
    public static function formatear(string $rut): string
    {
        $normal = self::normalizar($rut) ?? throw new RuntimeException('RUT con formato inválido.');

        return number_format((int) substr($normal, 0, -1), 0, '', '.') . '-' . substr($normal, -1);
    }

    /** Índice ciego: 64 caracteres hexadecimales, igual para cualquier forma de escribir el mismo RUT. */
    public static function indiceCiego(string $rut): string
    {
        $normal = self::normalizar($rut) ?? throw new RuntimeException('RUT con formato inválido.');

        return hash_hmac('sha256', $normal, self::subclave());
    }

    private static function subclave(): string
    {
        $clave = (string) config('app.key');
        if ($clave === '') {
            throw new RuntimeException('APP_KEY no está configurada.');
        }
        if (Str::startsWith($clave, 'base64:')) {
            $clave = base64_decode(substr($clave, 7));
        }

        return hash_hmac('sha256', 'colliers-indice-rut', $clave, true);
    }
}
