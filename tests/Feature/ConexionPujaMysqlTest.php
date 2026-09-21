<?php

namespace Tests\Feature;

use App\Models\Garantia;
use App\Models\Lote;
use App\Models\Postor;
use App\Models\Puja;
use App\Models\Remate;
use App\Models\User;
use App\Subastas\ConexionPuja;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * La puja sobrevive a una conexión RECHAZADA de verdad y conserva su hora de recepción (21/09).
 *
 * Solo MySQL/MariaDB (`composer test:mysql`): la conexión se corta apuntándola a un puerto cerrado —el driver responde
 * «[2002] … refused», el mismo código del sandbox— y se restituye durante la espera del reintento. Con SQLite en memoria,
 * cortar la conexión borra la base.
 *
 * Escenario: la puja LLEGA 0,25 s antes del cierre del lote y la base recién acepta la conexión 0,5 s después, o sea
 * pasado el cierre. Tiene que quedar aceptada con la hora de llegada, porque esa hora la selló PHP antes de Laravel.
 * Sin reintento, la misma puja falla.
 *
 * Usa truncado en vez de transacción: la conexión se reemplaza a mitad de la prueba y una transacción abierta se perdería.
 */
class ConexionPujaMysqlTest extends TestCase
{
    use DatabaseTruncation;

    private string $carpeta;

    protected function setUp(): void
    {
        // Antes de arrancar: el truncado de la base no debe tocar la SQLite en memoria de las demás clases.
        if (! in_array(getenv('DB_CONNECTION'), ['mysql', 'mariadb'], true)) {
            $this->markTestSkipped('Necesita MySQL/MariaDB: composer test:mysql');
        }
        parent::setUp();
        $this->carpeta = storage_path('framework/testing/tiempo-real-conexion-' . getmypid());
        config(['colliers.tiempo_real.carpeta' => $this->carpeta]);
        $this->app->forgetInstance(\App\Subastas\Difusion\Emisor::class);
    }

    protected function tearDown(): void
    {
        if (isset($this->carpeta)) {
            File::deleteDirectory($this->carpeta);
            // Las demás clases usan transacciones sobre la misma base: no dejarles filas.
            $this->truncateTablesForAllConnections();
        }
        parent::tearDown();
    }

    public function test_la_puja_recibida_antes_del_cierre_entra_con_su_hora_aunque_la_base_vuelva_despues(): void
    {
        [$user, $lote, $cierre, $llegada] = $this->escenario();
        $this->cortarConexionHastaLaPrimeraEspera();

        $respuesta = $this->actingAs($user)->withServerVariables(['REQUEST_TIME_FLOAT' => $llegada])
            ->postJson("/remates/corte/lotes/{$lote->id}/pujas", ['monto' => 100000000]);
        $procesada = microtime(true);

        $respuesta->assertCreated()->assertJson(['aceptada' => true]);
        $this->assertGreaterThan($cierre->getTimestamp(), $procesada, 'la base volvió DESPUÉS del cierre');
        $puja = Puja::sole();
        $this->assertSame(sprintf('%.6F', $llegada), $puja->recibida_en->format('U.u'), 'la hora de recepción es la de llegada, no la del reintento');
        $this->assertTrue($puja->recibida_en->lessThan($cierre));
    }

    public function test_sin_reintento_la_misma_puja_falla(): void
    {
        config(['colliers.conexion_puja.reintento_ms' => 0]);
        [$user, $lote, , $llegada] = $this->escenario();
        $this->cortarConexionHastaLaPrimeraEspera();

        $this->actingAs($user)->withServerVariables(['REQUEST_TIME_FLOAT' => $llegada])
            ->postJson("/remates/corte/lotes/{$lote->id}/pujas", ['monto' => 100000000])
            ->assertStatus(500);

        $this->restituirConexion();
        $this->assertSame(0, Puja::count());
    }

    /** @return array{User, Lote, CarbonImmutable, float} */
    private function escenario(): array
    {
        // El lote cierra en el próximo segundo entero (cierra_en no guarda fracciones) con al menos 0,5 s de holgura.
        $cierre = CarbonImmutable::createFromTimestamp((int) ceil(microtime(true) + 0.5), 'UTC');
        $remate = Remate::create(['folio' => 'R-2026-990', 'slug' => 'corte', 'titulo' => 'Corte de conexión', 'estado' => Remate::ESTADO_PUBLICADO]);
        $lote = Lote::create(['remate_id' => $remate->id, 'titulo' => 'Depto.', 'precio_base' => 100000000,
            'abre_en' => $cierre->subMinutes(10), 'cierra_en' => $cierre]);
        $user = User::create(['name' => 'Ana', 'email' => 'ana@correo.test', 'password' => 'clave-de-prueba-123', 'rol' => User::ROL_POSTOR]);
        $postor = Postor::create(['user_id' => $user->id, 'nombres' => 'Ana', 'apellidos' => 'Prueba', 'rut' => '10000001' . \App\Support\Rut::digitoVerificador('10000001')]);
        $postor->forceFill(['estado' => Postor::ESTADO_APROBADO])->save();
        Garantia::paraRemate($remate, $user)->forceFill(['estado' => Garantia::ESTADO_APROBADA])->save();

        // La puja «llega» 0,25 s antes del cierre.
        while (microtime(true) < $cierre->getTimestamp() - 0.25) {
            usleep(10000);
        }

        return [$user, $lote, $cierre, microtime(true)];
    }

    /** La base rechaza conexiones (puerto cerrado) hasta que el reintento espera por primera vez. */
    private function cortarConexionHastaLaPrimeraEspera(): void
    {
        $nombre = (string) config('database.default');
        $puerto = config("database.connections.{$nombre}.port");
        $this->app->instance('prueba.puerto', $puerto);
        DB::purge($nombre);
        config(["database.connections.{$nombre}.port" => 1]);

        $prueba = $this;
        $this->app->instance(ConexionPuja::class, new class($prueba) extends ConexionPuja
        {
            public function __construct(private ConexionPujaMysqlTest $prueba) {}

            protected function esperar(int $ms): void
            {
                $this->prueba->restituirConexion();
                parent::esperar($ms);
            }
        });
    }

    public function restituirConexion(): void
    {
        $nombre = (string) config('database.default');
        if ((int) config("database.connections.{$nombre}.port") === 1) {
            DB::purge($nombre);
            config(["database.connections.{$nombre}.port" => $this->app->make('prueba.puerto')]);
        }
    }
}
