<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/** QA 21/10, grupo 2: https forzado por la aplicación y cabeceras de seguridad con CSP. */
class SeguridadTest extends TestCase
{
    use RefreshDatabase;

    public function test_http_se_redirige_a_https_conservando_la_direccion_y_el_metodo(): void
    {
        config(['colliers.seguridad.forzar_https' => true]);

        $this->get('http://localhost/remates/apoquindo?orden=fecha')->assertStatus(301)
            ->assertRedirect('https://localhost/remates/apoquindo?orden=fecha');
        // Un POST no se convierte en GET: 308 conserva método y cuerpo.
        $this->post('http://localhost/ingresar', ['email' => 'a@b.test'])->assertStatus(308)
            ->assertRedirect('https://localhost/ingresar');

        $this->get('https://localhost/')->assertOk()->assertHeader('Strict-Transport-Security', 'max-age=31536000');
        // TLS terminado en un proxy: no hay bucle de redirecciones.
        $this->get('http://localhost/', ['X-Forwarded-Proto' => 'https'])->assertOk();
    }

    public function test_sin_https_forzado_no_redirige_ni_envia_hsts(): void
    {
        config(['colliers.seguridad.forzar_https' => false]);

        $this->get('http://localhost/')->assertOk()->assertHeaderMissing('Strict-Transport-Security');
    }

    public function test_cabeceras_de_seguridad_en_paginas_y_errores(): void
    {
        foreach (['/', '/no-existe-esta-pagina', '/hora'] as $ruta) {
            $respuesta = $this->get($ruta)
                ->assertHeader('X-Content-Type-Options', 'nosniff')
                ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
                ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
                ->assertHeader('Permissions-Policy');
            $csp = $respuesta->headers->get('Content-Security-Policy');
            $this->assertNotNull($csp, "CSP en {$ruta}");
            $this->assertStringContainsString("frame-src https://www.youtube-nocookie.com https://www.youtube.com", $csp);
            $this->assertStringContainsString("connect-src 'self'", $csp);
            $this->assertStringContainsString("frame-ancestors 'self'", $csp);
            $this->assertMatchesRegularExpression("/script-src [^;]*'self'/", $csp);
            $this->assertDoesNotMatchRegularExpression("/script-src [^;]*'unsafe-inline'/", $csp, 'sin scripts en línea');
            $this->assertStringNotContainsString('google.com', $csp, 'sin reCAPTCHA configurado no se abre Google');
            $this->assertStringNotContainsString('gstatic.com', $csp);
            $this->assertStringNotContainsString('cloudflare', $csp, 'Turnstile se quitó (22/10)');
        }
    }

    public function test_los_bloqueos_de_la_csp_quedan_en_el_log_sin_sesion_ni_csrf(): void
    {
        Log::spy();
        $this->call('POST', '/csp-informe', [], [], [], ['CONTENT_TYPE' => 'application/csp-report'], json_encode(['csp-report' => [
            'document-uri' => 'https://localhost/', 'violated-directive' => 'script-src-elem', 'blocked-uri' => 'inline', 'otro' => 'se descarta',
        ]]))->assertNoContent();

        Log::shouldHaveReceived('warning')->once()->with('CSP bloqueó un recurso', ['document-uri' => 'https://localhost/', 'violated-directive' => 'script-src-elem', 'blocked-uri' => 'inline']);
    }

    public function test_csp_abre_recaptcha_solo_si_esta_configurado_y_se_puede_pasar_a_reporte(): void
    {
        config(['services.recaptcha.site_key' => 'clave', 'services.recaptcha.secret_key' => 'secreto']);
        $csp = $this->get('/')->headers->get('Content-Security-Policy');
        $this->assertMatchesRegularExpression('#script-src [^;]*https://www\.google\.com/recaptcha/ https://www\.gstatic\.com/recaptcha/#', $csp);
        $this->assertMatchesRegularExpression('#frame-src [^;]*https://www\.google\.com/recaptcha/ https://recaptcha\.google\.com/recaptcha/#', $csp);
        $this->assertMatchesRegularExpression("#connect-src 'self' https://www\.google\.com/recaptcha/#", $csp);
        $this->assertStringNotContainsString('cloudflare', $csp);

        config(['colliers.seguridad.csp' => 'reporte']);
        $this->get('/')->assertHeaderMissing('Content-Security-Policy')->assertHeader('Content-Security-Policy-Report-Only');
        config(['colliers.seguridad.csp' => 'apagada']);
        $this->get('/')->assertHeaderMissing('Content-Security-Policy')->assertHeaderMissing('Content-Security-Policy-Report-Only');
    }
}
