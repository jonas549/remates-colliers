<?php

namespace Tests\Feature;

use App\Events\LoteLiquidado;
use App\Models\Adjudicacion;
use App\Models\Garantia;
use App\Models\Lote;
use App\Models\Postor;
use App\Models\Puja;
use App\Models\PujaIntento;
use App\Models\Remate;
use App\Models\User;
use App\Subastas\Liquidador;
use App\Subastas\MotorPujas;
use App\Subastas\PujaRechazada;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Bloque J · reglas del motor de pujas y del cierre, probadas de punta a punta por HTTP y sobre el servicio.
 * La concurrencia real (Apache + MySQL, peticiones en paralelo) se prueba aparte: tools/concurrencia.
 */
class MotorPujasTest extends TestCase
{
    use RefreshDatabase;

    private CarbonImmutable $t0;

    private string $carpeta;

    private Remate $remate;

    private Lote $lote;

    protected function setUp(): void
    {
        parent::setUp();

        $this->t0 = CarbonImmutable::parse('2026-09-20 15:00:00', 'UTC');
        CarbonImmutable::setTestNow($this->t0);

        $this->carpeta = storage_path('framework/testing/tiempo-real-' . getmypid());
        File::deleteDirectory($this->carpeta);
        config(['colliers.tiempo_real.carpeta' => $this->carpeta]);
        $this->app->forgetInstance(\App\Subastas\Difusion\Emisor::class);

        // Remate publicado; el lote abrió hace 10 min y cierra a las 15:20:00.
        $this->remate = Remate::create(['folio' => 'R-2026-900', 'slug' => 'prueba', 'titulo' => 'Remate de prueba', 'estado' => Remate::ESTADO_PUBLICADO]);
        $this->lote = Lote::create([
            'remate_id' => $this->remate->id, 'titulo' => 'Depto. de prueba', 'precio_base' => 100000000,
            'abre_en' => $this->t0->subMinutes(10), 'cierra_en' => $this->t0->addMinutes(20),
        ]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->carpeta);
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_puja_valida_actualiza_el_lote_y_publica_el_estado_sin_identidades(): void
    {
        $postor = $this->postorHabilitado('Ana', 'ana@correo.test');

        $this->actingAs($postor)->postJson($this->urlPuja(), ['monto' => 100000000])
            ->assertCreated()
            ->assertJson(['aceptada' => true, 'mi_alias' => 'Postor #1', 'lote' => ['precio_actual' => 100000000, 'puja_minima' => 100100000, 'ganador' => 'Postor #1']]);

        $lote = $this->lote->fresh();
        $this->assertSame(100000000, $lote->precio_actual);
        $this->assertSame($postor->id, $lote->ganador_id);
        $this->assertSame(1, $lote->total_pujas);
        $this->assertSame(Remate::ESTADO_EN_CURSO, $this->remate->fresh()->estado);
        $this->assertSame('2026-09-20 15:00:00.000000', Puja::first()->recibida_en->format('Y-m-d H:i:s.u'));

        $json = File::get($this->carpeta . '/prueba.json');
        $estado = json_decode($json, true);
        $this->assertSame('Postor #1', $estado['lotes'][0]['ganador']);
        $this->assertSame(100000000, $estado['lotes'][0]['pujas'][0]['monto']);
        $this->assertStringNotContainsString('ana@correo.test', $json);
        $this->assertStringNotContainsString('Ana', $json);
        $this->assertStringNotContainsString('"user_id"', $json);
        $this->assertSame([], glob($this->carpeta . '/*.tmp'), 'no quedan temporales');
    }

    public function test_primera_puja_debe_alcanzar_el_precio_base_y_las_siguientes_el_incremento(): void
    {
        [$ana, $beto] = [$this->postorHabilitado('Ana'), $this->postorHabilitado('Beto')];

        $this->pujaHttp($ana, 99999999)->assertStatus(422)->assertJson(['motivo' => 'monto_insuficiente', 'lote' => ['puja_minima' => 100000000]]);
        $this->pujaHttp($ana, 100000000)->assertCreated();
        $this->pujaHttp($beto, 100099999)->assertStatus(422)->assertJson(['motivo' => 'monto_insuficiente', 'lote' => ['puja_minima' => 100100000]]);
        $this->pujaHttp($beto, 100100000)->assertCreated();

        // Incremento propio del remate en vez del global.
        $this->remate->update(['incremento_minimo' => 500000]);
        $this->pujaHttp($ana, 100500000)->assertStatus(422);
        $this->pujaHttp($ana, 100600000)->assertCreated();
    }

    public function test_configuracion_cambiada_en_el_panel_rige_en_pujas_garantias_y_cierre(): void
    {
        \App\Models\Configuracion::sembrarDefectos();
        $admin = User::create(['name' => 'Admin', 'email' => 'admin@colliers.test', 'password' => 'clave-de-prueba-123', 'rol' => User::ROL_ADMIN, 'estado' => User::ESTADO_ACTIVO]);
        $config = [];
        foreach (\App\Models\Configuracion::DEFECTOS as $clave => $datos) {
            if (empty($datos['solo_lectura'])) {
                $valor = \App\Models\Configuracion::valor($clave);
                $config[$clave] = match ($datos['tipo']) {
                    'lista_montos' => implode(', ', (array) $valor), 'booleano' => $valor ? '1' : '0', 'secreto' => '', default => $valor,
                };
            }
        }
        $this->actingAs($admin)->put('/admin/configuracion', ['config' => [
            'incremento_minimo' => '300000', 'porcentaje_garantia' => '5', 'margen_liquidacion_segundos' => '6',
        ] + $config])->assertSessionHas('estado');

        // Incremento: la segunda puja necesita 300.000 sobre la primera.
        [$ana, $beto] = [$this->postorHabilitado('Ana'), $this->postorHabilitado('Beto')];
        $this->pujaHttp($ana, 100000000)->assertCreated()->assertJson(['lote' => ['puja_minima' => 100300000]]);
        $this->pujaHttp($beto, 100200000)->assertStatus(422)->assertJson(['motivo' => 'monto_insuficiente']);
        $this->pujaHttp($beto, 100300000)->assertCreated();

        // Garantía: un remate nuevo inscribe al 5 % de la base.
        $proximo = Remate::create(['folio' => 'R-2026-901', 'slug' => 'proximo', 'titulo' => 'Próximo', 'estado' => Remate::ESTADO_PUBLICADO,
            'inicio_en' => $this->t0->addDays(3), 'cierre_garantias_en' => $this->t0->addDays(2)]);
        Lote::create(['remate_id' => $proximo->id, 'titulo' => 'Casa', 'precio_base' => 80000000, 'abre_en' => $this->t0->addDays(3), 'cierra_en' => $this->t0->addDays(3)->addMinutes(30)]);
        $carla = $this->postorHabilitado('Carla', garantia: null);
        $carla->forceFill(['email_verified_at' => $this->t0, 'estado' => User::ESTADO_ACTIVO])->save();
        $this->actingAs($carla)->post(route('cuenta.inscribirme', $proximo))->assertSessionHasNoErrors();
        $this->assertSame(4000000, Garantia::where('user_id', $carla->id)->where('remate_id', $proximo->id)->sole()->monto);

        // Margen: con 6 s, a los 5 s del cierre todavía no se adjudica; a los 6 s sí.
        $cierre = $this->lote->cierra_en;
        $liquidador = app(Liquidador::class);
        $this->assertFalse($liquidador->liquidarPorId($this->lote->id, $cierre->addSeconds(5)));
        $this->assertTrue($liquidador->liquidarPorId($this->lote->id, $cierre->addSeconds(6)));
        $this->assertSame($beto->id, Adjudicacion::sole()->user_id);
    }

    public function test_sin_garantia_aprobada_o_sin_cuenta_aprobada_se_rechaza(): void
    {
        $sinGarantia = $this->postorHabilitado('Sin garantía', garantia: null);
        $enRevision = $this->postorHabilitado('En revisión', garantia: Garantia::ESTADO_EN_REVISION);
        $cuentaEnRevision = $this->postorHabilitado('Cuenta', cuenta: Postor::ESTADO_EN_REVISION);
        $bloqueado = $this->postorHabilitado('Bloqueado', cuenta: Postor::ESTADO_BLOQUEADO);
        $admin = User::create(['name' => 'Admin', 'email' => 'admin@colliers.test', 'password' => 'clave-de-prueba-123', 'rol' => User::ROL_ADMIN]);

        $this->pujaHttp($sinGarantia, 100000000)->assertStatus(422)->assertJson(['motivo' => 'sin_garantia']);
        $this->pujaHttp($enRevision, 100000000)->assertStatus(422)->assertJson(['motivo' => 'sin_garantia']);
        $this->pujaHttp($cuentaEnRevision, 100000000)->assertStatus(422)->assertJson(['motivo' => 'cuenta_no_habilitada']);
        $this->pujaHttp($bloqueado, 100000000)->assertStatus(422)->assertJson(['motivo' => 'cuenta_no_habilitada']);
        $this->pujaHttp($admin, 100000000)->assertStatus(422)->assertJson(['motivo' => 'cuenta_no_habilitada']);

        $this->assertSame(0, Puja::count());
        $this->assertNull($this->lote->fresh()->precio_actual);
    }

    public function test_garantia_de_otro_remate_no_habilita(): void
    {
        $otro = Remate::create(['folio' => 'R-2026-901', 'slug' => 'otro', 'titulo' => 'Otro', 'estado' => Remate::ESTADO_PUBLICADO]);
        $postor = $this->postorHabilitado('Ana', garantia: null);
        Garantia::create(['user_id' => $postor->id, 'remate_id' => $otro->id, 'monto' => 1, 'porcentaje' => '10', 'base_calculo' => 10])
            ->forceFill(['estado' => Garantia::ESTADO_APROBADA])->save();

        $this->pujaHttp($postor, 100000000)->assertStatus(422)->assertJson(['motivo' => 'sin_garantia']);
    }

    public function test_quien_va_ganando_no_puede_superarse_a_si_mismo(): void
    {
        $ana = $this->postorHabilitado('Ana');
        $this->pujaHttp($ana, 100000000)->assertCreated();

        $this->pujaHttp($ana, 120000000)->assertStatus(422)->assertJson(['motivo' => 'ya_vas_ganando']);
        $this->assertSame(1, Puja::count());
    }

    public function test_validez_por_hora_de_recepcion_y_sin_anti_sniping(): void
    {
        [$ana, $beto] = [$this->postorHabilitado('Ana'), $this->postorHabilitado('Beto')];
        $cierre = $this->lote->cierra_en;
        $motor = app(MotorPujas::class);

        // Recibida 1 ms antes del cierre y procesada 1,5 s después (dentro del margen): válida.
        CarbonImmutable::setTestNow($cierre->addMilliseconds(1500));
        $motor->pujar($ana, $this->lote->id, 100000000, $cierre->subMillisecond());
        $this->assertSame(100000000, $this->lote->fresh()->precio_actual);
        $this->assertTrue($this->lote->fresh()->cierra_en->equalTo($cierre), 'una puja de último segundo no extiende el cierre');

        // Recibida exactamente en el cierre: rechazada.
        $this->assertRechazo('lote_cerrado', fn () => $motor->pujar($beto, $this->lote->id, 110000000, $cierre));

        // Por HTTP, después del cierre.
        $this->pujaHttp($beto, 110000000)->assertStatus(422)->assertJson(['motivo' => 'lote_cerrado']);
        $this->assertSame(1, Puja::count());
    }

    public function test_antes_de_abrir_se_rechaza(): void
    {
        $this->lote->update(['abre_en' => $this->t0->addMinute()]);

        $this->pujaHttp($this->postorHabilitado('Ana'), 100000000)->assertStatus(422)->assertJson(['motivo' => 'lote_no_abierto']);
    }

    public function test_remate_en_borrador_o_cancelado_no_acepta_pujas(): void
    {
        $ana = $this->postorHabilitado('Ana');
        foreach ([Remate::ESTADO_BORRADOR, Remate::ESTADO_CANCELADO, Remate::ESTADO_FINALIZADO] as $estado) {
            $this->remate->update(['estado' => $estado]);
            $this->pujaHttp($ana, 100000000)->assertStatus(422)->assertJson(['motivo' => 'remate_no_disponible']);
        }
    }

    public function test_montos_mal_formados_se_rechazan(): void
    {
        $ana = $this->postorHabilitado('Ana');

        foreach (['100.000.000', '1e8', '100000000.0', '-100000000', '0', '', 'abc', 100000000.5, null, ['100000000']] as $monto) {
            $this->pujaHttp($ana, $monto)->assertStatus(422)->assertJson(['motivo' => 'monto_invalido']);
        }
        $this->pujaHttp($ana, '100000000')->assertCreated();
    }

    public function test_los_rechazos_quedan_registrados_fuera_de_la_transaccion(): void
    {
        $ana = $this->postorHabilitado('Ana');

        $this->withHeader('User-Agent', 'Prueba/1.0')->pujaHttp($ana, 1)->assertStatus(422);

        $intento = PujaIntento::sole();
        $this->assertSame('monto_insuficiente', $intento->motivo);
        $this->assertSame($this->lote->id, $intento->lote_id);
        $this->assertSame(1, $intento->monto);
        $this->assertSame(100000000, $intento->detalle['minima']);
        $this->assertSame('Prueba/1.0', $intento->user_agent);
        $this->assertNotNull($intento->ip);
    }

    public function test_lote_de_otro_remate_se_trata_como_inexistente(): void
    {
        $otro = Remate::create(['folio' => 'R-2026-902', 'slug' => 'otro', 'titulo' => 'Otro', 'estado' => Remate::ESTADO_PUBLICADO]);
        $ajeno = Lote::create(['remate_id' => $otro->id, 'titulo' => 'Ajeno', 'precio_base' => 1, 'abre_en' => $this->t0->subHour(), 'cierra_en' => $this->t0->addHour()]);

        $this->actingAs($this->postorHabilitado('Ana'))
            ->postJson("/remates/prueba/lotes/{$ajeno->id}/pujas", ['monto' => 100000000])
            ->assertStatus(422)->assertJson(['motivo' => 'lote_inexistente', 'lote' => null]);
    }

    public function test_sin_sesion_responde_401(): void
    {
        $this->postJson($this->urlPuja(), ['monto' => 100000000])->assertUnauthorized();
    }

    public function test_limite_de_pujas_por_minuto(): void
    {
        config(['colliers.limites.pujas_por_minuto' => 3]);
        $ana = $this->postorHabilitado('Ana');

        foreach (range(1, 3) as $_) {
            $this->pujaHttp($ana, 1)->assertStatus(422);
        }
        $this->pujaHttp($ana, 1)->assertTooManyRequests();
    }

    public function test_cierre_perezoso_adjudica_pasado_el_margen_de_forma_idempotente(): void
    {
        Event::fake([LoteLiquidado::class]);
        [$ana, $beto] = [$this->postorHabilitado('Ana'), $this->postorHabilitado('Beto')];
        $this->pujaHttp($ana, 100000000)->assertCreated();
        $this->pujaHttp($beto, 105000000)->assertCreated();
        $liquidador = app(Liquidador::class);
        $cierre = $this->lote->cierra_en;

        // Entre T y T + margen: cerrado para pujar, pero aún sin adjudicar.
        $this->assertFalse($liquidador->liquidarPorId($this->lote->id, $cierre->addMilliseconds(1999)));
        $this->assertSame(Lote::ESTADO_ABIERTO, $this->lote->fresh()->estado);

        $this->assertTrue($liquidador->liquidarPorId($this->lote->id, $cierre->addSeconds(2)));
        $this->assertFalse($liquidador->liquidarPorId($this->lote->id, $cierre->addSeconds(3)), 'la segunda vez no hace nada');

        $lote = $this->lote->fresh();
        $this->assertSame(Lote::ESTADO_ADJUDICADO, $lote->estado);
        $this->assertSame(Lote::CIERRE_TIEMPO, $lote->motivo_cierre);
        $adjudicacion = Adjudicacion::sole();
        $this->assertSame($beto->id, $adjudicacion->user_id);
        $this->assertSame(105000000, $adjudicacion->monto);
        $this->assertSame(Puja::orderByDesc('id')->first()->id, $adjudicacion->puja_id);
        $this->assertSame(Remate::ESTADO_FINALIZADO, $this->remate->fresh()->estado);
        Event::assertDispatchedTimes(LoteLiquidado::class, 1);

        $estado = json_decode(File::get($this->carpeta . '/prueba.json'), true);
        $this->assertSame('adjudicado', $estado['lotes'][0]['estado']);
    }

    public function test_lote_sin_pujas_queda_desierto(): void
    {
        CarbonImmutable::setTestNow($this->lote->cierra_en->addSeconds(2));

        $this->artisan('colliers:liquidar')->expectsOutputToContain('Lotes liquidados: 1')->assertSuccessful();

        $this->assertSame(Lote::ESTADO_DESIERTO, $this->lote->fresh()->estado);
        $this->assertSame(0, Adjudicacion::count());
    }

    public function test_varios_lotes_con_horario_fijo_y_remate_finalizado_al_liquidar_el_ultimo(): void
    {
        [$ana, $beto] = [$this->postorHabilitado('Ana'), $this->postorHabilitado('Beto')];
        $this->remate->update(['inicio_en' => $this->t0->subMinutes(10), 'duracion_lote_segundos' => 1800, 'pausa_entre_lotes_segundos' => 120]);
        $segundo = Lote::create(['remate_id' => $this->remate->id, 'orden' => 2, 'titulo' => 'Segundo', 'precio_base' => 50000000]);
        $this->remate->programarLotes();
        [$primero, $segundo] = [$this->lote->fresh(), $segundo->fresh()];
        $liquidador = app(Liquidador::class);
        $urlSegundo = "/remates/prueba/lotes/{$segundo->id}/pujas";

        // Mientras corre el primero, el segundo no acepta pujas.
        $this->actingAs($ana)->postJson($urlSegundo, ['monto' => 50000000])->assertStatus(422)->assertJson(['motivo' => 'lote_no_abierto']);
        $this->pujaHttp($ana, 100000000)->assertCreated();

        // Primer lote liquidado: el remate sigue en curso.
        $this->assertTrue($liquidador->liquidarPorId($primero->id, $primero->cierra_en->addSeconds(2)));
        $this->assertSame(Remate::ESTADO_EN_CURSO, $this->remate->fresh()->estado);

        // El segundo abre a su hora (cierre del primero + pausa) y acepta pujas.
        CarbonImmutable::setTestNow($segundo->abre_en);
        $this->actingAs($beto)->postJson($urlSegundo, ['monto' => 50000000])->assertCreated();

        // Último lote liquidado: remate finalizado.
        CarbonImmutable::setTestNow($segundo->cierra_en->addSeconds(2));
        $this->artisan('colliers:liquidar')->assertSuccessful();
        $this->assertSame(Remate::ESTADO_FINALIZADO, $this->remate->fresh()->estado);
        $this->assertSame([$ana->id, $beto->id], Adjudicacion::orderBy('lote_id')->pluck('user_id')->all());
    }

    public function test_endpoint_de_estado_liquida_lo_vencido_y_no_se_cachea(): void
    {
        $this->pujaHttp($this->postorHabilitado('Ana'), 100000000)->assertCreated();
        CarbonImmutable::setTestNow($this->lote->cierra_en->addSeconds(5));

        $respuesta = $this->getJson('/remates/prueba/estado')->assertOk()
            ->assertJsonPath('lotes.0.estado', 'adjudicado')
            ->assertJsonPath('lotes.0.ganador', 'Postor #1')
            ->assertJsonPath('estado', 'finalizado');
        $this->assertStringContainsString('no-store', $respuesta->headers->get('Cache-Control'));

        $this->remate->update(['estado' => Remate::ESTADO_BORRADOR]);
        $this->getJson('/remates/prueba/estado')->assertNotFound();
    }

    public function test_hora_del_servidor(): void
    {
        $this->getJson('/hora')->assertOk()->assertExactJson(['servidor_ms' => (int) $this->t0->format('Uv')]);
    }

    public function test_cierre_anticipado_adjudica_la_mejor_puja_y_corta_las_posteriores(): void
    {
        [$ana, $beto] = [$this->postorHabilitado('Ana'), $this->postorHabilitado('Beto')];
        $martillero = User::create(['name' => 'M. Ossandón', 'email' => 'm@colliers.test', 'password' => 'clave-de-prueba-123', 'rol' => User::ROL_MARTILLERO]);
        $otroMartillero = User::create(['name' => 'C. Vergara', 'email' => 'c@colliers.test', 'password' => 'clave-de-prueba-123', 'rol' => User::ROL_MARTILLERO]);
        $this->remate->update(['martillero_id' => $martillero->id]);
        $this->pujaHttp($ana, 100000000)->assertCreated();
        $url = "/admin/remates/prueba/lotes/{$this->lote->id}/cerrar";

        $this->actingAs($beto)->postJson($url)->assertForbidden();
        $this->actingAs($otroMartillero)->postJson($url)->assertForbidden();

        CarbonImmutable::setTestNow($this->t0->addMinutes(5)->addMilliseconds(700));
        $this->actingAs($martillero)->postJson($url)->assertOk()->assertJson(['cerrado' => true]);
        $this->actingAs($martillero)->postJson($url)->assertStatus(422);

        $lote = $this->lote->fresh();
        $this->assertSame('2026-09-20 15:05:00', $lote->cierra_en->format('Y-m-d H:i:s'));
        $this->assertSame(Lote::CIERRE_ANTICIPADO, $lote->motivo_cierre);
        $this->assertSame($martillero->id, $lote->cerrado_por_id);

        $this->pujaHttp($beto, 110000000)->assertStatus(422)->assertJson(['motivo' => 'lote_cerrado']);

        $this->assertTrue(app(Liquidador::class)->liquidarPorId($this->lote->id, $lote->cierra_en->addSeconds(2)));
        $this->assertSame($ana->id, Adjudicacion::sole()->user_id);
        $this->assertSame(Lote::CIERRE_ANTICIPADO, Adjudicacion::sole()->motivo_cierre);
    }

    public function test_mensaje_del_martillero_se_difunde(): void
    {
        $admin = User::create(['name' => 'Admin', 'email' => 'admin@colliers.test', 'password' => 'clave-de-prueba-123', 'rol' => User::ROL_ADMIN]);

        $this->actingAs($admin)->postJson('/admin/remates/prueba/mensaje', ['texto' => 'Quedan 5 minutos'])->assertOk();

        $estado = json_decode(File::get($this->carpeta . '/prueba.json'), true);
        $this->assertSame('Quedan 5 minutos', $estado['mensaje_martillero']['texto']);
    }

    public function test_vaciar_la_cache_a_mitad_del_remate_no_altera_el_estado(): void
    {
        [$ana, $beto] = [$this->postorHabilitado('Ana'), $this->postorHabilitado('Beto')];
        $this->pujaHttp($ana, 100000000)->assertCreated();

        Cache::flush();
        $this->artisan('optimize:clear')->assertSuccessful();

        $this->pujaHttp($beto, 100050000)->assertStatus(422)->assertJson(['lote' => ['puja_minima' => 100100000, 'ganador' => 'Postor #1']]);
        $this->pujaHttp($beto, 100100000)->assertCreated();
        $this->assertSame($beto->id, $this->lote->fresh()->ganador_id);
    }

    public function test_deploy_se_bloquea_con_remate_en_curso_o_por_comenzar(): void
    {
        $this->artisan('colliers:puede-desplegar')->expectsOutputToContain('R-2026-900')->assertExitCode(75);

        // Faltan 2 horas para que abra: se puede desplegar.
        $this->lote->update(['abre_en' => $this->t0->addHours(2), 'cierra_en' => $this->t0->addHours(3)]);
        $this->artisan('colliers:puede-desplegar')->assertExitCode(0);

        // Faltan 20 minutos (ventana de 30): bloquea.
        $this->lote->update(['abre_en' => $this->t0->addMinutes(20), 'cierra_en' => $this->t0->addMinutes(50)]);
        $this->artisan('colliers:puede-desplegar')->assertExitCode(75);

        // Liquidado: se puede desplegar.
        $this->lote->forceFill(['estado' => Lote::ESTADO_DESIERTO])->save();
        $this->artisan('colliers:puede-desplegar')->assertExitCode(0);
    }

    private function postorHabilitado(string $nombre, ?string $email = null, ?string $garantia = Garantia::ESTADO_APROBADA, string $cuenta = Postor::ESTADO_APROBADO): User
    {
        static $n = 0;
        $n++;
        $user = User::create(['name' => $nombre, 'email' => $email ?? "postor{$n}@correo.test", 'password' => 'clave-de-prueba-123', 'rol' => User::ROL_POSTOR]);
        $cuerpo = (string) (10000000 + $n);
        $postor = Postor::create(['user_id' => $user->id, 'nombres' => $nombre, 'apellidos' => 'Prueba', 'rut' => $cuerpo . \App\Support\Rut::digitoVerificador($cuerpo)]);
        $postor->forceFill(['estado' => $cuenta])->save();
        if ($garantia !== null) {
            Garantia::paraRemate($this->remate, $user)->forceFill(['estado' => $garantia])->save();
        }

        return $user;
    }

    private function urlPuja(): string
    {
        return "/remates/prueba/lotes/{$this->lote->id}/pujas";
    }

    private function pujaHttp(User $user, mixed $monto): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($user)->postJson($this->urlPuja(), ['monto' => $monto]);
    }

    private function assertRechazo(string $motivo, callable $accion): void
    {
        try {
            $accion();
            $this->fail("Se esperaba el rechazo {$motivo}");
        } catch (PujaRechazada $e) {
            $this->assertSame($motivo, $e->motivo);
        }
    }
}
