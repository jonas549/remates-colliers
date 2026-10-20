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
