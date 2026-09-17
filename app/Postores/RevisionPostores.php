<?php

namespace App\Postores;

use App\Events\CuentaRevisada;
use App\Events\GarantiaRevisada;
use App\Models\AccessLog;
use App\Models\Garantia;
use App\Models\Postor;
use App\Models\Remate;
use App\Models\User;
use Carbon\CarbonImmutable;
use DomainException;

/**
 * Revisión manual de Colliers (Bloques G y H). Cada decisión guarda quién y cuándo, queda en la bitácora y dispara el
 * evento que envía el correo (Bloque M).
 *
 * Máquina de estados propuesta el 15/09 (en revisión con el cliente; texto, no enum):
 * cuenta registrado → en_revision → aprobado | rechazado; aprobado ↔ bloqueado.
 * garantía pendiente → en_revision → aprobada | rechazada; una rechazada se puede volver a enviar.
 */
class RevisionPostores
{
    public function aprobarCuenta(Postor $postor, User $quien): void
    {
        if (! in_array($postor->estado, [Postor::ESTADO_EN_REVISION, Postor::ESTADO_RECHAZADO], true)) {
            throw new DomainException(match ($postor->estado) {
                Postor::ESTADO_REGISTRADO => 'El postor todavía no confirma su correo.',
                Postor::ESTADO_APROBADO => 'La cuenta ya está aprobada.',
                default => 'Una cuenta bloqueada se desbloquea, no se aprueba.',
            });
        }
        $this->revisar($postor, Postor::ESTADO_APROBADO, $quien, null, 'cuenta_aprobada');
    }

    public function rechazarCuenta(Postor $postor, User $quien, string $motivo): void
    {
        if (! in_array($postor->estado, [Postor::ESTADO_REGISTRADO, Postor::ESTADO_EN_REVISION], true)) {
            throw new DomainException('Solo se rechaza una cuenta en revisión. Una aprobada se bloquea.');
        }
        $this->revisar($postor, Postor::ESTADO_RECHAZADO, $quien, $motivo, 'cuenta_rechazada');
    }

    /** Supuesto (máquina de estados en revisión): una cuenta bloqueada no puja ni se inscribe; sus garantías se conservan. */
    public function bloquear(Postor $postor, User $quien, string $motivo): void
    {
        if ($postor->estado !== Postor::ESTADO_APROBADO) {
            throw new DomainException('Solo se bloquea una cuenta aprobada.');
        }
        $this->revisar($postor, Postor::ESTADO_BLOQUEADO, $quien, $motivo, 'cuenta_bloqueada');
    }

    public function desbloquear(Postor $postor, User $quien): void
    {
        if ($postor->estado !== Postor::ESTADO_BLOQUEADO) {
            throw new DomainException('La cuenta no está bloqueada.');
        }
        $this->revisar($postor, Postor::ESTADO_APROBADO, $quien, null, 'cuenta_desbloqueada');
    }

    public function aprobarGarantia(Garantia $garantia, User $quien): void
    {
        $garantia->loadMissing(['user.postor', 'remate']);
        if (! $garantia->user->postor?->estaAprobado()) {
            throw new DomainException('Primero aprueba la cuenta del postor.');
        }
        if (! in_array($garantia->estado, [Garantia::ESTADO_PENDIENTE, Garantia::ESTADO_EN_REVISION, Garantia::ESTADO_RECHAZADA], true)) {
            throw new DomainException('La garantía ya está aprobada.');
        }
        $this->remateAbierto($garantia->remate);

        $garantia->forceFill([
            'estado' => Garantia::ESTADO_APROBADA, 'revisado_por_id' => $quien->id,
            'revisado_en' => CarbonImmutable::now('UTC'), 'motivo_rechazo' => null,
        ])->save();
        $this->anotar($garantia->user, $quien, 'garantia_aprobada', ['remate' => $garantia->remate->folio, 'monto' => $garantia->monto]);
        GarantiaRevisada::dispatch($garantia);
    }

    public function rechazarGarantia(Garantia $garantia, User $quien, string $motivo): void
    {
        $garantia->loadMissing(['user', 'remate']);
        if ($garantia->estado === Garantia::ESTADO_RECHAZADA) {
            throw new DomainException('La garantía ya está rechazada.');
        }
        if ($garantia->estado === Garantia::ESTADO_APROBADA && $garantia->remate->yaComenzo()) {
            throw new DomainException('El remate ya comenzó: una garantía aprobada no se rechaza durante el remate.');
        }
        $this->remateAbierto($garantia->remate);

        $garantia->forceFill([
            'estado' => Garantia::ESTADO_RECHAZADA, 'revisado_por_id' => $quien->id,
            'revisado_en' => CarbonImmutable::now('UTC'), 'motivo_rechazo' => mb_substr(trim($motivo), 0, 1000),
        ])->save();
        $this->anotar($garantia->user, $quien, 'garantia_rechazada', ['remate' => $garantia->remate->folio, 'motivo' => $motivo]);
        GarantiaRevisada::dispatch($garantia);
    }

    private function remateAbierto(Remate $remate): void
    {
        if (in_array($remate->estadoVisible(), [Remate::VISTA_ADJUDICADO, Remate::VISTA_CERRADO, Remate::VISTA_CANCELADO], true)) {
            throw new DomainException("El remate {$remate->folio} ya terminó o fue cancelado.");
        }
    }

    private function revisar(Postor $postor, string $estado, User $quien, ?string $motivo, string $evento): void
    {
        $postor->forceFill([
            'estado' => $estado, 'revisado_por_id' => $quien->id, 'revisado_en' => CarbonImmutable::now('UTC'),
            'motivo_rechazo' => $motivo === null ? null : mb_substr(trim($motivo), 0, 1000),
        ])->save();
        $this->anotar($postor->user, $quien, $evento, array_filter(['motivo' => $motivo]));
        CuentaRevisada::dispatch($postor, $evento);
    }

    private function anotar(User $afectado, User $quien, string $evento, array $detalle): void
    {
        AccessLog::create([
            'user_id' => $afectado->id, 'email' => $afectado->email, 'evento' => $evento,
            'ip' => request()?->ip(), 'user_agent' => mb_substr((string) request()?->userAgent(), 0, 512),
            'detalle' => $detalle + ['por_user_id' => $quien->id],
        ]);
    }
}
