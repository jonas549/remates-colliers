<?php

namespace App\Console\Commands;

use App\Http\Middleware\DocumentosDelRegistro;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Borra los adjuntos que quedaron a medio camino en un registro abandonado (`registro-temporal/` del disco privado).
 * Los que siguen vivos son de alguien que está corrigiendo su formulario: se conservan las primeras horas.
 */
class LimpiarTemporales extends Command
{
    protected $signature = 'colliers:limpiar-temporales {--horas= : Antigüedad mínima para borrar (por defecto, la del middleware)}';

    protected $description = 'Borra los documentos de registros abandonados';

    public function handle(): int
    {
        $horas = (int) ($this->option('horas') ?: DocumentosDelRegistro::HORAS);
        $limite = CarbonImmutable::now('UTC')->subHours(max(1, $horas))->getTimestamp();
        $disco = Storage::disk('local');
        $borradas = 0;

        foreach ($disco->directories(DocumentosDelRegistro::CARPETA) as $carpeta) {
            $archivos = $disco->allFiles($carpeta);
            $ultimo = 0;
            foreach ($archivos as $archivo) {
                $ultimo = max($ultimo, $disco->lastModified($archivo));
            }
            if ($archivos === [] || $ultimo < $limite) {
                $disco->deleteDirectory($carpeta);
                $borradas++;
            }
        }

        $this->info("Registros abandonados borrados: {$borradas}");

        return self::SUCCESS;
    }
}
