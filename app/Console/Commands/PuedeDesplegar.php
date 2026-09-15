<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * El script de deploy lo llama ANTES de actualizar el código. Código de salida 0 = se puede desplegar,
 * 75 = NO desplegar ahora (el script termina sin tocar nada y reintenta en el próximo ciclo del cron).
 * Se usa 75 y no 1 para que un error cualquiera de la aplicación no se confunda con un bloqueo.
 *
 * Hoy bloquea solo si existe el archivo de bloqueo manual (config colliers.bloqueo_deploy).
 * El Bloque J agrega aquí el bloqueo automático mientras haya un remate en curso.
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

        // Bloque J: bloquear si hay un remate en curso o que comienza en los próximos minutos.

        $this->line('SI: se puede desplegar.');

        return self::SUCCESS;
    }
}
