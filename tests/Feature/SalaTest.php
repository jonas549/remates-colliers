<?php

namespace Tests\Feature;

use App\Models\Garantia;
use App\Models\Lote;
use App\Models\Postor;
use App\Models\Remate;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/** Bloque K · sala de puja: quién entra y qué recibe el navegador. */
class SalaTest extends TestCase
{
    use RefreshDatabase;

    private string $carpeta;

    private Remate $remate;

    private Lote $lote;

    protected function setUp(): void
    {
        parent::setUp();
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-20 15:00:00', 'UTC'));
        $this->carpeta = storage_path('framework/testing/sala-' . getmypid());
        config(['colliers.tiempo_real.carpeta' => $this->carpeta]);
        $this->app->forgetInstance(\App\Subastas\Difusion\Emisor::class);

        $martillero = User::create(['name' => 'M. Ossandón', 'email' => 'm@colliers.test', 'password' => 'x-clave-larga-1', 'rol' => User::ROL_MARTILLERO]);
        $this->remate = Remate::create(['folio' => 'R-2026-114', 'slug' => 'apoquindo', 'titulo' => 'Apoquindo', 'estado' => Remate::ESTADO_PUBLICADO, 'youtube_video_id' => 'jfKfPfyJRdk', 'martillero_id' => $martillero->id]);
        $this->lote = Lote::create([
            'remate_id' => $this->remate->id, 'titulo' => 'Av. Apoquindo 4501, Depto. 1802', 'direccion' => 'Av. Apoquindo 4501, Depto. 1802',
            'comuna' => 'Las Condes', 'region' => 'Región Metropolitana', 'tipo_propiedad' => 'Departamento', 'superficie_util' => 118,
            'dormitorios' => 3, 'banos' => 2, 'ocupacion' => 'Desocupada', 'precio_base' => 185000000,
            'abre_en' => CarbonImmutable::now('UTC')->subMinutes(18), 'cierra_en' => CarbonImmutable::now('UTC')->addMinutes(42),
        ]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->carpeta);
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_sin_sesion_va_al_login_y_otros_roles_no_entran(): void
    {
        $this->get('/remates/apoquindo/sala')->assertRedirect(route('login'));

        $admin = User::create(['name' => 'Admin', 'email' => 'a@colliers.test', 'password' => 'x-clave-larga-1', 'rol' => User::ROL_ADMIN]);
        $this->actingAs($admin)->get('/remates/apoquindo/sala')->assertForbidden();
    }

    public function test_sin_cuenta_o_garantia_aprobadas_para_este_remate_va_a_su_estado_de_cuenta(): void
    {
        $this->actingAs($this->postor('Sin garantía', garantia: null))->get('/remates/apoquindo/sala')->assertRedirect(route('cuenta.estado'));
        $this->actingAs($this->postor('En revisión', garantia: Garantia::ESTADO_EN_REVISION))->get('/remates/apoquindo/sala')->assertRedirect(route('cuenta.estado'));
        $this->actingAs($this->postor('Cuenta', cuenta: Postor::ESTADO_EN_REVISION))->get('/remates/apoquindo/sala')->assertRedirect(route('cuenta.estado'));
    }

    public function test_postor_habilitado_recibe_estado_urls_y_su_alias_sin_identidades_ajenas(): void
    {
        $rival = $this->postor('Rival Secreto', 'rival@correo.test');
        $ana = $this->postor('Ana Postora', 'ana@correo.test');

        $respuesta = $this->actingAs($ana)->get('/remates/apoquindo/sala')->assertOk()
            ->assertSee('salaPuja(', false)
            ->assertSee('Ana Postora · Postor #2')
            ->assertSee('Las Condes, Región Metropolitana · Departamento · 118 m² útiles · 3D / 2B · Desocupada')
            ->assertSee('Precio y cronómetro oficiales: el video tiene 10–30 s de retraso')
            ->assertSee('youtube-nocookie.com/embed/jfKfPfyJRdk', false)
            ->assertDontSee('salaPujaDemo(', false);

        $config = $this->configDe($respuesta->getContent());
        $this->assertSame('apoquindo', $config['remate']);
        $this->assertSame($this->lote->id, $config['loteInicial']);
        $this->assertSame('Postor #2', $config['miAlias']);
        $this->assertSame([100000, 500000, 1000000], $config['pujasRapidas']);
        $this->assertSame((int) CarbonImmutable::now('UTC')->format('Uv'), $config['servidorMs']);
        $this->assertStringEndsWith('/tiempo-real/apoquindo.json', $config['urls']['estadoJson']);
        $this->assertStringEndsWith('/remates/apoquindo/lotes/__LOTE__/pujas', $config['urls']['pujar']);
        $this->assertSame(185000000, $config['estado']['lotes'][0]['puja_minima']);

        // Ni el nombre ni el correo del rival viajan al navegador.
        $this->assertStringNotContainsString('Rival Secreto', $respuesta->getContent());
        $this->assertStringNotContainsString('rival@correo.test', $respuesta->getContent());
        $this->assertFileExists("{$this->carpeta}/apoquindo.json", 'la carga de la sala publica el estado');
    }

    public function test_cargar_la_sala_liquida_el_lote_vencido(): void
    {
        $ana = $this->postor('Ana');
        CarbonImmutable::setTestNow($this->lote->cierra_en->addSeconds(3));

        $respuesta = $this->actingAs($ana)->get('/remates/apoquindo/sala')->assertOk();

        $this->assertSame(Lote::ESTADO_DESIERTO, $this->lote->fresh()->estado);
        $this->assertSame('desierto', $this->configDe($respuesta->getContent())['estado']['lotes'][0]['estado']);
    }

    public function test_remate_en_borrador_no_existe_y_el_modo_demo_solo_es_local(): void
    {
        $ana = $this->postor('Ana');
        $this->actingAs($ana)->get('/remates/apoquindo/sala?demo=1')->assertOk()->assertDontSee('salaPujaDemo(', false);

        $this->remate->update(['estado' => Remate::ESTADO_BORRADOR]);
        $this->actingAs($ana)->get('/remates/apoquindo/sala')->assertNotFound();
    }

    private function configDe(string $html): array
    {
        // @js entrega los arreglos como JSON.parse('…') con comillas escapadas como ".
        $this->assertSame(1, preg_match('/x-data="salaPuja\(JSON\.parse\(\'(.*?)\'\)\)"/s', html_entity_decode($html, ENT_QUOTES), $m));
        $literal = json_decode('"' . $m[1] . '"', flags: JSON_THROW_ON_ERROR);

        return json_decode($literal, true, flags: JSON_THROW_ON_ERROR);
    }

    private function postor(string $nombre, ?string $email = null, ?string $garantia = Garantia::ESTADO_APROBADA, string $cuenta = Postor::ESTADO_APROBADO): User
    {
        static $n = 0;
        $n++;
        $user = User::create(['name' => $nombre, 'email' => $email ?? "p{$n}@correo.test", 'password' => 'x-clave-larga-1', 'rol' => User::ROL_POSTOR]);
        $user->forceFill(['email_verified_at' => now(), 'estado' => User::ESTADO_ACTIVO])->save();
        $cuerpo = (string) (30000000 + $n);
        Postor::create(['user_id' => $user->id, 'nombres' => $nombre, 'apellidos' => 'X', 'rut' => $cuerpo . \App\Support\Rut::digitoVerificador($cuerpo)])
            ->forceFill(['estado' => $cuenta])->save();
        if ($garantia !== null) {
            Garantia::paraRemate($this->remate, $user)->forceFill(['estado' => $garantia])->save();
        }

        return $user->fresh();
    }
}
