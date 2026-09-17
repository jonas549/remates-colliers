<?php

namespace Tests\Feature;

use App\Models\Configuracion;
use App\Models\Documento;
use App\Models\Garantia;
use App\Models\Lote;
use App\Models\Postor;
use App\Models\Remate;
use App\Models\User;
use App\Subastas\MotorPujas;
use App\Support\Rut;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Bloque N · sitio público con datos reales: listado, detalle, documentos, calendario y variantes del visitante. */
class SitioPublicoTest extends TestCase
{
    use RefreshDatabase;

    private string $carpeta;

    protected function setUp(): void
    {
        parent::setUp();
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-20 15:00:00', 'UTC'));
        $this->carpeta = storage_path('framework/testing/publico-' . getmypid());
        config(['colliers.tiempo_real.carpeta' => $this->carpeta]);
        $this->app->forgetInstance(\App\Subastas\Difusion\Emisor::class);
        Configuracion::sembrarDefectos();
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->carpeta);
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    private function remate(string $slug, string $estado, int $minutosHastaInicio, array $lote = []): Remate
    {
        $remate = Remate::create([
            'folio' => 'R-' . strtoupper($slug), 'slug' => $slug, 'titulo' => "Remate {$slug}", 'estado' => $estado,
            'inicio_en' => CarbonImmutable::now('UTC')->addMinutes($minutosHastaInicio),
            'cierre_garantias_en' => CarbonImmutable::now('UTC')->addMinutes($minutosHastaInicio)->subHours(48),
        ]);
        Lote::create($lote + [
            'remate_id' => $remate->id, 'titulo' => "Lote {$slug}", 'direccion' => "Los Militares 5620, {$slug}", 'comuna' => 'Las Condes',
            'region' => 'Región Metropolitana', 'tipo_propiedad' => 'Departamento', 'superficie_util' => 96, 'dormitorios' => 3, 'banos' => 2,
            'ocupacion' => 'Desocupada', 'precio_base' => 142000000, 'latitud' => -33.4093, 'longitud' => -70.5772,
            'atributos' => ['mandante' => 'Banco Consorcio', 'rol_avaluo' => '2871-14'],
        ]);
        $remate->programarLotes();

        return $remate;
    }

    private function postor(string $cuenta = Postor::ESTADO_APROBADO): User
    {
        static $n = 0;
        $n++;
        $user = User::create(['name' => "Postor {$n}", 'email' => "p{$n}@correo.test", 'password' => 'x-clave-larga-1', 'rol' => User::ROL_POSTOR, 'estado' => User::ESTADO_ACTIVO]);
        $user->forceFill(['email_verified_at' => now()])->save();
        $cuerpo = (string) (70000000 + $n);
        Postor::create(['user_id' => $user->id, 'nombres' => 'Postor', 'apellidos' => (string) $n, 'rut' => $cuerpo . Rut::digitoVerificador($cuerpo)])->forceFill(['estado' => $cuenta])->save();

        return $user->fresh();
    }

    public function test_listado_con_remates_reales_sin_borradores_cancelados_ni_demostraciones(): void
    {
        $proximo = $this->remate('militares', Remate::ESTADO_PUBLICADO, 3 * 24 * 60);
        $this->remate('apoquindo', Remate::ESTADO_PUBLICADO, -10);
        $this->remate('borrador', Remate::ESTADO_BORRADOR, 60);
        $this->remate('cancelado', Remate::ESTADO_CANCELADO, 60);
        $this->remate('demo', Remate::ESTADO_PUBLICADO, 60)->forceFill(['es_demostracion' => true])->save();
        Configuracion::guardar('uf_valor', '39412.73');
        Configuracion::guardar('uf_fecha', '2026-09-20');

        $respuesta = $this->get('/')->assertOk()->assertSee('$39.412,73')->assertSee('20 de septiembre de 2026');
        $respuesta->assertViewHas('remates', function (array $remates) {
            $porId = collect($remates)->keyBy('id');

            return $porId->keys()->sort()->values()->all() === ['apoquindo', 'militares']
                && $porId['militares']['estado'] === 'Próximo' && $porId['militares']['delta'] === 3 * 86400
                && $porId['militares']['garantia'] === 14200000 && $porId['apoquindo']['estado'] === 'En vivo';
        });
        $respuesta->assertViewHas('hero', fn (array $hero) => $hero['id'] === $proximo->slug && $hero['limiteLargo'] === '21-09-2026, 12:00');
        $respuesta->assertViewHas('mostrarFiltroGarantia', false);
    }

