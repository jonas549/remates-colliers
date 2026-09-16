<?php

namespace Tests\Feature;

use App\Http\Middleware\AccesoSandbox;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Bloque B · infraestructura de deploy: comandos, clave del sandbox y páginas de error. */
class InfraestructuraTest extends TestCase
{
    use RefreshDatabase;

    public function test_instalar_crea_el_primer_administrador_una_sola_vez(): void
    {
        config([
            'colliers.admin.email' => 'admin@colliers.test',
            'colliers.admin.clave' => 'una-clave-muy-larga',
            'colliers.admin.nombre' => 'Admin Prueba',
        ]);

        $this->artisan('colliers:instalar')->assertSuccessful();
        $this->artisan('colliers:instalar')->assertSuccessful();

        $this->assertSame(1, User::where('rol', User::ROL_ADMIN)->count());
        $admin = User::where('email', 'admin@colliers.test')->first();
        $this->assertTrue($admin->debe_cambiar_clave);
        $this->assertNotSame('una-clave-muy-larga', $admin->password);
    }

    public function test_instalar_no_crea_administrador_con_clave_corta(): void
    {
        config(['colliers.admin.email' => 'admin@colliers.test', 'colliers.admin.clave' => 'corta']);

        $this->artisan('colliers:instalar')->assertSuccessful();

        $this->assertSame(0, User::count());
    }

    public function test_puede_desplegar_responde_75_con_bloqueo_manual(): void
    {
        $archivo = storage_path('framework/testing/bloquear-deploy');
        @mkdir(dirname($archivo), 0777, true);
        config(['colliers.bloqueo_deploy' => $archivo]);
        @unlink($archivo);

        $this->artisan('colliers:puede-desplegar')->assertExitCode(0);

        file_put_contents($archivo, 'mantención');
        $this->artisan('colliers:puede-desplegar')->assertExitCode(75);
        unlink($archivo);
    }

    public function test_diagnostico_se_ejecuta_y_revisa_la_base(): void
    {
        $this->artisan('colliers:diagnostico')->expectsOutputToContain('Base de datos');
    }

    public function test_sin_clave_de_acceso_el_sitio_queda_abierto(): void
    {
        config(['colliers.acceso.clave' => null]);

        $this->get('/ingresar')->assertOk();
    }

    public function test_con_clave_de_acceso_redirige_y_protege_admin(): void
    {
        config(['colliers.acceso.clave' => 'clave-sandbox']);

        $this->get('/admin')->assertRedirect(route('acceso.formulario'));
        $this->get('/acceso')->assertOk()->assertSee('Acceso restringido');
    }

    public function test_clave_incorrecta_no_da_acceso(): void
    {
        config(['colliers.acceso.clave' => 'clave-sandbox']);

        $this->from('/acceso')->post('/acceso', ['clave' => 'otra'])
            ->assertRedirect('/acceso')
            ->assertSessionHasErrors('clave');
    }

    public function test_clave_correcta_entrega_cookie_y_da_acceso(): void
    {
        config(['colliers.acceso.clave' => 'clave-sandbox']);

        $this->get('/admin');
        $respuesta = $this->post('/acceso', ['clave' => 'clave-sandbox']);
        $respuesta->assertRedirect(url('/admin'))->assertCookie(AccesoSandbox::COOKIE);

        // Con la clave del sandbox se pasa al sitio; el panel además exige sesión (Bloque D).
        $this->withCookie(AccesoSandbox::COOKIE, AccesoSandbox::huella('clave-sandbox'))
            ->get('/admin')
            ->assertRedirect(route('admin.ingresar'));
        $this->withCookie(AccesoSandbox::COOKIE, AccesoSandbox::huella('clave-sandbox'))
            ->get('/admin/ingresar')
            ->assertOk()
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    }

    public function test_pagina_404_en_espanol(): void
    {
        config(['colliers.acceso.clave' => null]);

        $this->get('/esta-pagina-no-existe')->assertNotFound()->assertSee('No encontramos esta página');
    }

    public function test_la_raiz_es_el_listado_y_remates_redirige(): void
    {
        config(['colliers.acceso.clave' => null]);

        $this->get('/')->assertOk()->assertSee('Cargar más remates');
        $this->get('/remates')->assertRedirect('/')->assertStatus(301);
    }

    public function test_programador_incluye_latido_y_cola(): void
    {
        $this->artisan('schedule:list')
            ->expectsOutputToContain('latido-programador')
            ->expectsOutputToContain('queue:work --stop-when-empty');
    }
}
