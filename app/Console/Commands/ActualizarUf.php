<?php

namespace App\Console\Commands;

use App\Models\Configuracion;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Actualiza el valor de la UF desde mindicador.cl cuando la fuente configurada es automática (Bloque V).
 * El programador lo ejecuta cada hora; solo consulta si el valor guardado no es el de hoy. Si el hosting bloquea HTTP
 * saliente o el servicio no responde, el valor anterior se mantiene (la UF es solo referencia visual).
 */
class ActualizarUf extends Command
{
    protected $signature = 'colliers:actualizar-uf {--forzar : Consultar aunque la fuente sea manual o el valor ya sea de hoy}';

    protected $description = 'Actualiza la UF de referencia desde mindicador.cl';

    public const URL = 'https://mindicador.cl/api/uf';

    public function handle(): int
    {
        $forzar = (bool) $this->option('forzar');
        if (! $forzar && Configuracion::valor('uf_fuente') !== 'mindicador') {
            $this->line('Fuente manual: no se consulta.');

            return self::SUCCESS;
        }
        $hoy = CarbonImmutable::now('America/Santiago')->toDateString();
        if (! $forzar && Configuracion::valor('uf_fecha') === $hoy && Configuracion::valor('uf_valor')) {
            $this->line("La UF ya es la de hoy ({$hoy}).");

            return self::SUCCESS;
        }

        try {
            $respuesta = Http::timeout(10)->acceptJson()->get(self::URL)->throw();
            $valor = $respuesta->json('serie.0.valor');
            $fecha = $respuesta->json('serie.0.fecha');
        } catch (Throwable $e) {
            $this->error('No se pudo consultar mindicador.cl: ' . $e->getMessage());

            return self::FAILURE;
        }
        if (! is_numeric($valor) || (float) $valor < 1000 || ! is_string($fecha)) {
            $this->error('Respuesta inesperada de mindicador.cl.');

            return self::FAILURE;
        }

        $dia = CarbonImmutable::parse($fecha)->setTimezone('America/Santiago')->toDateString();
        Configuracion::guardar('uf_valor', number_format((float) $valor, 2, '.', ''));
        Configuracion::guardar('uf_fecha', $dia);
        $this->info("UF del {$dia}: $" . number_format((float) $valor, 2, ',', '.'));

        return self::SUCCESS;
    }
}