    public function test_detalle_proximo_con_ficha_mapa_visitas_documentos_y_calendario(): void
    {
        Storage::fake('local');
        $remate = $this->remate('militares', Remate::ESTADO_PUBLICADO, 3 * 24 * 60);
        $lote = $remate->lotes()->first();
        $lote->visitas()->create(['inicia_en' => CarbonImmutable::now('UTC')->addDay(), 'termina_en' => CarbonImmutable::now('UTC')->addDay()->addHours(2)]);
        Storage::disk('local')->put('documentos/bases.pdf', '%PDF');
        Storage::disk('local')->put('documentos/dominio.pdf', '%PDF');
        $bases = Documento::create(['remate_id' => $remate->id, 'titulo' => 'Bases especiales del remate', 'ruta' => 'documentos/bases.pdf', 'nombre_original' => 'bases.pdf', 'tamano_bytes' => 420000, 'publico' => true]);
        $reservado = Documento::create(['remate_id' => $remate->id, 'titulo' => 'Certificado de dominio', 'ruta' => 'documentos/dominio.pdf', 'nombre_original' => 'dominio.pdf', 'tamano_bytes' => 90000, 'publico' => false]);

        $this->get('/remates/militares')->assertOk()
            ->assertSee('Los Militares 5620, militares')->assertSee('REMATE N.º')->assertSee('R-MILITARES')
            ->assertSee('Banco Consorcio')->assertSee('2871-14')->assertSee('Abrir en Google Maps')
            ->assertSee('Bases especiales del remate')->assertSee('Con garantía aprobada')
            ->assertSee(route('remates.documento', ['militares', $bases->id]))
            ->assertDontSee(route('remates.documento', ['militares', $reservado->id]))
            ->assertSee(route('remates.calendario', 'militares'));

        $this->get(route('remates.documento', ['militares', $bases->id]))->assertOk()->assertDownload('bases.pdf');
        $this->get(route('remates.documento', ['militares', $reservado->id]))->assertForbidden();
        $habilitado = $this->postor();
        Garantia::paraRemate($remate, $habilitado)->forceFill(['estado' => Garantia::ESTADO_APROBADA])->save();
        $this->actingAs($habilitado)->get(route('remates.documento', ['militares', $reservado->id]))->assertOk();

        $ics = $this->get(route('remates.calendario', 'militares'))->assertOk()->assertHeader('Content-Type', 'text/calendar; charset=utf-8')->getContent();
        $this->assertStringContainsString('DTSTART:20260923T150000Z', $ics);
        $this->assertStringContainsString('SUMMARY:Remate R-MILITARES · Remate militares', $ics);

        $this->get('/remates/no-existe')->assertNotFound();
        $this->remate('borrador', Remate::ESTADO_BORRADOR, 60);
        $this->get('/remates/borrador')->assertNotFound();
    }

    public function test_variantes_del_visitante_segun_su_cuenta_y_garantia_en_ese_remate(): void
    {
        $remate = $this->remate('militares', Remate::ESTADO_PUBLICADO, 3 * 24 * 60);

        $this->get('/remates/militares')->assertSee('Necesitas una cuenta para pujar')->assertSee('Crear cuenta');

        $postor = $this->postor();
        $this->actingAs($postor)->get('/remates/militares')->assertSee('Falta constituir la garantía')
            ->assertSee(route('cuenta.inscribirme', 'militares'), false);

        $garantia = Garantia::paraRemate($remate, $postor);
        $garantia->forceFill(['estado' => Garantia::ESTADO_EN_REVISION])->save();
        $this->actingAs($postor)->get('/remates/militares')->assertSee('Garantía en revisión');
        $this->actingAs($postor)->get('/')->assertSee('Recibimos tu comprobante para Remate militares');

        $garantia->forceFill(['estado' => Garantia::ESTADO_APROBADA])->save();
        $this->actingAs($postor)->get('/remates/militares')->assertSee('Estás habilitado para pujar')->assertSee('Recordarme al comenzar');
    }

