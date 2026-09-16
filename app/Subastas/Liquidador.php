<?php

namespace App\Subastas;

use App\Events\LoteLiquidado;
use App\Models\Adjudicacion;
use App\Models\Configuracion;
use App\Models\Lote;
use App\Models\Puja;
use App\Models\Remate;
use App\Models\User;
use App\Subastas\Difusion\Emisor;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Cierre de lotes (CLAUDE.md §3 y §5).
 *
 * - Cierre perezoso: un lote está cerrado por definición cuando ahora ≥ cierra_en. La adjudicación se
 *   materializa en cierra_en + margen de liquidación, para que terminen las pujas recibidas antes de T.
 * - Idempotente: la materializa el primero que la detecta (puja, endpoint de estado, cron); los demás no hacen nada.
 * - Sin anti-sniping: nadie modifica cierra_en salvo el cierre anticipado de un administrador o martillero.
 * - Supuesto vigente (16/09): el cierre anticipado adjudica la mejor puja; sin pujas, el lote queda desierto.
 */
class Liquidador
{
    public const ESTADOS_TERMINALES = [Lote::ESTADO_ADJUDICADO, Lote::ESTADO_DESIERTO, Lote::ESTADO_CERRADO, Lote::ESTADO_INCUMPLIDO];

    private const REINTENTOS = 5;

    public function __construct(private readonly Emisor $emisor) {}

    public function margenSegundos(): int
    {
        return (int) Configuracion::valor('margen_liquidacion_segundos');
    }

    /** Liquida el lote si ya pasó cierra_en + margen. Devuelve true solo si esta llamada lo materializó. */
    public function liquidarPorId(int $loteId, ?CarbonImmutable $ahora = null): bool
    {
        $ahora ??= CarbonImmutable::now('UTC');
        $margen = $this->margenSegundos();

        $lote = Lote::find($loteId);
        if ($lote === null || ! $this->pendienteDeLiquidar($lote, $ahora, $margen)) {
            return false;
        }

        $liquidado = DB::transaction(function () use ($loteId, $ahora, $margen) {
            $lote = Lote::whereKey($loteId)->lockForUpdate()->first();
            if (! $this->pendienteDeLiquidar($lote, $ahora, $margen)) {
                return null;
            }

            $lote->cerrado_en = $lote->cierra_en;
            $lote->motivo_cierre ??= Lote::CIERRE_TIEMPO;

            if ($lote->ganador_id === null) {
                $lote->estado = Lote::ESTADO_DESIERTO;
                $lote->save();
            } else {
                $puja = Puja::where('lote_id', $lote->id)->where('user_id', $lote->ganador_id)
                    ->where('monto', $lote->precio_actual)->orderByDesc('id')->firstOrFail();
                $lote->estado = Lote::ESTADO_ADJUDICADO;
                $lote->save();
                Adjudicacion::create([
                    'lote_id' => $lote->id, 'user_id' => $lote->ganador_id, 'puja_id' => $puja->id, 'monto' => $puja->monto,
                    'cerrado_en' => $lote->cierra_en, 'motivo_cierre' => $lote->motivo_cierre, 'estado' => Adjudicacion::ESTADO_ADJUDICADO,
                ]);
            }

            $remate = $lote->remate;
            $quedanAbiertos = Lote::where('remate_id', $remate->id)->whereNotIn('estado', self::ESTADOS_TERMINALES)->exists();
            if (! $quedanAbiertos && ! in_array($remate->estado, [Remate::ESTADO_FINALIZADO, Remate::ESTADO_CANCELADO], true)) {
                $remate->update(['estado' => Remate::ESTADO_FINALIZADO, 'finalizado_en' => $lote->cierra_en]);
            }

            return $lote;
        }, self::REINTENTOS);

        if ($liquidado === null) {
            return false;
        }

        $this->emitir($liquidado->remate);
        event(new LoteLiquidado($liquidado));

        return true;
    }

    /** Para el cron y el endpoint de estado. Devuelve cuántos lotes materializó esta llamada. */
    public function liquidarVencidos(?Remate $remate = null, ?CarbonImmutable $ahora = null): int
    {
        $ahora ??= CarbonImmutable::now('UTC');
        $ids = Lote::query()
            ->when($remate, fn ($q) => $q->where('remate_id', $remate->id))
            ->whereNotIn('estado', self::ESTADOS_TERMINALES)
            ->whereNotNull('cierra_en')
            ->where('cierra_en', '<=', $ahora->subSeconds($this->margenSegundos())->format('Y-m-d H:i:s'))
            ->pluck('id');

        return $ids->filter(fn (int $id) => $this->liquidarPorId($id, $ahora))->count();
    }

    /**
     * Cierre anticipado (emergencia): fija cierra_en en este momento. Las pujas recibidas antes siguen siendo
     * válidas; la adjudicación se materializa pasado el margen, por el mismo camino que un cierre por tiempo.
     */
    public function cerrarAnticipadamente(Lote $lote, User $quien, CarbonImmutable $ahora): Lote
    {
        $lote = DB::transaction(function () use ($lote, $quien, $ahora) {
            $lote = Lote::whereKey($lote->id)->lockForUpdate()->firstOrFail();

            if (in_array($lote->estado, self::ESTADOS_TERMINALES, true) || ($lote->cierra_en !== null && $lote->vencido($ahora))) {
                throw new DomainException('El lote ya está cerrado.');
            }
            if ($lote->abre_en === null || $ahora->lessThan($lote->abre_en)) {
                throw new DomainException('El lote todavía no abre: no se puede cerrar anticipadamente.');
            }

            // cierra_en tiene precisión de segundos: se trunca hacia abajo, nunca se acepta una puja posterior al cierre.
            $lote->cierra_en = $ahora->startOfSecond();
            $lote->motivo_cierre = Lote::CIERRE_ANTICIPADO;
            $lote->cerrado_por_id = $quien->id;
            $lote->save();

            return $lote;
        }, self::REINTENTOS);

        $this->emitir($lote->remate);

        return $lote;
    }

    private function pendienteDeLiquidar(Lote $lote, CarbonImmutable $ahora, int $margen): bool
    {
        return ! in_array($lote->estado, self::ESTADOS_TERMINALES, true)
            && $lote->cierra_en !== null
            && $ahora->greaterThanOrEqualTo($lote->cierra_en->addSeconds($margen));
    }

    private function emitir(Remate $remate): void
    {
        try {
            $this->emisor->publicarEstado($remate);
        } catch (Throwable $e) {
            report($e);
        }
    }
}
