<?php

namespace App\Subastas;

use RuntimeException;

/**
 * Rechazo de una puja por una regla de negocio. Se lanza DENTRO de la transacción (que se revierte) y el
 * intento se registra FUERA, en `puja_intentos` (CLAUDE.md §5).
 */
class PujaRechazada extends RuntimeException
{
    public const MENSAJES = [
        'monto_invalido' => 'El monto debe ser un número entero de pesos.',
        'lote_inexistente' => 'El lote no existe.',
        'remate_no_disponible' => 'Este remate no está disponible para pujar.',
        'lote_no_abierto' => 'El lote todavía no está abierto.',
        'lote_cerrado' => 'El lote ya cerró. La puja llegó después del cierre.',
        'cuenta_no_habilitada' => 'Tu cuenta no está aprobada para pujar.',
        'sin_garantia' => 'No tienes una garantía aprobada para este remate.',
        'ya_vas_ganando' => 'Ya tienes la puja más alta.',
        'monto_insuficiente' => 'El monto es menor que la puja mínima.',
    ];

    public function __construct(public readonly string $motivo, public readonly array $detalle = [])
    {
        parent::__construct(self::MENSAJES[$motivo] ?? 'Puja rechazada.');
    }
}
