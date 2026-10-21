<?php

namespace Tests\Feature;

use App\Models\Configuracion;
use App\Models\Remate;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\DesarrolloSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/** Sitio en inglés (20/10): prefijo /en, conmutador con cookie, hreflang y el panel siempre en español. */
class IdiomaTest extends TestCase
{
    use RefreshDatabase;

    private string $carpeta;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-10-21 15:00:00', 'UTC'));
        $this->carpeta = storage_path('framework/testing/idioma-' . getmypid());
        config(['colliers.tiempo_real.carpeta' => $this->carpeta]);
        $this->app->forgetInstance(\App\Subastas\Difusion\Emisor::class);
        $this->seed(DesarrolloSeeder::class);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->carpeta);
        parent::tearDown();
    }

    public function test_el_espanol_va_sin_prefijo_y_el_ingles_bajo_en(): void
    {
        $this->get('/')->assertOk()->assertSee('<html lang="es"', false);
        $ingles = $this->get('/en')->assertOk()->assertSee('<html lang="en"', false);

        // Los enlaces internos de una página en inglés siguen en inglés; los externos y el panel no cambian.
        $ingles->assertSee('href="' . url('/en/remates/militares') . '"', false)
            ->assertDontSee('href="' . url('/remates/militares') . '"', false);
        $this->get('/en/remates/militares')->assertOk()->assertSee('href="' . url('/en/ingresar') . '"', false);
    }

    public function test_hreflang_apunta_a_la_misma_pagina_en_los_dos_idiomas(): void
    {
        $this->get('/remates/militares?x=1')->assertOk()
            ->assertSee('<link rel="alternate" hreflang="es" href="' . url('/remates/militares?x=1') . '">', false)
            ->assertSee('<link rel="alternate" hreflang="en" href="' . url('/en/remates/militares?x=1') . '">', false)
            ->assertSee('<link rel="alternate" hreflang="x-default" href="' . url('/remates/militares?x=1') . '">', false);
    }

    public function test_el_conmutador_lleva_a_la_misma_pagina_y_recuerda_la_eleccion(): void
    {
        $this->get('/remates/militares')
            ->assertSee('href="' . e(route('idioma.cambiar', ['idioma' => 'en', 'a' => '/remates/militares'])) . '"', false)
            ->assertSee('<span>ES</span>', false);
        $this->get('/en/remates/militares')
            ->assertSee('href="' . e(route('idioma.cambiar', ['idioma' => 'es', 'a' => '/remates/militares'])) . '"', false)
            ->assertSee('<span>EN</span>', false);

        $this->get('/idioma/en?a=' . urlencode('/remates/militares?sesion=x'))
            ->assertRedirect(url('/en/remates/militares?sesion=x'))
            ->assertPlainCookie('idioma', 'en');
        $this->get('/idioma/es?a=' . urlencode('/remates/militares'))->assertRedirect(url('/remates/militares'))->assertPlainCookie('idioma', 'es');
    }

    public function test_el_conmutador_nunca_lleva_a_otro_sitio(): void
    {
        foreach (['//otro.test/x', 'https://otro.test', '/\\otro.test'] as $a) {
            $destino = $this->get('/idioma/en?a=' . urlencode($a))->headers->get('Location');
            $this->assertStringStartsWith(url('/en'), $destino, $a);
        }
        $this->get('/idioma/fr?a=/')->assertNotFound();
    }

    public function test_la_portada_respeta_la_ultima_eleccion(): void
    {
        $this->withUnencryptedCookie('idioma', 'en')->get('/?orden=fecha')->assertRedirect(url('/en?orden=fecha'));
        $this->withUnencryptedCookie('idioma', 'es')->get('/')->assertOk();
        // Un enlace compartido a otra página en español se respeta siempre.
        $this->withUnencryptedCookie('idioma', 'en')->get('/remates/militares')->assertOk()->assertSee('<html lang="es"', false);
    }

    public function test_el_panel_no_tiene_version_en_ingles(): void
    {
        $admin = User::where('rol', User::ROL_ADMIN)->first();
        $this->actingAs($admin)->get('/en/admin')->assertNotFound();
        $this->actingAs($admin)->get('/admin')->assertOk()->assertSee('<html lang="es"', false)->assertDontSee('hreflang', false);
    }

    public function test_sin_sesion_y_tras_ingresar_se_sigue_en_el_idioma(): void
    {
        $this->get('/en/mi-cuenta')->assertRedirect(url('/en/ingresar'));
        $this->get('/mi-cuenta')->assertRedirect(url('/ingresar'));

        $this->flushSession();
        $this->post('/en/ingresar', ['usuario' => 'mpgonzalez@correo.test', 'password' => 'colliers-local-2026'])
            ->assertRedirectContains('/en/mi-cuenta');
    }

    public function test_los_correos_salen_en_espanol_aunque_se_pidan_desde_en(): void
    {
        Notification::fake();
        $user = User::create(['name' => 'Ana Pérez', 'email' => 'ana@correo.test', 'password' => 'una-clave-larga-1',
            'rol' => User::ROL_POSTOR, 'estado' => User::ESTADO_ACTIVO]);

        $this->actingAs($user)->post('/en/verificar-correo/reenviar')->assertRedirect();

        Notification::assertSentTo($user, VerifyEmail::class, fn ($n, $canales, $destinatario, $idioma) => $idioma === 'es');
        $this->assertSame('es', (new \App\Notifications\RemateNuevoAviso(Remate::first()))->locale);
    }

    public function test_las_paginas_en_ingles_no_dejan_claves_sin_traducir(): void
    {
        $postor = User::where('email', 'mpgonzalez@correo.test')->first();
        $paginas = ['/en', '/en/remates/militares', '/en/remates/apoquindo', '/en/remates/sanmartin', '/en/ingresar', '/en/registro', '/en/recuperar-clave', '/en/no-existe'];
        foreach ($paginas as $url) {
            $html = $this->get($url)->getContent();
            $this->assertDoesNotMatchRegularExpression('/>[^<]*\b(sitio|listado|remate|sala|cuenta|acceso|errores|catalogo|formato)\.[a-z_]+\.[a-z_]+/', $html, $url);
            $this->assertStringContainsString('<html lang="en"', $html, $url);
        }
        foreach (['/en/mi-cuenta', '/en/remates/apoquindo/sala'] as $url) {
            $html = $this->actingAs($postor)->get($url)->assertOk()->getContent();
            $this->assertDoesNotMatchRegularExpression('/>[^<]*\b(sitio|listado|remate|sala|cuenta|acceso|errores|catalogo|formato)\.[a-z_]+\.[a-z_]+/', $html, $url);
        }
    }

    public function test_en_ingles_montos_fechas_y_datos_van_en_su_formato(): void
    {
        $postor = User::where('email', 'mpgonzalez@correo.test')->first();

        // Miles con coma, hora de Santiago explícita, tipo y ocupación traducidos al mostrarlos.
        $this->get('/en/remates/militares')->assertOk()->assertSee('$142,000,000')->assertDontSee('$142.000.000')
            ->assertSee('(Santiago time)')->assertSee('Apartment')->assertSee('Vacant');
        $this->get('/remates/militares')->assertOk()->assertSee('$142.000.000')->assertDontSee('Santiago time')->assertSee('Departamento');

        // El alias del feed y la cabecera de la sala, en el idioma de la página.
        $this->actingAs($postor)->get('/en/remates/apoquindo/sala')->assertOk()->assertSee('· Bidder #1')->assertDontSee('· Postor #1'); // en el JSON de datos sigue «Postor #N»
        $this->actingAs($postor)->get('/remates/apoquindo/sala')->assertOk()->assertSee('· Postor #1');
    }

    public function test_el_conmutador_esta_en_las_cabeceras_sin_menu(): void
    {
        $postor = User::where('email', 'mpgonzalez@correo.test')->first();
        foreach (['/ingresar', '/registro'] as $url) {
            $this->get($url)->assertSee('pub-cabecera__idioma--siempre', false);
        }
        foreach (['/mi-cuenta', '/remates/apoquindo/sala'] as $url) {
            $this->actingAs($postor)->get($url)->assertSee('pub-cabecera__idioma--siempre', false)
                ->assertSee('href="' . e(route('idioma.cambiar', ['idioma' => 'en', 'a' => $url])) . '"', false);
        }
    }

    /** QA 21/10, 3.2: el párrafo de la garantía de «Condiciones del remate» salía en español en /en. */
    public function test_la_condicion_de_garantia_se_traduce_mientras_no_se_edite(): void
    {
        $this->get('/en/remates/militares')->assertOk()->assertSee('The bid deposit is paid by bank draft (vale a la vista) or bank transfer')
            ->assertDontSee('La garantía se constituye por vale a la vista');
        $this->get('/remates/militares')->assertOk()->assertSee('La garantía se constituye por vale a la vista o transferencia');

        // Editado en el panel: es contenido de Colliers y se muestra tal cual en los dos idiomas.
        Configuracion::guardar('texto_condiciones_garantia', 'Garantía solo por transferencia.');
        $this->get('/en/remates/militares')->assertSee('Garantía solo por transferencia.')->assertDontSee('The bid deposit is paid by bank draft');
    }

    /** QA 21/10, 3.4: descripción y Open Graph por remate; el listado y el resto, con los del sitio. El panel, sin ellos. */
    public function test_seo_y_vista_previa_al_compartir(): void
    {
        $this->get('/remates/militares')->assertOk()
            ->assertSee('<meta name="description" content="Remate de Los Militares 5620, Depto. 703 (Las Condes, Departamento). Precio base $142.000.000. Comienza el', false)
            ->assertSee('<meta property="og:title" content="Los Militares 5620, Depto. 703 · Remates Colliers">', false)
            ->assertSee('<meta property="og:url" content="' . url('/remates/militares') . '">', false)
            ->assertSee('<meta property="og:image" content="' . asset('img/demo/prop-militares.jpg') . '">', false)
            ->assertSee('<meta name="twitter:card" content="summary_large_image">', false);
        $this->get('/en/remates/militares')->assertOk()
            ->assertSee('content="Auction of Los Militares 5620, Depto. 703 (Las Condes, Apartment). Starting price $142,000,000. Starts', false)
            ->assertSee('<meta property="og:locale" content="en_US">', false);

        $this->get('/?orden=fecha')->assertOk()
            ->assertSee('<meta name="description" content="' . e(__('sitio.seo.descripcion')) . '">', false)
            ->assertSee('<meta property="og:title" content="Remates · Remates Colliers">', false)
            ->assertSee('<meta property="og:url" content="' . url('/') . '">', false)
            ->assertSee('<meta property="og:image" content="' . asset('img/compartir.png') . '">', false)
            ->assertSee('<meta property="og:image:width" content="475">', false)->assertSee('<meta name="twitter:card" content="summary">', false);

        $admin = User::where('rol', User::ROL_ADMIN)->firstOrFail();
        $this->actingAs($admin)->get('/admin')->assertOk()->assertDontSee('og:title', false)->assertDontSee('name="description"', false);
    }

    public function test_los_documentos_se_marcan_en_espanol_solo_en_ingles(): void
    {
        $remate = Remate::where('slug', 'militares')->firstOrFail();
        \App\Models\Documento::create(['remate_id' => $remate->id, 'titulo' => 'Bases del remate', 'nombre_original' => 'bases.pdf',
            'ruta' => 'documentos/bases.pdf', 'publico' => true, 'tamano_bytes' => 2048]);

        $this->get('/en/remates/militares')->assertSee('Bases del remate (in Spanish)')
            ->assertSee(__('remate.documentos.version_que_rige', [], 'en'));
        $this->get('/remates/militares')->assertSee('Bases del remate')->assertDontSee('(in Spanish)');
    }

    public function test_la_puja_tambien_entra_por_en(): void
    {
        $remate = Remate::where('slug', 'apoquindo')->firstOrFail();
        $lote = $remate->lotes()->orderBy('orden')->first();
        // Andes no va ganando (en el seeder la última puja es de María Paz).
        $postor = User::where('email', 'contacto@andes.test')->first();
        $incremento = (int) ($remate->incremento_minimo ?? Configuracion::valor('incremento_minimo'));

        $this->actingAs($postor)->postJson("/en/remates/{$remate->slug}/lotes/{$lote->id}/pujas", ['monto' => (int) $lote->precio_actual + $incremento])
            ->assertSuccessful();
        $this->assertSame(route('en.pujas.store', [$remate->slug, $lote->id]), url("/en/remates/{$remate->slug}/lotes/{$lote->id}/pujas"));
    }
}
