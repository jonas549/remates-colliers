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

    public static function clp(?int $monto): string
    {
        return $monto === null ? '—' : '$' . number_format($monto, 0, ',', '.');
    }

    /** «Hoy, 12:00» o «09-09, 12:00» (tablas del panel). */
    public static function fechaCorta(?CarbonInterface $fecha, ?CarbonInterface $ahora = null): string
    {
        if ($fecha === null) {
            return 'Sin fecha';
        }
        $local = CarbonImmutable::instance($fecha)->setTimezone(self::ZONA);
        $hoy = CarbonImmutable::instance($ahora ?? CarbonImmutable::now())->setTimezone(self::ZONA);

        return ($local->isSameDay($hoy) ? 'Hoy' : $local->format('d-m')) . ', ' . $local->format('H:i');
    }

    /** «09-09-2026, 12:00» o «Hoy, 12:00» (sitio público). */
    public static function fecha(?CarbonInterface $fecha, ?CarbonInterface $ahora = null): string
    {
        if ($fecha === null) {
            return 'Sin fecha';
        }
        $local = CarbonImmutable::instance($fecha)->setTimezone(self::ZONA);
        $hoy = CarbonImmutable::instance($ahora ?? CarbonImmutable::now())->setTimezone(self::ZONA);

        return ($local->isSameDay($hoy) ? 'Hoy' : $local->format('d-m-Y')) . ', ' . $local->format('H:i');
    }

    public static function dia(?CarbonInterface $fecha): string
    {
        return $fecha === null ? '' : CarbonImmutable::instance($fecha)->setTimezone(self::ZONA)->format('d-m-Y');
    }

    /** «31 de agosto de 2026». */
    public static function fechaLarga(CarbonInterface $fecha): string
    {
        $local = CarbonImmutable::instance($fecha)->setTimezone(self::ZONA);

        return $local->day . ' de ' . self::MESES[$local->month - 1] . ' de ' . $local->year;
    }

    /** «Lunes 31 de agosto de 2026». */
    public static function fechaConDia(CarbonInterface $fecha): string
    {
        $local = CarbonImmutable::instance($fecha)->setTimezone(self::ZONA);

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
            $segundos < 60 => "hace {$segundos} seg",
            $segundos < 3600 => 'hace ' . intdiv($segundos, 60) . ' min',
            $local->isSameDay($ahora->setTimezone(self::ZONA)) => 'hace ' . intdiv($segundos, 3600) . ' h',
            $local->isSameDay($ahora->setTimezone(self::ZONA)->subDay()) => 'ayer, ' . $local->format('H:i'),
            default => $local->format('d-m, H:i'),
        };
    }

    public static function numero(int|float|string|null $n, int $decimales = 0): string
    {
        if ($n === null || $n === '') {
            return '';
        }
        $texto = number_format((float) $n, $decimales, ',', '.');

        return $decimales > 0 ? rtrim(rtrim($texto, '0'), ',') : $texto;
    }
}
