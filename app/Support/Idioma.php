<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

/**
 * Idioma del sitio público (20/10): español sin prefijo, inglés bajo /en. El panel queda siempre en español.
 * Las rutas públicas se registran dos veces (routes/web.php); las de inglés llevan el nombre con «en.» delante y
 * UrlLocalizada elige la del idioma actual, así que route('remates.show') en una página en inglés ya da /en/remates/….
 */
class Idioma
{
    public const ESPANOL = 'es';

    public const INGLES = 'en';

    public const PREFIJO = 'en';

    /** Cookie con la última elección del conmutador. Solo se usa al entrar por la portada (/). */
    public const COOKIE = 'idioma';

    public const TODOS = [self::ESPANOL, self::INGLES];

    public static function actual(): string
    {
        return App::currentLocale() === self::INGLES ? self::INGLES : self::ESPANOL;
    }

    public static function esIngles(): bool
    {
        return self::actual() === self::INGLES;
    }

    /** Idioma que corresponde a una ruta: /en y /en/… son inglés; el resto, español (incluido el panel). */
    public static function dePath(string $path): string
    {
        $path = trim($path, '/');

        return $path === self::PREFIJO || str_starts_with($path, self::PREFIJO . '/') ? self::INGLES : self::ESPANOL;
    }

    /** Path sin el prefijo de idioma: «en/remates/x» → «remates/x»; «en» → «». */
    public static function sinPrefijo(string $path): string
    {
        $path = trim($path, '/');
        if ($path === self::PREFIJO) {
            return '';
        }

        return str_starts_with($path, self::PREFIJO . '/') ? substr($path, strlen(self::PREFIJO) + 1) : $path;
    }

    /** La misma página (con su consulta) en otro idioma. Las rutas públicas son idénticas en los dos idiomas. */
    public static function urlEn(string $idioma, ?Request $request = null): string
    {
        $request ??= request();
        $path = self::sinPrefijo($request->path());
        $destino = $idioma === self::INGLES ? trim(self::PREFIJO . '/' . $path, '/') : $path;
        $consulta = $request->getQueryString();

        return url('/' . $destino) . ($consulta ? '?' . $consulta : '');
    }

    /** Enlace del conmutador: guarda la elección y lleva a la misma página en el otro idioma. */
    public static function urlConmutador(string $idioma, ?Request $request = null): string
    {
        $request ??= request();
        $path = '/' . self::sinPrefijo($request->path());
        $consulta = $request->getQueryString();

        return route('idioma.cambiar', ['idioma' => $idioma, 'a' => $path . ($consulta ? '?' . $consulta : '')]);
    }

    /** Archivos de idioma con textos para el JavaScript del sitio público (clave «js» de cada uno). */
    public const GRUPOS_JS = ['sitio', 'listado', 'remate', 'sala', 'cuenta', 'acceso', 'errores', 'catalogo', 'formato'];

    /** La página actual es del sitio público (tiene versión en los dos idiomas): no el panel ni un error sin ruta. */
    public static function esPaginaPublica(?Request $request = null): bool
    {
        $request ??= request();
        $nombre = (string) $request->route()?->getName();
        if ($nombre === '') {
            return false;
        }
        $base = str_starts_with($nombre, self::PREFIJO . '.') ? substr($nombre, strlen(self::PREFIJO) + 1) : $nombre;

        return app('router')->getRoutes()->hasNamedRoute(self::PREFIJO . '.' . $base);
    }

    /** hreflang de la página actual: [idioma => URL]. Vacío fuera del sitio público. */
    public static function alternativas(?Request $request = null): array
    {
        $request ??= request();
        if (! $request->isMethod('GET') || ! self::esPaginaPublica($request)) {
            return [];
        }

        return [self::ESPANOL => self::urlEn(self::ESPANOL, $request), self::INGLES => self::urlEn(self::INGLES, $request)];
    }

    /** Textos del JavaScript público en el idioma actual. El panel no los usa. */
    public static function textosJs(): array
    {
        $textos = [];
        foreach (self::GRUPOS_JS as $grupo) {
            $js = trans($grupo . '.js');
            if (is_array($js)) {
                $textos[$grupo] = $js;
            }
        }

        return $textos;
    }

    /** Destino interno seguro del conmutador (nunca otro sitio). */
    public static function destino(string $idioma, string $a): string
    {
        $a = '/' . ltrim($a, '/');
        if (str_starts_with($a, '//') || str_contains($a, '\\') || preg_match('#[\r\n]#', $a)) {
            $a = '/';
        }
        [$path, $consulta] = array_pad(explode('?', $a, 2), 2, null);
        $path = self::sinPrefijo($path);
        $destino = $idioma === self::INGLES ? trim(self::PREFIJO . '/' . $path, '/') : $path;

        return url('/' . $destino) . ($consulta ? '?' . $consulta : '');
    }
}
