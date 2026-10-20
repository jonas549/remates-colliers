<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/** Formatos de pantalla con los mismos patrones del diseño. Las fechas se muestran en America/Santiago. */
class Formato
{
    public const ZONA = 'America/Santiago';

    public const MESES = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];

    public const DIAS = ['domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado'];

    /*
     * Sitio en inglés (20/10): cada formato tiene su versión en inglés (miles con coma, mes en letras para que el día y
     * el mes no se confundan, 24 h como en español) y la hora lleva «(Santiago time)». En español no cambia nada: el
     * panel y los correos corren siempre en español.
     */
    private static function ingles(): bool
    {
        return Idioma::esIngles();
    }

    /** « (Santiago time)» en inglés; nada en español. */
    public static function zona(): string
    {
        return (string) __('formato.zona');
    }

    public static function clp(?int $monto): string
    {
        if ($monto === null) {
            return '—';
        }

        return '$' . (self::ingles() ? number_format($monto, 0, '.', ',') : number_format($monto, 0, ',', '.'));
    }

    /** «Hoy, 12:00» o «09-09, 12:00» (tablas del panel). En inglés: «Today, 12:00» o «9 Sep, 12:00 (Santiago time)». */
    public static function fechaCorta(?CarbonInterface $fecha, ?CarbonInterface $ahora = null): string
    {
        if ($fecha === null) {
            return __('formato.sin_fecha');
        }
        $local = CarbonImmutable::instance($fecha)->setTimezone(self::ZONA);
        $hoy = CarbonImmutable::instance($ahora ?? CarbonImmutable::now())->setTimezone(self::ZONA);

        return ($local->isSameDay($hoy) ? __('formato.hoy') : $local->format(self::ingles() ? 'j M' : 'd-m')) . ', ' . $local->format('H:i') . self::zona();
    }

    /** «09-09-2026, 12:00» o «Hoy, 12:00» (sitio público). En inglés: «9 Sep 2026, 12:00 (Santiago time)». */
    public static function fecha(?CarbonInterface $fecha, ?CarbonInterface $ahora = null): string
    {
        if ($fecha === null) {
            return __('formato.sin_fecha');
        }
        $local = CarbonImmutable::instance($fecha)->setTimezone(self::ZONA);
        $hoy = CarbonImmutable::instance($ahora ?? CarbonImmutable::now())->setTimezone(self::ZONA);

        return ($local->isSameDay($hoy) ? __('formato.hoy') : $local->format(self::ingles() ? 'j M Y' : 'd-m-Y')) . ', ' . $local->format('H:i') . self::zona();
    }

    /** «09-09-2026»; en inglés «9 Sep 2026». */
    public static function dia(?CarbonInterface $fecha): string
    {
        return $fecha === null ? '' : CarbonImmutable::instance($fecha)->setTimezone(self::ZONA)->format(self::ingles() ? 'j M Y' : 'd-m-Y');
    }

    /** «09-09, 12:00»; en inglés «9 Sep, 12:00 (Santiago time)». */
    public static function diaHora(CarbonInterface $fecha): string
    {
        $local = CarbonImmutable::instance($fecha)->setTimezone(self::ZONA);

        return $local->format(self::ingles() ? 'j M, H:i' : 'd-m, H:i') . self::zona();
    }

    /** «31 de agosto de 2026»; en inglés «31 August 2026». */
    public static function fechaLarga(CarbonInterface $fecha): string
    {
        $local = CarbonImmutable::instance($fecha)->setTimezone(self::ZONA);
        if (self::ingles()) {
            return $local->format('j F Y');
        }

        return $local->day . ' de ' . self::MESES[$local->month - 1] . ' de ' . $local->year;
    }

    /** «31 de agosto» (sin el año); en inglés «31 August». */
    public static function diaMes(CarbonInterface $fecha): string
    {
        $local = CarbonImmutable::instance($fecha)->setTimezone(self::ZONA);

        return self::ingles() ? $local->format('j F') : $local->day . ' de ' . self::MESES[$local->month - 1];
    }

    /** «Lunes 31 de agosto de 2026»; en inglés «Monday, 31 August 2026». */
    public static function fechaConDia(CarbonInterface $fecha): string
    {
        $local = CarbonImmutable::instance($fecha)->setTimezone(self::ZONA);
        if (self::ingles()) {
            return $local->format('l, j F Y');
        }

        return ucfirst(self::DIAS[$local->dayOfWeek]) . ' ' . self::fechaLarga($local);
    }

    /** «hace 34 seg», «hace 22 min», «hace 2 h», «ayer, 17:40», «12-08, 12:30». */
    public static function hace(?CarbonInterface $fecha, ?CarbonInterface $ahora = null): string
    {
        if ($fecha === null) {
            return '';
        }
        $ahora = CarbonImmutable::instance($ahora ?? CarbonImmutable::now());
        $segundos = max(0, $ahora->getTimestamp() - $fecha->getTimestamp());
        $local = CarbonImmutable::instance($fecha)->setTimezone(self::ZONA);

        return match (true) {
            $segundos < 60 => __('formato.hace_seg', ['n' => $segundos]),
            $segundos < 3600 => __('formato.hace_min', ['n' => intdiv($segundos, 60)]),
            $local->isSameDay($ahora->setTimezone(self::ZONA)) => __('formato.hace_h', ['n' => intdiv($segundos, 3600)]),
            $local->isSameDay($ahora->setTimezone(self::ZONA)->subDay()) => __('formato.ayer') . ', ' . $local->format('H:i'),
            default => $local->format(self::ingles() ? 'j M, H:i' : 'd-m, H:i'),
        };
    }

    /**
     * Número para un campo de formulario (punto decimal, sin ceros de sobra): «10.00» → «10», «82.50» → «82.5».
     * Solo quita ceros DESPUÉS del punto: con rtrim a secas, «10» quedaba en «1» (21/09).
     */
    public static function campo(int|float|string|null $n): string
    {
        if ($n === null || $n === '') {
            return '';
        }
        $texto = (string) $n;

        return str_contains($texto, '.') ? rtrim(rtrim($texto, '0'), '.') : $texto;
    }

    public static function numero(int|float|string|null $n, int $decimales = 0): string
    {
        if ($n === null || $n === '') {
            return '';
        }
        [$decimal, $miles] = self::ingles() ? ['.', ','] : [',', '.'];
        $texto = number_format((float) $n, $decimales, $decimal, $miles);

        return $decimales > 0 ? rtrim(rtrim($texto, '0'), $decimal) : $texto;
    }
}