    public function test_el_espectador_ve_el_cierre_al_segundo_y_avisa_una_sola_vez(): void
    {
        // A: el cliente deduce el cierre con la hora del servidor; B: un aviso al servidor, configurable desde el panel.
        \App\Models\Configuracion::guardar('cierre_aviso_espera_segundos', '8');
        \App\Models\Configuracion::guardar('cierre_aviso_porcentaje', '40');
        \App\Models\Configuracion::guardar('margen_liquidacion_segundos', '5');

        $remate = $this->remate('apoquindo', Remate::ESTADO_PUBLICADO, -10, ['duracion_segundos' => 3600]);
        $remate->programarLotes();

        $this->get('/remates/apoquindo')->assertOk()->assertViewHas('r', function (array $r) {
            $tr = $r['tiempoReal'];
            $this->assertSame(5000, $tr['margenMs']);
            $this->assertSame(['espera_ms' => 8000, 'porcentaje' => 40], $tr['avisoCierre']);
            $this->assertStringEndsWith('/remates/apoquindo/estado', $tr['estadoUrl']);

            return true;
        });

        // El JSON estático también los trae: una página abierta hace horas se entera de un cambio del panel.
        app(\App\Subastas\Difusion\Emisor::class)->publicarEstado($remate);
        $json = json_decode(File::get($this->carpeta . '/apoquindo.json'), true);
        $this->assertSame(['espera_ms' => 8000, 'porcentaje' => 40], $json['aviso_cierre']);
        $this->assertSame(5000, $json['margen_liquidacion_ms']);

        // En 0 % nadie avisa: el resultado queda para el cron.
        \App\Models\Configuracion::guardar('cierre_aviso_porcentaje', '0');
        app(\App\Subastas\Difusion\Emisor::class)->publicarEstado($remate->fresh());
        $this->assertSame(0, json_decode(File::get($this->carpeta . '/apoquindo.json'), true)['aviso_cierre']['porcentaje']);
    }

    public function test_detalle_en_vivo_lee_solo_el_json_estatico_con_las_pujas_publicas(): void
    {
        $remate = $this->remate('apoquindo', Remate::ESTADO_PUBLICADO, -10, ['duracion_segundos' => 3600]);
        $remate->programarLotes();
        $lote = $remate->lotes()->first();
        $ana = $this->postor();
        Garantia::paraRemate($remate, $ana)->forceFill(['estado' => Garantia::ESTADO_APROBADA])->save();
        app(MotorPujas::class)->pujar($ana, $lote->id, 142000000, CarbonImmutable::now('UTC'));

        $respuesta = $this->get('/remates/apoquindo')->assertOk()->assertSee('REMATE EN CURSO')
            ->assertViewHas('r', fn (array $r) => str_ends_with($r['tiempoReal']['estadoJson'], '/tiempo-real/apoquindo.json')
                && str_ends_with($r['tiempoReal']['hora'], '/hora.php') && $r['tiempoReal']['pujas'][0]['postor'] === 'Postor #1'
                && $r['tiempoReal']['precioActual'] === 142000000);
        $html = stripslashes($respuesta->getContent());
        $this->assertStringNotContainsString('apoquindo/estado', $html, 'los espectadores no llaman al endpoint con PHP');
        $this->assertStringNotContainsString($ana->email, $html);

        // Espectador: mira el precio y el historial, pero no tiene con qué pujar ni puede hacerlo a mano.
        $respuesta->assertSee('Solo puedes mirar este remate')->assertDontSee('Entrar a la sala de pujas');
        $this->assertStringNotContainsString("/lotes/{$lote->id}/pujas", $html);
        $this->postJson("/remates/apoquindo/lotes/{$lote->id}/pujas", ['monto' => 150000000])->assertUnauthorized();
        $this->get('/remates/apoquindo/sala')->assertRedirect(route('login'));
        $sinGarantia = $this->postor();
        $this->actingAs($sinGarantia)->get('/remates/apoquindo')->assertOk()->assertDontSee('Entrar a la sala de pujas');
        $this->actingAs($sinGarantia)->postJson("/remates/apoquindo/lotes/{$lote->id}/pujas", ['monto' => 150000000])
            ->assertStatus(422)->assertJson(['motivo' => 'sin_garantia']);
        $this->assertSame(1, \App\Models\Puja::count());
    }
}
