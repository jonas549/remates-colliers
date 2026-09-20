<?php

namespace App\Console\Commands;

use App\Remates\RemateDePrueba;
use DomainException;
use Illuminate\Console\Command;

/**
 * Crea un remate de DEMOSTRACIÓN listo para probar en el sandbox: lotes con horario fijo, postores aprobados con
 * garantía aprobada y el estado publicado en el JSON estático. Sirve para las pruebas de transporte con espectadores
 * y de concurrencia contra MariaDB real.
 *
 * - Se niega a correr con APP_ENV=production. El sandbox debe usar APP_ENV=staging.
 * - Las claves de los postores son ALEATORIAS y se muestran una sola vez; nunca hay claves fijas en el código.
 * - El remate queda marcado `es_demostracion`: el sitio público y los reportes lo excluyen.
 */
class RemateDemo extends Command
{
    protected $signature = 'colliers:remate-demo
        {--postores=10 : Postores aprobados con garantía aprobada (1 a 50)}
        {--lotes=3 : Cantidad de lotes (1 a 10). Varios por defecto: así se prueba el paso al siguiente y el cierre anticipado}
        {--inicio=5 : Minutos desde ahora hasta que abre el primer lote}
        {--duracion=10 : Minutos que dura cada lote}
        {--pausa=1 : Minutos de pausa entre lotes}';

    protected $description = 'Crea un remate de demostración con postores habilitados (nunca en producción)';

    public function handle(RemateDePrueba $prueba): int
    {
        try {
            $creado = $prueba->crear(
                postores: (int) $this->option('postores'),
                lotes: (int) $this->option('lotes'),
                inicioMinutos: max(1, (int) $this->option('inicio')),
                duracionMinutos: (int) $this->option('duracion'),
                pausaMinutos: (int) $this->option('pausa'),
            );
        } catch (DomainException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $remate = $creado['remate'];
        $primero = $remate->lotes->first();
        $this->info("Remate de demostración {$remate->folio} creado.");
        $this->line('  Primer lote abre: ' . $primero->abre_en->setTimezone('America/Santiago')->format('d-m-Y H:i') . ' (Santiago)');
        $this->line('  Estado público:   ' . rtrim(config('app.url'), '/') . "/tiempo-real/{$remate->slug}.json");
        $this->line('  Estado (PHP):     ' . rtrim(config('app.url'), '/') . "/remates/{$remate->slug}/estado");
        $this->newLine();
        $this->warn("Clave de TODOS los postores de demostración (se muestra una sola vez): {$creado['clave']}");
        $this->table(['#', 'Correo', 'RUT'], array_map(array_values(...), $creado['postores']));

        return self::SUCCESS;
    }
}
