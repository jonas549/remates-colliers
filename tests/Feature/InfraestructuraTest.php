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

    public function test_la_clave_del_sandbox_no_expulsa_una_sesion_autenticada_y_le_devuelve_la_cookie(): void
    {
        config(['colliers.acceso.clave' => 'clave-sandbox']);
        $admin = User::create(['name' => 'Admin', 'email' => 'admin@colliers.test', 'password' => 'x-clave-larga-1', 'rol' => User::ROL_ADMIN, 'estado' => User::ESTADO_ACTIVO]);

        // Sesión iniciada pero sin la cookie de la clave: pasa y recibe la cookie de nuevo.
        $this->actingAs($admin)->get('/admin')->assertOk()->assertCookie(AccesoSandbox::COOKIE);
        $this->assertSame(302, $this->actingAs($admin->fresh()->forceFill(['debe_cambiar_clave' => true]))->get('/admin')->status(), 'sigue su flujo normal (cambio de clave)');
    }

    public function test_la_clave_del_sandbox_nunca_bloquea_en_silencio(): void
    {
        config(['colliers.acceso.clave' => 'clave-sandbox']);

        // Petición JSON (puja, acciones del panel): 403 con el motivo, no un redirect que fetch mostraría como éxito.
        $this->postJson('/admin/postores/1/aprobar')->assertForbidden()
            ->assertJson(['motivo' => 'acceso_sandbox', 'mensaje' => AccesoSandbox::MENSAJE_VENCIDO]);
        $this->postJson('/remates/prueba/lotes/1/pujas', ['monto' => 1])->assertForbidden()->assertJsonPath('motivo', 'acceso_sandbox');

        // Primera visita: formulario sin aviso.
        $this->get('/admin/ingresar')->assertRedirect(route('acceso.formulario'))->assertSessionMissing('acceso_aviso');

        // Venía navegando (tiene cookie de sesión) y envía un formulario: se explica y vuelve a la página del formulario.
        $this->withCookie((string) config('session.cookie'), 'sesion-anterior')->from(url('/admin/ingresar'))
            ->post('/admin/ingresar', ['usuario' => 'a@colliers.test', 'password' => 'x'])
            ->assertRedirect(route('acceso.formulario'))->assertSessionHas('acceso_aviso', AccesoSandbox::MENSAJE_VENCIDO)
            ->assertSessionHas('url.intended', url('/admin/ingresar'));
        $this->get('/acceso')->assertOk()->assertSee('venció o no se encontró');
        $this->post('/acceso', ['clave' => 'clave-sandbox'])->assertRedirect(url('/admin/ingresar'));
    }

    public function test_sesion_vencida_en_una_accion_por_fetch_responde_en_espanol(): void
    {
        config(['colliers.acceso.clave' => null]);
        $admin = User::create(['name' => 'Admin', 'email' => 'admin@colliers.test', 'password' => 'x-clave-larga-1', 'rol' => User::ROL_ADMIN, 'estado' => User::ESTADO_ACTIVO]);

        // Sin el middleware que omite CSRF en pruebas: simula el token vencido.
        $this->app->instance(\Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class, new class($this->app, $this->app['encrypter']) extends \Illuminate\Foundation\Http\Middleware\PreventRequestForgery
        {
            protected function runningUnitTests()
            {
                return false;
            }
        });
        $this->actingAs($admin)->postJson('/admin/postores/1/aprobar')->assertStatus(419)
            ->assertJson(['mensaje' => 'Tu sesión expiró. Recarga la página para continuar.']);
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
            ->expectsOutputToContain('queue:work --stop-when-empty')
            ->expectsOutputToContain('colliers:recordatorios')
            ->expectsOutputToContain('colliers:actualizar-uf');
    }

    public function test_crear_martillero_con_clave_temporal_de_un_solo_uso(): void
    {
        $this->artisan('colliers:crear-usuario martillero m.ossandon@colliers.test M. Ossandón')
            ->expectsOutputToContain('Martillero creado: m.ossandon@colliers.test')
            ->expectsOutputToContain('Clave temporal')
            ->assertSuccessful();

        $user = User::where('email', 'm.ossandon@colliers.test')->sole();
        $this->assertSame(User::ROL_MARTILLERO, $user->rol);
        $this->assertSame('M. Ossandón', $user->name);
        $this->assertTrue($user->debe_cambiar_clave);

        $this->artisan('colliers:crear-usuario martillero m.ossandon@colliers.test Otro')->assertFailed();
        $this->artisan('colliers:crear-usuario postor otro@colliers.test Postor')->assertFailed();
    }
}
