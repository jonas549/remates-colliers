<?php

namespace App\Subastas;

use App\Support\Formato;
use RuntimeException;

/**
 * Puja rechazada por el motor. El `motivo` es el contrato de la API (lo usan las pruebas y la bitácora de intentos);
 * el MENSAJE es lo que lee el postor y dice exactamente qué pasó (17/09, OBS-7 del QA en el sandbox: pujar después del
 * cierre respondía «Este remate no está disponible para pujar» en vez de decir que el lote ya había cerrado).
 */
class PujaRechazada extends RuntimeException
{
    /** Motivos con texto propio en lang/{idioma}/sala.php (rechazo.<motivo>). El texto se resuelve solo al rechazar. */
    public const MOTIVOS = [
        'monto_invalido', 'lote_inexistente', 'remate_no_disponible', 'remate_cancelado', 'remate_no_publicado', 'lote_no_abierto',
        'lote_cerrado', 'cuenta_no_habilitada', 'sin_garantia', 'ya_vas_ganando', 'monto_insuficiente',
    ];

    public function __construct(public readonly string $motivo, public readonly array $detalle = [])
    {
        parent::__construct(self::mensaje($motivo, $detalle));
    }

    /** @param array<string, mixed> $detalle */
    public static function mensaje(string $motivo, array $detalle = []): string
    {
        $hora = fn (?string $fecha) => $fecha === null ? null
            : \Carbon\CarbonImmutable::parse($fecha, 'UTC')->setTimezone(Formato::ZONA)->format('H:i:s');

        return match ($motivo) {
            'lote_cerrado' => ($hora($detalle['cierra_en'] ?? null) === null
                ? __('sala.rechazo.lote_cerrado_sin_hora')
                : __('sala.rechazo.lote_cerrado_a_las', ['hora' => $hora($detalle['cierra_en'])]))
                . (($detalle['estado_lote'] ?? null) === 'adjudicado' ? __('sala.rechazo.ya_adjudicado') : ''),
            'lote_no_abierto' => $hora($detalle['abre_en'] ?? null) === null
                ? __('sala.rechazo.lote_no_abierto')
                : __('sala.rechazo.lote_abre_a_las', ['hora' => $hora($detalle['abre_en'])]),
            'monto_insuficiente' => isset($detalle['minima'])
                ? __('sala.rechazo.monto_menor_que', ['minima' => Formato::clp((int) $detalle['minima'])])
                : __('sala.rechazo.monto_insuficiente'),
            'cuenta_no_habilitada' => match ($detalle['cuenta'] ?? null) {
                'registrado', 'en_revision', 'rechazado', 'bloqueado', 'sin_postor', 'inactiva' => __('sala.rechazo.cuenta.' . $detalle['cuenta']),
                default => __('sala.rechazo.cuenta_no_habilitada'),
            },
            'sin_garantia' => match ($detalle['garantia'] ?? null) {
                'pendiente', 'en_revision', 'rechazada' => __('sala.rechazo.garantia.' . $detalle['garantia']),
                default => __('sala.rechazo.garantia.sin_inscripcion'),
            },
            default => in_array($motivo, self::MOTIVOS, true) ? __('sala.rechazo.' . $motivo) : __('sala.rechazo.generico'),
        };
    }
}
