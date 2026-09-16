<?php

namespace App\Console\Commands;

use App\Subastas\Liquidador;
use Illuminate\Console\Command;

/**
 * Respaldo del cierre perezoso: liquida los lotes vencidos que nadie detectó. Corre cada minuto por el
 * programador. No define cuándo cierra un lote (eso es cierra_en); solo acota la demora de las notificaciones.
 */
class Liquidar extends Command
{
    protected $signature = 'colliers:liquidar';

    protected $description = 'Adjudica o declara desiertos los lotes vencidos (idempotente)';

    public function handle(Liquidador $liquidador): int
    {
        $cantidad = $liquidador->liquidarVencidos();
        $this->line($cantidad ? "Lotes liquidados: {$cantidad}" : 'Sin lotes pendientes de liquidar.');

        return self::SUCCESS;
    }
}
