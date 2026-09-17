<?php

namespace App\Notifications;

use App\Models\Remate;
use App\Support\Formato;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Recordatorio antes del remate. `motivo`:
 * inicio (a inscritos con garantía aprobada y a quienes pidieron «Avísame» de ese remate),
 * garantia (a inscritos con la garantía sin aprobar),
 * cierre_garantias (a suscriptores generales, antes del cierre de garantías).
 */
class RecordatorioRemateAviso extends AvisoColliers
{
    public function __construct(public Remate $remate, public string $motivo) {}

    public function plantilla(): string
    {
        return match ($this->motivo) {
            'garantia' => 'recordatorio_garantia',
            'cierre_garantias' => 'recordatorio_cierre_garantias',
            default => 'recordatorio_remate',
        };
    }

    public function notificable(): ?Model
    {
        return $this->remate;
    }

    protected function datos(?object $destinatario = null): array
    {
        $inicio = $this->remate->abreEn();

        return [
            'remate' => $this->remate->titulo,
            'folio' => $this->remate->folio,
            'inicio' => Formato::fecha($inicio),
            'cierre_garantias' => $this->remate->cierre_garantias_en ? Formato::fecha($this->remate->cierre_garantias_en) : 'antes del inicio',
            'faltan' => $inicio === null ? '' : self::cuantoFalta($inicio),
        ];
    }

    /** «en 26 horas», «en 45 minutos»: el recordatorio se envía a una hora fija antes del inicio. */
    private static function cuantoFalta(CarbonImmutable $inicio): string
    {
        $minutos = max(0, (int) round(CarbonImmutable::now('UTC')->diffInMinutes($inicio, absolute: false)));

        return $minutos >= 90 ? (int) round($minutos / 60) . ' horas' : "{$minutos} minutos";
    }

    protected function enlace(?object $destinatario = null): ?string
    {
        return $this->motivo === 'garantia'
            ? route('cuenta.estado', ['remate' => $this->remate->slug])
            : route('remates.show', $this->remate->slug);
    }

    protected function pie(MailMessage $correo, object $destinatario): MailMessage
    {
        return RemateNuevoAviso::baja($correo, $destinatario);
    }
}
