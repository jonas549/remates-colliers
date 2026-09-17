<?php

namespace App\Console\Commands;

use App\Models\Configuracion;
use App\Models\Garantia;
use App\Models\NotificacionLog;
use App\Models\Remate;
use App\Models\Suscripcion;
use App\Models\User;
use App\Notifications\RecordatorioRemateAviso;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

/**
 * Recordatorios antes del remate (Bloque M). El programador lo corre cada 10 minutos; es idempotente: cada destinatario
 * recibe cada recordatorio una sola vez (se consulta `notificaciones_log`, y los encolados de esta corrida se recuerdan).
 *
 * - N horas antes del inicio (Configuración): a inscritos con garantía aprobada y a quienes pidieron «Avísame» de ese
 *   remate; a inscritos con la garantía sin aprobar, un aviso de que falta.
 * - 48 horas antes del cierre de garantías (diseño del listado): a los suscriptores generales.
 */
class EnviarRecordatorios extends Command
{
    protected $signature = 'colliers:recordatorios';

    protected $description = 'Encola los recordatorios de remates próximos (idempotente)';

    private const HORAS_ANTES_CIERRE_GARANTIAS = 48;

    public function handle(): int
    {
        $ahora = CarbonImmutable::now('UTC');
        $horas = (int) Configuracion::valor('recordatorio_horas_antes');
        $enviados = 0;

        $remates = Remate::with('lotes')->where('estado', Remate::ESTADO_PUBLICADO)->where('es_demostracion', false)->get()
            ->filter(fn (Remate $r) => $r->estadoVisible($ahora) === Remate::VISTA_PROXIMO);

        foreach ($remates as $remate) {
            $inicio = $remate->abreEn();
            if ($inicio !== null && $ahora->greaterThanOrEqualTo($inicio->subHours($horas))) {
                $garantias = Garantia::with('user')->where('remate_id', $remate->id)->get();
                $enviados += $this->enviar($remate, 'inicio', $garantias->where('estado', Garantia::ESTADO_APROBADA)->pluck('user')
                    ->merge(Suscripcion::vigentes()->where('remate_id', $remate->id)->get()));
                if ($remate->cierre_garantias_en === null || $ahora->lessThan($remate->cierre_garantias_en)) {
                    $enviados += $this->enviar($remate, 'garantia', $garantias->whereIn('estado', [Garantia::ESTADO_PENDIENTE, Garantia::ESTADO_RECHAZADA])->pluck('user'));
                }
            }

            $cierre = $remate->cierre_garantias_en;
            if ($cierre !== null && $ahora->lessThan($cierre) && $ahora->greaterThanOrEqualTo($cierre->subHours(self::HORAS_ANTES_CIERRE_GARANTIAS))) {
                $enviados += $this->enviar($remate, 'cierre_garantias', Suscripcion::vigentes()->whereNull('remate_id')->get());
            }
        }

        $this->line("Recordatorios encolados: {$enviados}");

        return self::SUCCESS;
    }

    /** Encola a quienes todavía no lo recibieron (por correo, sin repetir entre usuario y suscripción). */
    private function enviar(Remate $remate, string $motivo, Collection $destinatarios): int
    {
        $asunto = (new RecordatorioRemateAviso($remate, $motivo))->asunto();
        $yaRecibieron = NotificacionLog::where('tipo', 'RecordatorioRemateAviso')->where('notificable_type', $remate->getMorphClass())
            ->where('notificable_id', $remate->id)->where('asunto', $asunto)->whereIn('estado', ['enviada', 'pendiente'])
            ->pluck('destinatario')->map(fn ($c) => mb_strtolower($c))->all();

        $cantidad = 0;
        foreach ($destinatarios->filter()->unique(fn ($d) => mb_strtolower($d->email)) as $destinatario) {
            if (in_array(mb_strtolower($destinatario->email), $yaRecibieron, true)) {
                continue;
            }
            // Pendiente hasta que la cola lo envíe (RegistroDeNotificaciones la marca enviada): evita duplicarlo si el cron
            // corre antes de procesar la cola. Se anota ANTES de encolar: con cola síncrona el envío ocurre dentro de notify().
            NotificacionLog::create(['canal' => 'correo', 'tipo' => 'RecordatorioRemateAviso', 'destinatario' => $destinatario->email, 'asunto' => $asunto,
                'estado' => 'pendiente', 'notificable_type' => $remate->getMorphClass(), 'notificable_id' => $remate->id,
                'user_id' => $destinatario instanceof User ? $destinatario->id : null]);
            $destinatario->notify(new RecordatorioRemateAviso($remate, $motivo));
            $cantidad++;
        }

        return $cantidad;
    }
}
