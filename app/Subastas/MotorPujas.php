<?php

namespace App\Subastas;

use App\Models\Garantia;
use App\Models\Lote;
use App\Models\Postor;
use App\Models\Puja;
use App\Models\PujaIntento;
use App\Models\Remate;
use App\Models\User;
use App\Subastas\Difusion\Emisor;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Registro de pujas (CLAUDE.md §5).
 *
 * - Bloqueo sobre la fila del LOTE (lockForUpdate): guarda precio actual, ganador y cierre. Toda validación que
 *   depende del estado del lote se hace con el bloqueo tomado.
 * - Transacción con reintentos ante deadlock.
 * - Validez por hora de RECEPCIÓN: se compara contra `cierra_en` la hora en que llegó la petición.
 * - Sin anti-sniping: una puja nunca modifica `cierra_en`.
 * - Rechazos registrados en `puja_intentos` FUERA de la transacción.
 * - Difusión síncrona después del commit; si falla, la puja sigue siendo válida.
 */
class MotorPujas
{
    private const REINTENTOS = 5;

    public function __construct(private readonly Emisor $emisor, private readonly Liquidador $liquidador) {}

    /** @throws PujaRechazada */
    public function pujar(User $user, int $loteId, mixed $montoCrudo, CarbonImmutable $recibidaEn, ?string $ip = null, ?string $userAgent = null): Puja
    {
        $monto = self::monto($montoCrudo);

        try {
            if ($monto === null) {
                throw new PujaRechazada('monto_invalido', ['valor' => is_scalar($montoCrudo) ? mb_substr((string) $montoCrudo, 0, 40) : gettype($montoCrudo)]);
            }

            [$puja, $remate] = DB::transaction(function () use ($user, $loteId, $monto, $recibidaEn, $ip, $userAgent) {
                $lote = Lote::whereKey($loteId)->lockForUpdate()->first()
                    ?? throw new PujaRechazada('lote_inexistente');
                $remate = $lote->remate;

                $this->validar($lote, $remate, $user, $monto, $recibidaEn);

                $puja = Puja::create([
                    'lote_id' => $lote->id, 'user_id' => $user->id, 'monto' => $monto,
                    'recibida_en' => $recibidaEn, 'ip' => $ip, 'user_agent' => $userAgent === null ? null : mb_substr($userAgent, 0, 512),
                ]);

                $lote->precio_actual = $monto;
                $lote->ganador_id = $user->id;
                $lote->total_pujas++;
                $lote->ultima_puja_en = $recibidaEn;
                if ($lote->estado === Lote::ESTADO_PROGRAMADO) {
                    $lote->estado = Lote::ESTADO_ABIERTO;
                }
                $lote->save();

                if ($remate->estado === Remate::ESTADO_PUBLICADO) {
                    $remate->update(['estado' => Remate::ESTADO_EN_CURSO]);
                }

                return [$puja, $remate];
            }, self::REINTENTOS);
        } catch (PujaRechazada $rechazo) {
            $this->registrarIntento($user, $loteId, $monto, $rechazo, $recibidaEn, $ip, $userAgent);
            if ($rechazo->motivo === 'lote_cerrado') {
                // Detector del cierre perezoso: quien encuentra el lote vencido lo liquida (idempotente).
                $this->liquidador->liquidarPorId($loteId);
            }
            throw $rechazo;
        }

        $this->emitir($remate);

        return $puja;
    }

    private function validar(Lote $lote, Remate $remate, User $user, int $monto, CarbonImmutable $recibidaEn): void
    {
        // El remate finalizado NO se rechaza aquí: el lote dirá que cerró, que es lo que el postor necesita saber (OBS-7).
        if ($remate->estado === Remate::ESTADO_CANCELADO) {
            throw new PujaRechazada('remate_cancelado', ['estado_remate' => $remate->estado]);
        }
        if ($remate->estado === Remate::ESTADO_BORRADOR) {
            throw new PujaRechazada('remate_no_publicado', ['estado_remate' => $remate->estado]);
        }
        // Remate finalizado con un lote que sigue abierto: inconsistencia, no un cierre normal.
        if ($remate->estado === Remate::ESTADO_FINALIZADO && ! in_array($lote->estado, Liquidador::ESTADOS_TERMINALES, true)) {
            throw new PujaRechazada('remate_no_disponible', ['estado_remate' => $remate->estado, 'estado_lote' => $lote->estado]);
        }
        if (in_array($lote->estado, Liquidador::ESTADOS_TERMINALES, true) || $lote->cierra_en === null || $recibidaEn->greaterThanOrEqualTo($lote->cierra_en)) {
            throw new PujaRechazada('lote_cerrado', ['cierra_en' => $lote->cierra_en?->format('Y-m-d H:i:s'), 'estado_lote' => $lote->estado]);
        }
        if ($lote->abre_en === null || $recibidaEn->lessThan($lote->abre_en)) {
            throw new PujaRechazada('lote_no_abierto', ['abre_en' => $lote->abre_en?->format('Y-m-d H:i:s')]);
        }

        // Se relee de la base: un bloqueo o rechazo aplicado a mitad de la sesión rige desde la puja siguiente.
        $cuenta = User::with('postor')->find($user->id);
        if ($cuenta?->rol !== User::ROL_POSTOR || $cuenta->estado !== User::ESTADO_ACTIVO || $cuenta->postor?->estado !== Postor::ESTADO_APROBADO) {
            // El detalle hace específico el mensaje: no es lo mismo «en revisión» que «bloqueada».
            throw new PujaRechazada('cuenta_no_habilitada', ['cuenta' => match (true) {
                $cuenta === null || $cuenta->rol !== User::ROL_POSTOR => 'sin_postor',
                $cuenta->estado !== User::ESTADO_ACTIVO => 'inactiva',
                default => $cuenta->postor?->estado ?? 'sin_postor',
            }]);
        }
        $garantia = Garantia::where('user_id', $user->id)->where('remate_id', $remate->id)->latest('id')->first();
        if ($garantia?->estado !== Garantia::ESTADO_APROBADA) {
            throw new PujaRechazada('sin_garantia', ['garantia' => $garantia?->estado]);
        }

        if ($lote->ganador_id === $user->id) {
            throw new PujaRechazada('ya_vas_ganando');
        }
        $minima = EstadoRemate::pujaMinima($lote, $remate->incrementoMinimo());
        if ($monto < $minima) {
            throw new PujaRechazada('monto_insuficiente', ['minima' => $minima, 'precio_actual' => $lote->precio_actual]);
        }
    }

    /** Solo enteros positivos de pesos: 150000000 o "150000000". Nada de decimales, puntos ni notación científica. */
    private static function monto(mixed $valor): ?int
    {
        if (is_int($valor)) {
            return $valor > 0 ? $valor : null;
        }
        if (is_string($valor) && preg_match('/^[1-9]\d{0,14}$/', $valor)) {
            return (int) $valor;
        }

        return null;
    }

    private function registrarIntento(User $user, int $loteId, ?int $monto, PujaRechazada $rechazo, CarbonImmutable $recibidaEn, ?string $ip, ?string $userAgent): void
    {
        try {
            PujaIntento::create([
                'lote_id' => Lote::whereKey($loteId)->exists() ? $loteId : null,
                'user_id' => $user->id, 'monto' => $monto, 'motivo' => $rechazo->motivo,
                'detalle' => $rechazo->detalle ?: null, 'recibida_en' => $recibidaEn,
                'ip' => $ip, 'user_agent' => $userAgent === null ? null : mb_substr($userAgent, 0, 512),
            ]);
        } catch (Throwable $e) {
            report($e);
        }
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
