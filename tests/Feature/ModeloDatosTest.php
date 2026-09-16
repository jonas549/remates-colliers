<?php

namespace Tests\Feature;

use App\Models\Configuracion;
use App\Models\Empresa;
use App\Models\Garantia;
use App\Models\Lote;
use App\Models\Postor;
use App\Models\Puja;
use App\Models\Remate;
use App\Models\User;
use App\Support\Rut;
use Carbon\CarbonImmutable;
use Database\Seeders\DesarrolloSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use LogicException;
use RuntimeException;
use Tests\TestCase;

/** Bloque C · modelo de datos: RUT cifrado, fechas UTC, pujas inmutables, garantías y horario de lotes. */
class ModeloDatosTest extends TestCase
{
    use RefreshDatabase;

    public function test_rut_valida_digito_verificador_y_normaliza(): void
    {
        $this->assertTrue(Rut::esValido('15.482.331-K'));
        $this->assertTrue(Rut::esValido('15482331k'));
        $this->assertTrue(Rut::esValido('76543210-3'));
        $this->assertTrue(Rut::esValido('11.111.111-1'));
        $this->assertFalse(Rut::esValido('15.482.331-2'));
        $this->assertFalse(Rut::esValido('abc'));
        $this->assertFalse(Rut::esValido(''));

        $this->assertSame('15482331K', Rut::normalizar(' 15.482.331-k '));
        $this->assertSame('15.482.331-K', Rut::formatear('15482331k'));
        $this->assertSame('9.884.112-1', Rut::formatear('098841121'));
    }

    public function test_indice_ciego_no_depende_del_formato_ni_expone_el_rut(): void
    {
        $indice = Rut::indiceCiego('15.482.331-K');

        $this->assertSame($indice, Rut::indiceCiego('15482331k'));
        $this->assertSame(64, strlen($indice));
        $this->assertNotSame(hash('sha256', '15482331K'), $indice, 'sin clave sería reversible por fuerza bruta');
        $this->assertNotSame($indice, Rut::indiceCiego('17.998.221-8'));
    }

    public function test_rut_del_postor_se_guarda_cifrado_y_se_busca_por_indice(): void
    {
        $postor = $this->postor('15482331k');

        $crudo = DB::table('postores')->where('id', $postor->id)->value('rut');
        $this->assertStringNotContainsString('15482331', $crudo);
        $this->assertStringNotContainsString('15.482.331', $crudo);
        $this->assertSame('15.482.331-K', $postor->fresh()->rut);

        $this->assertTrue(Postor::porRut('15.482.331-K')->first()->is($postor));
        $this->assertNull(Postor::porRut('17.998.221-8')->first());
        $this->assertNull(Postor::porRut('no-es-rut')->first());
    }

    public function test_un_rut_no_puede_repetirse_entre_postores(): void
    {
        $this->postor('15.482.331-K');

        $this->expectException(QueryException::class);
        $this->postor('15482331K');
    }

    public function test_rut_con_formato_invalido_no_se_guarda(): void
    {
        $this->expectException(RuntimeException::class);
        $this->postor('no-es-rut');
    }

    public function test_empresa_tambien_cifra_su_rut(): void
    {
        $empresa = Empresa::create(['rut' => '76543210-3', 'razon_social' => 'Inversiones Andes SpA']);

        $this->assertStringNotContainsString('76543210', DB::table('empresas')->value('rut'));
        $this->assertTrue(Empresa::porRut('76.543.210-3')->first()->is($empresa));
    }

    public function test_fechas_se_guardan_en_utc_aunque_lleguen_en_hora_de_santiago(): void
    {
        $remate = $this->remate(['inicio_en' => CarbonImmutable::parse('2026-09-09 12:00', 'America/Santiago')]);

        // 12:00 en Santiago (UTC-3 en septiembre) = 15:00 UTC.
        $this->assertSame('2026-09-09 15:00:00', DB::table('remates')->where('id', $remate->id)->value('inicio_en'));
        $this->assertSame('UTC', $remate->fresh()->inicio_en->timezone->getName());
    }

