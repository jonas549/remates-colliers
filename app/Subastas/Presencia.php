<?php

namespace App\Subastas;

use App\Models\Remate;
use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * Lado Laravel de la presencia en la sala (la lógica está en PresenciaSala, que no depende del framework).
 *
 * public/presencia.php no arranca Laravel, así que no lee el .env ni la configuración: lo que necesita (la clave para
 * verificar el token y las carpetas) se lo deja Laravel en un archivo de ajustes en storage, fuera de `public`. La clave
 * se deriva de APP_KEY: si APP_KEY cambia, el archivo se reescribe solo la próxima vez que alguien abra la sala.
 */
final class Presencia
{
    /** El token sirve mientras la sala está abierta; vence antes que la sesión más larga del panel (12 h). */
    private const VIGENCIA_TOKEN_HORAS = 12;

    public static function sala(): PresenciaSala
    {
        return new PresenciaSala(
            (string) config('colliers.presencia.carpeta'),
            (string) config('colliers.tiempo_real.carpeta'),
            self::ventanaMs(),
        );
    }

    public static function ventanaMs(): int
    {
        return (int) config('colliers.presencia.ventana_segundos') * 1000;
    }

    public static function latidoMs(): int
    {
        return (int) config('colliers.presencia.latido_segundos') * 1000;
    }

    public static function token(Remate $remate, User $user): string
    {
        self::publicarAjustes();

        return PresenciaSala::firmar([
            'r' => $remate->id,
            's' => $remate->slug,
            'u' => $user->id,
            'e' => (int) CarbonImmutable::now('UTC')->addHours(self::VIGENCIA_TOKEN_HORAS)->format('Uv'),
        ], self::clave());
    }

    /** Escribe los ajustes del script si faltan o cambiaron. Idempotente: también lo llama colliers:instalar. */
    public static function publicarAjustes(): void
    {
        $archivo = (string) config('colliers.presencia.ajustes');
        $contenido = json_encode([
            'clave' => self::clave(),
            'carpeta' => (string) config('colliers.presencia.carpeta'),
            'carpeta_tiempo_real' => (string) config('colliers.tiempo_real.carpeta'),
            'ventana_ms' => self::ventanaMs(),
        ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        if (@file_get_contents($archivo) === $contenido) {
            return;
        }
        if (! is_dir(dirname($archivo))) {
            @mkdir(dirname($archivo), 0775, true);
        }
        PresenciaSala::escribirAtomico($archivo, $contenido);
    }

    private static function clave(): string
    {
        return hash_hmac('sha256', 'colliers-presencia-sala', (string) config('app.key'));
    }
}
