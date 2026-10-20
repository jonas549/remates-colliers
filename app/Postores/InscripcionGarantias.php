<?php

namespace App\Postores;

use App\Events\ComprobanteRecibido;
use App\Models\Garantia;
use App\Models\Remate;
use App\Models\User;
use App\Postores\Habilitacion;
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
    /**
     * El postor se inscribe en un remate: nace la garantía pendiente con el monto vigente (10 % por defecto).
     * Con el modo «habilitación única» (Configuración → Garantías) nace una sola garantía que vale para todos los
     * remates, por el monto fijo que definió Colliers; si ya la tiene, se devuelve esa.
     */
    public function inscribir(User $user, Remate $remate): Garantia
    {
        if (! $user->postor?->estaAprobado()) {
            throw new DomainException(__('cuenta.inscripcion.cuenta_no_aprobada'));
        }

        // Los remates de prueba y sus postores son un mundo aparte (21/09): un postor real que llegue al enlace de uno no
        // queda con una garantía que nadie va a revisar, y un postor de prueba no se inscribe en remates reales.
        if ($remate->es_demostracion && ! $user->esDePrueba()) {
            throw new DomainException(__('cuenta.inscripcion.remate_de_prueba'));
        }
        if ($user->esDePrueba() && ! $remate->es_demostracion) {
            throw new DomainException(__('cuenta.inscripcion.cuenta_de_prueba'));
        }

        if (Habilitacion::modo() === Habilitacion::MODO_UNICO) {
            return $this->habilitacionUnica($user, $remate);
        }

        if ($remate->estadoVisible() !== Remate::VISTA_PROXIMO) {
            throw new DomainException(__('cuenta.inscripcion.no_acepta'));
        }
        $this->dentroDelPlazo($remate);

        return DB::transaction(function () use ($user, $remate) {
            $existente = Garantia::where('user_id', $user->id)->where('remate_id', $remate->id)->lockForUpdate()->first();

            return $existente ?? Garantia::paraRemate($remate, $user);
        });
    }

    /** Habilitación única: no vence con el remate desde el que se pide, porque no pertenece a ninguno. */
    private function habilitacionUnica(User $user, Remate $remate): Garantia
    {
        $monto = Habilitacion::montoUnico();
        if ($monto === null) {
            throw new DomainException(__('cuenta.inscripcion.sin_monto_unico'));
        }

        return DB::transaction(function () use ($user, $remate, $monto) {
            $existente = Garantia::where('user_id', $user->id)->where('alcance', Garantia::ALCANCE_UNICO)
                ->lockForUpdate()->latest('id')->first();

            return $existente ?? Garantia::habilitacionUnica($remate, $user, $monto);
        });
    }

    public function subirComprobante(Garantia $garantia, UploadedFile $archivo, string $medio): void
    {
        $garantia->loadMissing('remate');
        if (! in_array($garantia->estado, [Garantia::ESTADO_PENDIENTE, Garantia::ESTADO_RECHAZADA], true)) {
            throw new DomainException($garantia->estado === Garantia::ESTADO_APROBADA
                ? __('cuenta.inscripcion.ya_aprobada') : __('cuenta.inscripcion.ya_en_revision'));
        }
        // La habilitación única no pertenece a un remate: no la vence el plazo del remate desde donde se pidió.
        if (! $garantia->esUnica()) {
            $this->dentroDelPlazo($garantia->remate);
        }

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
            throw new DomainException(__('cuenta.inscripcion.plazo_cerrado'));
        }
        if ($remate->yaComenzo()) {
            throw new DomainException(__('cuenta.inscripcion.ya_comenzo'));
        }
    }
}