    public function test_puja_guarda_microsegundos_y_no_se_edita_ni_se_borra(): void
    {
        $lote = $this->lote($this->remate());
        $puja = Puja::create([
            'lote_id' => $lote->id, 'user_id' => $this->postor('15482331K')->user_id, 'monto' => 142000000,
            'recibida_en' => CarbonImmutable::parse('2026-09-09 15:29:59.987654', 'UTC'),
        ]);

        $this->assertSame('2026-09-09 15:29:59.987654', $puja->fresh()->recibida_en->format('Y-m-d H:i:s.u'));

        try {
            $puja->update(['monto' => 1]);
            $this->fail('La puja se editó');
        } catch (LogicException) {
        }
        try {
            $puja->delete();
            $this->fail('La puja se borró');
        } catch (LogicException) {
        }
        $this->assertSame(142000000, Puja::find($puja->id)->monto);
    }

    public function test_garantia_es_por_remate_sobre_la_suma_de_bases_y_redondea_hacia_arriba(): void
    {
        $remate = $this->remate();
        $this->lote($remate, ['orden' => 1, 'precio_base' => 100000000]);
        $this->lote($remate, ['orden' => 2, 'precio_base' => 23456789]);

        // 10 % por defecto sobre 123.456.789 = 12.345.678,9 → 12.345.679
        $this->assertSame(123456789, $remate->baseGarantia());
        $this->assertSame(12345679, $remate->montoGarantia());

        $remate->update(['porcentaje_garantia' => '7.5']);
        $this->assertSame(9259260, $remate->montoGarantia());

        $garantia = Garantia::paraRemate($remate, $this->postor('15482331K')->user);
        $this->assertSame(9259260, $garantia->monto);
        $this->assertSame(123456789, $garantia->base_calculo);
        $this->assertSame('pendiente', $garantia->fresh()->estado);

        // Un cambio posterior de configuración no altera la garantía ya creada.
        $remate->update(['porcentaje_garantia' => '10']);
        $this->assertSame(9259260, $garantia->fresh()->monto);
    }

    public function test_lotes_con_horario_fijo_desde_el_inicio_del_remate(): void
    {
        $remate = $this->remate([
            'inicio_en' => CarbonImmutable::parse('2026-09-09 15:00', 'UTC'),
            'duracion_lote_segundos' => 1800,
            'pausa_entre_lotes_segundos' => 300,
        ]);
        $primero = $this->lote($remate, ['orden' => 1]);
        $segundo = $this->lote($remate, ['orden' => 2, 'duracion_segundos' => 600]);
        $tercero = $this->lote($remate, ['orden' => 3]);

        $remate->programarLotes();

        $this->assertSame(['15:00', '15:30'], [$primero->fresh()->abre_en->format('H:i'), $primero->fresh()->cierra_en->format('H:i')]);
        $this->assertSame(['15:35', '15:45'], [$segundo->fresh()->abre_en->format('H:i'), $segundo->fresh()->cierra_en->format('H:i')]);
        $this->assertSame(['15:50', '16:20'], [$tercero->fresh()->abre_en->format('H:i'), $tercero->fresh()->cierra_en->format('H:i')]);

        $this->assertFalse($primero->fresh()->vencido(CarbonImmutable::parse('2026-09-09 15:29:59', 'UTC')));
        $this->assertTrue($primero->fresh()->vencido(CarbonImmutable::parse('2026-09-09 15:30:00', 'UTC')));
    }

