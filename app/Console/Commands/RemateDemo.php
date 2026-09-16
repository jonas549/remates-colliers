<?php

namespace App\Console\Commands;

use App\Models\Garantia;
use App\Models\Lote;
use App\Models\Postor;
use App\Models\Remate;
use App\Models\User;
use App\Subastas\Difusion\Emisor;
use App\Support\Rut;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

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
        {--lotes=1 : Cantidad de lotes (1 a 10)}
        {--inicio=5 : Minutos desde ahora hasta que abre el primer lote}
        {--duracion=30 : Minutos que dura cada lote}
        {--pausa=2 : Minutos de pausa entre lotes}';

    protected $description = 'Crea un remate de demostración con postores habilitados (nunca en producción)';

    public function handle(Emisor $emisor): int
    {
        if (app()->isProduction()) {
            $this->error('No se crean remates de demostración con APP_ENV=production. En el sandbox usa APP_ENV=staging.');

            return self::FAILURE;
        }

        $postores = (int) $this->option('postores');
        $lotes = (int) $this->option('lotes');
        $inicio = (int) $this->option('inicio');
        $duracion = (int) $this->option('duracion');
        $pausa = (int) $this->option('pausa');
        if ($postores < 1 || $postores > 50 || $lotes < 1 || $lotes > 10 || $inicio < 0 || $duracion < 1 || $pausa < 0) {
            $this->error('Opciones fuera de rango: postores 1–50, lotes 1–10, inicio ≥ 0, duración ≥ 1, pausa ≥ 0.');

            return self::FAILURE;
        }

        $sufijo = CarbonImmutable::now('America/Santiago')->format('ymd-Hi') . '-' . Str::lower(Str::random(3));
        $clave = Str::password(16, symbols: false);

        [$remate, $credenciales] = DB::transaction(function () use ($sufijo, $clave, $postores, $lotes, $inicio, $duracion, $pausa) {
            $remate = Remate::create([
                'folio' => 'DEMO-' . strtoupper($sufijo),
                'slug' => 'demo-' . $sufijo,
                'titulo' => 'DEMOSTRACIÓN · no es un remate real',
                'estado' => Remate::ESTADO_PUBLICADO,
                'inicio_en' => CarbonImmutable::now('UTC')->addMinutes($inicio)->startOfMinute(),
                'duracion_lote_segundos' => $duracion * 60,
                'pausa_entre_lotes_segundos' => $pausa * 60,
                'publicado_en' => CarbonImmutable::now('UTC'),
            ]);
            $remate->forceFill(['es_demostracion' => true])->save();

            for ($orden = 1; $orden <= $lotes; $orden++) {
                Lote::create([
                    'remate_id' => $remate->id, 'orden' => $orden, 'titulo' => "Lote de demostración {$orden}",
                    'tipo_propiedad' => 'Departamento', 'comuna' => 'Las Condes', 'region' => 'Región Metropolitana',
                    'precio_base' => 100000000,
                ]);
            }
            $remate->programarLotes();

            $credenciales = [];
            for ($i = 1; $i <= $postores; $i++) {
                $email = "demo{$i}-{$sufijo}@demo.colliers.test";
                $user = User::create(['name' => "Postor demo {$i}", 'email' => $email, 'password' => $clave, 'rol' => User::ROL_POSTOR, 'estado' => User::ESTADO_ACTIVO]);
                $user->forceFill(['email_verified_at' => CarbonImmutable::now('UTC')])->save();
                $postor = Postor::create(['user_id' => $user->id, 'nombres' => 'Postor demo', 'apellidos' => (string) $i, 'rut' => $this->rutLibre()]);
                $postor->forceFill(['estado' => Postor::ESTADO_APROBADO, 'revisado_en' => CarbonImmutable::now('UTC')])->save();
                Garantia::paraRemate($remate, $user)->forceFill(['estado' => Garantia::ESTADO_APROBADA, 'revisado_en' => CarbonImmutable::now('UTC')])->save();
                $credenciales[] = [$i, $email, $postor->rut];
            }

            return [$remate, $credenciales];
        });

        $emisor->publicarEstado($remate);

        $primero = $remate->lotes()->first();
        $this->info("Remate de demostración {$remate->folio} creado.");
        $this->line('  Primer lote abre: ' . $primero->abre_en->setTimezone('America/Santiago')->format('d-m-Y H:i') . ' (Santiago)');
        $this->line('  Estado público:   ' . rtrim(config('app.url'), '/') . "/tiempo-real/{$remate->slug}.json");
        $this->line('  Estado (PHP):     ' . rtrim(config('app.url'), '/') . "/remates/{$remate->slug}/estado");
        $this->newLine();
        $this->warn("Clave de TODOS los postores de demostración (se muestra una sola vez): {$clave}");
        $this->table(['#', 'Correo', 'RUT'], $credenciales);

        return self::SUCCESS;
    }

    /** RUT válido que no existe todavía (rango alto para no chocar con RUT reales de prueba). */
    private function rutLibre(): string
    {
        do {
            $cuerpo = (string) random_int(90000000, 99999999);
            $rut = $cuerpo . Rut::digitoVerificador($cuerpo);
        } while (Postor::porRut($rut)->exists());

        return $rut;
    }
}
