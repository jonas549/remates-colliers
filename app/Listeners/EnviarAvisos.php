<?php

namespace App\Listeners;

use App\Events\ComprobanteRecibido;
use App\Events\CuentaRevisada;
use App\Events\GarantiaRevisada;
use App\Events\LoteLiquidado;
use App\Events\RematePublicado;
use App\Correo\Avisos;
use App\Models\Configuracion;
use App\Models\Lote;
use App\Models\Puja;
use App\Models\Remate;
use App\Models\Suscripcion;
use App\Models\User;
use App\Notifications\AdjudicacionAviso;
use App\Notifications\AvisoColliers;
use App\Notifications\ComprobanteRecibidoAviso;
use App\Notifications\CuentaRevisadaAviso;
use App\Notifications\GarantiaRevisadaAviso;
use App\Notifications\NoAdjudicadoAviso;
use App\Notifications\RemateNuevoAviso;
use App\Notifications\ResumenRemateAviso;
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
        $aviso = new CuentaRevisadaAviso($evento->postor, $evento->accion);
        $this->enviar($aviso, fn () => $evento->postor->user->notify($aviso));
    }

    public function garantia(GarantiaRevisada $evento): void
    {
        $aviso = new GarantiaRevisadaAviso($evento->garantia);
        $this->enviar($aviso, fn () => $evento->garantia->user->notify($aviso));
    }

    public function comprobante(ComprobanteRecibido $evento): void
    {
        $aviso = new ComprobanteRecibidoAviso($evento->garantia);
        $this->enviar($aviso, fn () => $evento->garantia->user->notify($aviso));
    }

    /**
     * Al cerrar un lote: al adjudicatario (acta) y a quienes pujaron y no ganaron (decisión del 17/09, en vez del
     * correo «te superaron» por cada puja). A la administración va UN resumen cuando cierra el último lote.
     */
    public function lote(LoteLiquidado $evento): void
    {
        $lote = $evento->lote->fresh(['remate', 'adjudicacion.user']);
        if ($lote->remate->es_demostracion) {
            return;
        }
        $ahora = CarbonImmutable::now('UTC');

        if ($lote->estado === Lote::ESTADO_ADJUDICADO && $lote->adjudicacion) {
            $adjudicacion = new AdjudicacionAviso($lote->adjudicacion);
            $this->enviar($adjudicacion, function () use ($lote, $adjudicacion, $ahora) {
                $lote->adjudicacion->user->notify($adjudicacion);
                $lote->adjudicacion->forceFill(['notificado_ganador_en' => $ahora])->save();
            });

            $perdedores = User::whereIn('id', Puja::where('lote_id', $lote->id)->where('user_id', '!=', $lote->adjudicacion->user_id)
                ->distinct()->pluck('user_id'))->get();
            if ($perdedores->isNotEmpty()) {
                $noAdjudicado = new NoAdjudicadoAviso($lote);
                $this->enviar($noAdjudicado, fn () => Notification::send($perdedores, $noAdjudicado));
            }
        }

        // Un solo correo a la administración, con todos los lotes, cuando el remate queda cerrado.
        if ($lote->remate->fresh()->estado === Remate::ESTADO_FINALIZADO) {
            $resumen = new ResumenRemateAviso($lote->remate);
            $this->enviar($resumen, function () use ($lote, $resumen, $ahora) {
                $this->administracion($resumen);
                $lote->adjudicacion?->forceFill(['notificado_admin_en' => $ahora])->save();
            });
        }
    }

    public function remateNuevo(RematePublicado $evento): void
    {
        $aviso = new RemateNuevoAviso($evento->remate);
        $this->enviar($aviso, fn () => Notification::send(
            Suscripcion::vigentes()->whereNull('remate_id')->get()->unique('email'),
            $aviso,
        ));
    }

    /** Respeta el interruptor de la pantalla Notificaciones: apagado, no se envía nada. */
    private function enviar(AvisoColliers $aviso, callable $envio): void
    {
        if (! Avisos::activo($aviso->plantilla())) {
            return;
        }
        $this->seguro($envio);
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
