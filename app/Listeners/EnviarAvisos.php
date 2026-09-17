<?php

namespace App\Listeners;

use App\Events\ComprobanteRecibido;
use App\Events\CuentaRevisada;
use App\Events\GarantiaRevisada;
use App\Events\LoteLiquidado;
use App\Events\RematePublicado;
use App\Models\Configuracion;
use App\Models\Lote;
use App\Models\Suscripcion;
use App\Models\User;
use App\Notifications\AdjudicacionAviso;
use App\Notifications\ComprobanteRecibidoAviso;
use App\Notifications\CuentaRevisadaAviso;
use App\Notifications\GarantiaRevisadaAviso;
use App\Notifications\RemateNuevoAviso;
use App\Notifications\ResultadoLoteAviso;
use Carbon\CarbonImmutable;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * Traduce los hechos del sistema en correos (Bloque M). Los correos van a la cola: este oyente solo los encola, así que
 * no demora la acción del administrador ni la liquidación de un lote. Un fallo al encolar se reporta y no revierte nada.
 */
class EnviarAvisos
{
    public function subscribe(Dispatcher $eventos): array
    {
        return [
            CuentaRevisada::class => 'cuenta',
            GarantiaRevisada::class => 'garantia',
            ComprobanteRecibido::class => 'comprobante',
            LoteLiquidado::class => 'lote',
            RematePublicado::class => 'remateNuevo',
        ];
    }

    public function cuenta(CuentaRevisada $evento): void
    {
        $this->seguro(fn () => $evento->postor->user->notify(new CuentaRevisadaAviso($evento->postor, $evento->accion)));
    }

    public function garantia(GarantiaRevisada $evento): void
    {
        $this->seguro(fn () => $evento->garantia->user->notify(new GarantiaRevisadaAviso($evento->garantia)));
    }

    public function comprobante(ComprobanteRecibido $evento): void
    {
        $this->seguro(fn () => $evento->garantia->user->notify(new ComprobanteRecibidoAviso($evento->garantia)));
    }

    /** Al cerrar: el sistema avisa al adjudicatario y a la administración (acta). Un lote desierto, solo a la administración. */
    public function lote(LoteLiquidado $evento): void
    {
        $lote = $evento->lote->fresh(['remate', 'adjudicacion.user']);
        if ($lote->remate->es_demostracion) {
            return;
        }
        $ahora = CarbonImmutable::now('UTC');

        $this->seguro(function () use ($lote, $ahora) {
            if ($lote->estado === Lote::ESTADO_ADJUDICADO && $lote->adjudicacion) {
                $lote->adjudicacion->user->notify(new AdjudicacionAviso($lote->adjudicacion));
                $lote->adjudicacion->forceFill(['notificado_ganador_en' => $ahora])->save();
            }
            $this->administracion(new ResultadoLoteAviso($lote));
            $lote->adjudicacion?->forceFill(['notificado_admin_en' => $ahora])->save();
        });
    }

    public function remateNuevo(RematePublicado $evento): void
    {
        $this->seguro(fn () => Notification::send(
            Suscripcion::vigentes()->whereNull('remate_id')->get()->unique('email'),
            new RemateNuevoAviso($evento->remate),
        ));
    }

    /** Al correo de avisos de Configuración o, si está vacío, a todos los administradores activos. */
    private function administracion(object $aviso): void
    {
        $correo = Configuracion::valor('correo_avisos_admin');
        if (filled($correo)) {
            Notification::route('mail', $correo)->notify($aviso);

            return;
        }
        Notification::send(User::where('rol', User::ROL_ADMIN)->where('estado', User::ESTADO_ACTIVO)->get(), $aviso);
    }

    private function seguro(callable $envio): void
    {
        try {
            $envio();
        } catch (Throwable $e) {
            report($e);
        }
    }
}
