<?php

namespace App\Notifications;

use App\Models\Postor;
use Illuminate\Database\Eloquent\Model;

/** Cuenta aprobada, rechazada, bloqueada o desbloqueada. */
class CuentaRevisadaAviso extends AvisoColliers
{
    public function __construct(public Postor $postor, public string $accion) {}

    public function plantilla(): string
    {
        return match ($this->accion) {
            'cuenta_aprobada' => 'cuenta_aprobada',
            'cuenta_rechazada' => 'cuenta_rechazada',
            'cuenta_bloqueada' => 'cuenta_bloqueada',
            default => 'cuenta_desbloqueada',
        };
    }

    public function notificable(): ?Model
    {
        return $this->postor;
    }

    protected function datos(?object $destinatario = null): array
    {
        return ['motivo' => $this->postor->motivo_rechazo ?: 'no indicado'];
    }

    protected function enlace(?object $destinatario = null): ?string
    {
        return $this->accion === 'cuenta_aprobada' ? route('remates.index') : route('cuenta.estado');
    }
}
