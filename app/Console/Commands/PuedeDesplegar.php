<?php

namespace App\Console\Commands;

use App\Models\Lote;
use App\Models\Remate;
use App\Subastas\Liquidador;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

/**
 * El script de deploy lo llama ANTES de actualizar el código. Código de salida 0 = se puede desplegar,
 * 75 = NO desplegar ahora (el script termina sin tocar nada y reintenta en el próximo ciclo del cron).
 * Se usa 75 y no 1 para que un error cualquiera de la aplicación no se confunda con un bloqueo.
 *
 * Bloquea si existe el archivo de bloqueo manual (config colliers.bloqueo_deploy) o si hay un remate en curso
 * o por comenzar (Bloque J).
 */
class PuedeDesplegar extends Command
{
    public const NO_DESPLEGAR = 75;

    protected $signature = 'colliers:puede-desplegar';

    protected $description = 'Indica si se puede desplegar ahora (0 = sí, 75 = no)';

    public function handle(): int
    {
        $bloqueo = config('colliers.bloqueo_deploy');
        if ($bloqueo && file_exists($bloqueo)) {
            $motivo = trim((string) file_get_contents($bloqueo)) ?: 'sin motivo indicado';
            $this->line("NO: deploy bloqueado manualmente ({$motivo}).");

            return self::NO_DESPLEGAR;
        }

        $enCurso = $this->remateEnCurso();
        if ($enCurso !== null) {
            $this->line("NO: remate {$enCurso} en curso o por comenzar.");

            return self::NO_DESPLEGAR;
        }

        $this->line('SI: se puede desplegar.');

        return self::SUCCESS;
    }

    /**
     * Folio del remate que bloquea el deploy, o null. Bloquea desde N minutos antes de que abra un lote hasta que
     * el lote esté liquidado. Un lote vencido hace más de 15 minutos sin liquidar (cron detenido) deja de bloquear,
     * para que un cron caído no congele los deploys para siempre.
     */
    private function remateEnCurso(): ?string
    {
        if (! Schema::hasTable('lotes')) {
            return null;
        }

        $ahora = CarbonImmutable::now('UTC');
        $lote = Lote::query()
            ->whereHas('remate', fn ($q) => $q->whereIn('estado', [Remate::ESTADO_PUBLICADO, Remate::ESTADO_EN_CURSO]))
            ->whereNotIn('estado', Liquidador::ESTADOS_TERMINALES)
            ->where('abre_en', '<=', $ahora->addMinutes(config('colliers.deploy_minutos_antes_de_remate'))->format('Y-m-d H:i:s'))
            ->where('cierra_en', '>', $ahora->subMinutes(15)->format('Y-m-d H:i:s'))
            ->with('remate')
            ->first();

        return $lote?->remate->folio;
    }
}