    public function test_campos_del_motor_no_se_asignan_en_masa(): void
    {
        $lote = $this->lote($this->remate());
        $lote->fill(['precio_actual' => 1, 'ganador_id' => 1, 'total_pujas' => 99, 'cerrado_en' => now()])->save();

        $lote->refresh();
        $this->assertNull($lote->precio_actual);
        $this->assertNull($lote->ganador_id);
        $this->assertSame(0, $lote->total_pujas);
        $this->assertNull($lote->cerrado_en);
    }

    public function test_configuracion_usa_defectos_del_acta_y_respeta_cambios_del_panel(): void
    {
        $this->assertSame(100000, Configuracion::valor('incremento_minimo'));
        $this->assertSame([100000, 500000, 1000000], Configuracion::valor('pujas_rapidas'));
        $this->assertSame(2, Configuracion::valor('margen_liquidacion_segundos'));
        $this->assertSame('10', Configuracion::valor('porcentaje_garantia'));

        $this->artisan('colliers:instalar')->assertSuccessful();
        Configuracion::where('clave', 'incremento_minimo')->update(['valor' => '250000']);
        $this->artisan('colliers:instalar')->assertSuccessful();

        $this->assertSame(count(Configuracion::DEFECTOS), Configuracion::count());
        $this->assertSame(250000, Configuracion::valor('incremento_minimo'));
        $this->assertSame(250000, $this->remate()->incrementoMinimo());
        $this->assertSame(500000, $this->remate(['folio' => 'R-2', 'slug' => 'r-2', 'incremento_minimo' => 500000])->incrementoMinimo());
    }

    public function test_seeder_de_desarrollo_crea_un_catalogo_coherente(): void
    {
        $this->seed(DesarrolloSeeder::class);

        $this->assertSame(10, Remate::count());
        $this->assertSame(1, Remate::where('estado', Remate::ESTADO_EN_CURSO)->count());

        $enVivo = Lote::where('estado', Lote::ESTADO_ABIERTO)->first();
        $ultima = $enVivo->pujas()->orderByDesc('recibida_en')->first();
        $this->assertSame($ultima->monto, $enVivo->precio_actual);
        $this->assertSame($ultima->user_id, $enVivo->ganador_id);
        $this->assertTrue($enVivo->cierra_en->isFuture());

        $adjudicado = Lote::where('estado', Lote::ESTADO_ADJUDICADO)->first();
        $this->assertSame(88000000, $adjudicado->adjudicacion->monto);
        $this->assertSame(0, Lote::where('estado', Lote::ESTADO_DESIERTO)->first()->pujas()->count());

        // Manda el acta: garantía = 10 % de la base.
        $this->assertSame(0, Garantia::query()->get()->filter(fn ($g) => $g->monto !== intdiv($g->base_calculo, 10))->count());
        $this->assertNotNull(Lote::whereNotNull('lote_origen_id')->first());
    }

    public function test_seeder_de_desarrollo_se_niega_a_correr_en_produccion(): void
    {
        $this->app['env'] = 'production';

        // Directo al seeder: `db:seed` en producción pregunta antes, y lo que se prueba es la guarda propia.
        $this->expectException(RuntimeException::class);
        $this->app->make(DesarrolloSeeder::class)->run();
    }

    private function postor(string $rut): Postor
    {
        static $n = 0;
        $n++;
        $user = User::create(['name' => "Postor {$n}", 'email' => "postor{$n}@correo.test", 'password' => 'clave-de-prueba-123', 'rol' => User::ROL_POSTOR]);

        return Postor::create(['user_id' => $user->id, 'nombres' => 'Postor', 'apellidos' => "Número {$n}", 'rut' => $rut]);
    }

    private function remate(array $datos = []): Remate
    {
        return Remate::create($datos + ['folio' => 'R-2026-001', 'slug' => 'r-2026-001', 'titulo' => 'Remate de prueba']);
    }

    private function lote(Remate $remate, array $datos = []): Lote
    {
        return Lote::create($datos + ['remate_id' => $remate->id, 'titulo' => 'Lote de prueba', 'precio_base' => 142000000]);
    }
}
