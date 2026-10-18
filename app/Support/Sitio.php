<?php

namespace App\Support;

use App\Models\Configuracion;
use Throwable;

/**
 * Datos del sitio que se editan en Administración → Configuración y aparecen en muchas vistas (contacto, UF, enlaces).
 * Si la base no responde (página de error, instalación) devuelve los valores por defecto en vez de fallar.
 */
class Sitio
{
    public static function valor(string $clave): mixed
    {
        try {
            return Configuracion::valor($clave);
        } catch (Throwable) {
            $base = Configuracion::DEFECTOS[$clave] ?? null;

            return $base['valor'] ?? null;
        }
    }

    public static function correo(): string
    {
        return (string) (self::valor('contacto_correo') ?: 'remates@colliers.cl');
    }

    public static function telefono(): string
    {
        return (string) self::valor('contacto_telefono');
    }

    /** «tel:+56227603535». */
    public static function telefonoEnlace(): string
    {
        return 'tel:' . preg_replace('/[^\d+]/', '', self::telefono());
    }

    /** Valor de la UF o null si no hay uno vigente (entonces no se muestran referencias en UF). */
    public static function uf(): ?float
    {
        $valor = self::valor('uf_valor');

        return is_numeric($valor) && (float) $valor > 0 ? (float) $valor : null;
    }

    /** «UF 4.694» o cadena vacía si no hay valor de UF. */
    public static function enUf(?int $pesos): string
    {
        $uf = self::uf();

        return $uf === null || $pesos === null ? '' : 'UF ' . number_format(round($pesos / $uf), 0, ',', '.');
    }

    /** Página del sitio corporativo en producción (config colliers.sitio_produccion), o null si no tiene una. */
    public static function produccion(string $pagina): ?string
    {
        $ruta = config("colliers.sitio_produccion.paginas.{$pagina}");

        return $ruta === null ? null : rtrim((string) config('colliers.sitio_produccion.url'), '/') . '/' . $ruta;
    }

    /** Red social que publica el sitio corporativo, o null si no tiene una (por ejemplo X). */
    public static function red(string $red): ?string
    {
        return config("colliers.sitio_produccion.redes.{$red}");
    }

    /**
     * Enlace legal: manda el de Configuración → Sitio cuando tiene valor; si está vacío, la página del sitio corporativo.
     * Las bases generales no tienen página allí: sin valor en el panel, no hay enlace.
     */
    public static function enlaceLegal(string $tipo): ?string
    {
        $clave = ['privacidad' => 'enlace_privacidad', 'terminos' => 'enlace_terminos', 'bases' => 'enlace_bases_generales'][$tipo];

        return self::valor($clave) ?: self::produccion($tipo);
    }

    public static function horasRevision(): int
    {
        return (int) (self::valor('garantias_revision_horas') ?: 24);
    }
}
