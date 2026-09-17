<?php

namespace App\Postores;

use App\Events\ComprobanteRecibido;
use App\Models\Garantia;
use App\Models\Remate;
use App\Models\User;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Lado del postor en las garantías (Bloque H). Proceso 100 % manual y externo: vale a la vista o transferencia, SIN
 * pasarela de pago. La plataforma solo registra la inscripción y recibe el comprobante para que Colliers lo revise.
 */
class InscripcionGarantias
{
    /** El postor se inscribe en un remate: nace la garantía pendiente con el monto vigente (10 % por defecto). */
    public function inscribir(User $user, Remate $remate): Garantia
    {
        if (! $user->postor?->estaAprobado()) {
            throw new DomainException('Tu cuenta debe estar aprobada por Colliers para inscribirte en un remate.');
        }
        if ($remate->estadoVisible() !== Remate::VISTA_PROXIMO) {
            throw new DomainException('Este remate no acepta inscripciones.');
        }
        $this->dentroDelPlazo($remate);

        return DB::transaction(function () use ($user, $remate) {
            $existente = Garantia::where('user_id', $user->id)->where('remate_id', $remate->id)->lockForUpdate()->first();

            return $existente ?? Garantia::paraRemate($remate, $user);
        });
    }

    public function subirComprobante(Garantia $garantia, UploadedFile $archivo, string $medio): void
    {
        $garantia->loadMissing('remate');
        if (! in_array($garantia->estado, [Garantia::ESTADO_PENDIENTE, Garantia::ESTADO_RECHAZADA], true)) {
            throw new DomainException($garantia->estado === Garantia::ESTADO_APROBADA
                ? 'Tu garantía ya está aprobada.' : 'Ya recibimos tu comprobante y lo estamos revisando.');
        }
        $this->dentroDelPlazo($garantia->remate);

        $ruta = $archivo->storeAs("garantias/{$garantia->remate_id}", Str::uuid() . '.' . ($archivo->guessExtension() ?: 'pdf'), 'local');
        $anterior = $garantia->comprobante_ruta;
        $garantia->forceFill([
            'medio' => $medio,
            'comprobante_ruta' => $ruta,
            'comprobante_nombre' => mb_substr($archivo->getClientOriginalName(), 0, 255),
            'comprobante_subido_en' => CarbonImmutable::now('UTC'),
            'estado' => Garantia::ESTADO_EN_REVISION,
        ])->save();
        if ($anterior && $anterior !== $ruta) {
            // El comprobante rechazado se conserva: es respaldo de la revisión anterior.
            $garantia->forceFill(['notas_internas' => trim(($garantia->notas_internas ?? '') . "\nComprobante anterior: {$anterior}")])->save();
        }
        ComprobanteRecibido::dispatch($garantia);
    }

    private function dentroDelPlazo(Remate $remate): void
    {
        if ($remate->cierre_garantias_en !== null && CarbonImmutable::now('UTC')->greaterThanOrEqualTo($remate->cierre_garantias_en)) {
            throw new DomainException('El plazo para constituir la garantía de este remate ya cerró.');
        }
        if ($remate->yaComenzo()) {
            throw new DomainException('El remate ya comenzó: no se reciben más garantías.');
        }
    }
}
