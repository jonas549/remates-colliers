<?php

namespace App\Console\Commands;

use App\Subastas\Liquidador;
use Illuminate\Console\Command;

/**
 * Respaldo de las transiciones automáticas: cierra los lotes vencidos y abre los que ya empezaron, aunque nadie
 * tenga la sala abierta. Corre cada minuto por el programador. No define cuándo abre ni cierra un lote (eso es
 * abre_en / cierra_en): solo acota cuánto tarda el JSON público en reflejarlo y la demora de las notificaciones.
 */
class Liquidar extends Command
{
    protected $signature = 'colliers:liquidar';

    protected $description = 'Abre los lotes que ya empezaron y cierra los vencidos (idempotente)';

    public function handle(Liquidador $liquidador): int
    {
        ['liquidados' => $liquidados, 'abiertos' => $abiertos] = $liquidador->transicionesPendientes();
        $this->line($liquidados ? "Lotes liquidados: {$liquidados}" : 'Sin lotes pendientes de liquidar.');
        $this->line($abiertos ? "Lotes abiertos: {$abiertos}" : 'Sin lotes pendientes de abrir.');

        return self::SUCCESS;
    }
}
