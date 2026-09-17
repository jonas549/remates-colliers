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
    /** Texto por defecto de cada motivo. `mensaje()` lo completa con los datos del intento cuando los hay. */
    public const MENSAJES = [
        'monto_invalido' => 'El monto debe ser un número entero de pesos, sin puntos ni decimales.',
        'lote_inexistente' => 'Ese lote no existe en este remate.',
        'remate_no_disponible' => 'Este remate no está disponible para pujar.',
        'remate_cancelado' => 'El remate fue cancelado: ya no recibe pujas.',
        'remate_no_publicado' => 'Este remate todavía no está publicado.',
        'lote_no_abierto' => 'El lote todavía no abre.',
        'lote_cerrado' => 'El lote ya cerró: tu puja llegó después de la hora de cierre.',
        'cuenta_no_habilitada' => 'Tu cuenta no está habilitada para pujar.',
        'sin_garantia' => 'No tienes una garantía aprobada para este remate.',
        'ya_vas_ganando' => 'Ya tienes la puja más alta: espera a que alguien te supere.',
        'monto_insuficiente' => 'El monto es menor que la puja mínima.',
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
                ? 'El lote ya cerró.'
                : 'El lote cerró a las ' . $hora($detalle['cierra_en']) . ' (hora de Chile): tu puja llegó después.')
                . (($detalle['estado_lote'] ?? null) === 'adjudicado' ? ' Ya está adjudicado.' : ''),
            'lote_no_abierto' => $hora($detalle['abre_en'] ?? null) === null
                ? 'El lote todavía no abre.'
                : 'El lote abre a las ' . $hora($detalle['abre_en']) . ' (hora de Chile): todavía no se puede pujar.',
            'monto_insuficiente' => isset($detalle['minima'])
                ? 'El monto es menor que la puja mínima: ' . Formato::clp((int) $detalle['minima']) . '.'
                : self::MENSAJES['monto_insuficiente'],
            'cuenta_no_habilitada' => match ($detalle['cuenta'] ?? null) {
                'registrado' => 'Todavía no confirmas tu correo: revisa tu bandeja para activar la cuenta.',
                'en_revision' => 'Colliers todavía está revisando tu cuenta: te avisamos por correo cuando quede aprobada.',
                'rechazado' => 'Tu cuenta fue rechazada: revisa el motivo en «Mi cuenta».',
                'bloqueado' => 'Tu cuenta está bloqueada: escríbenos para revisarla.',
                'sin_postor' => 'Esta cuenta no es de postor: las pujas se hacen con una cuenta de postor.',
                'inactiva' => 'Tu cuenta está deshabilitada.',
                default => self::MENSAJES['cuenta_no_habilitada'],
            },
            'sin_garantia' => match ($detalle['garantia'] ?? null) {
                'pendiente' => 'Tu garantía está pendiente: sube el comprobante en «Mi cuenta» para que Colliers la revise.',
                'en_revision' => 'Tu garantía está en revisión: te avisamos por correo apenas quede aprobada.',
                'rechazada' => 'Tu garantía fue rechazada: revisa el motivo en «Mi cuenta» y sube otro comprobante.',
                default => 'No estás inscrito en este remate: inscríbete y constituye la garantía para poder pujar.',
            },
            default => self::MENSAJES[$motivo] ?? 'Puja rechazada.',
        };
    }
}
