<?php

namespace Tests\Feature;

use App\Models\Garantia;
use App\Models\Lote;
use App\Models\Postor;
use App\Models\Remate;
use App\Models\User;
use App\Subastas\MotorPujas;
use Carbon\CarbonImmutable;
use Database\Seeders\DesarrolloSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Tests\TestCase;

/** Remate de demostración para el sandbox y guardas de entorno de los datos de prueba. */
class RemateDemoTest extends TestCase
{
    use RefreshDatabase;

    private string $carpeta;

    protected function setUp(): void
    {
        parent::setUp();
        $this->carpeta = storage_path('framework/testing/demo-' . getmypid());
        config(['colliers.tiempo_real.carpeta' => $this->carpeta]);
        $this->app->forgetInstance(\App\Subastas\Difusion\Emisor::class);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->carpeta);
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_se_niega_en_produccion(): void
    {
        $this->app['env'] = 'production';

        $this->artisan('colliers:remate-demo')->expectsOutputToContain('APP_ENV=staging')->assertFailed();
        $this->assertSame(0, Remate::count());
    }

    public function test_crea_un_remate_marcado_con_postores_habilitados_que_pueden_pujar(): void
    {
        $this->app['env'] = 'staging';
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-20 15:00:20', 'UTC'));

        $this->artisan('colliers:remate-demo', ['--postores' => 3, '--lotes' => 2, '--inicio' => 5, '--duracion' => 10, '--pausa' => 1])
            ->expectsOutputToContain('se muestra una sola vez')->assertSuccessful();

        $remate = Remate::sole();
        $this->assertTrue($remate->es_demostracion);
        $this->assertSame(Remate::ESTADO_PUBLICADO, $remate->estado);
        $this->assertSame(['15:05', '15:15', '15:16', '15:26'], $remate->lotes->flatMap(fn (Lote $l) => [$l->abre_en->format('H:i'), $l->cierra_en->format('H:i')])->all());
        $this->assertSame(3, Postor::where('estado', Postor::ESTADO_APROBADO)->count());
        $this->assertSame(3, Garantia::where('estado', Garantia::ESTADO_APROBADA)->count());
        $this->assertFileExists("{$this->carpeta}/{$remate->slug}.json");

        // Ninguna clave fija: la clave no es la del seeder de desarrollo.
        $usuario = User::where('rol', User::ROL_POSTOR)->first();
        $this->assertFalse(Hash::check('colliers-local-2026', $usuario->password));

        // Un postor de demostración puja cuando abre el lote.
        $lote = $remate->lotes->first();
        app(MotorPujas::class)->pujar($usuario, $lote->id, 100000000, $lote->abre_en->addSecond());
        $this->assertSame(100000000, $lote->fresh()->precio_actual);
    }

    public function test_opciones_fuera_de_rango(): void
    {
        $this->artisan('colliers:remate-demo', ['--postores' => 0])->assertFailed();
        $this->artisan('colliers:remate-demo', ['--lotes' => 11])->assertFailed();
    }

    public function test_seeder_de_desarrollo_se_niega_en_staging(): void
    {
        $this->app['env'] = 'staging';

        $this->expectException(RuntimeException::class);
        $this->app->make(DesarrolloSeeder::class)->run();
    }
}
