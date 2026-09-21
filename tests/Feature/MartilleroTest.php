<?php

namespace Tests\Feature;

use App\Models\Configuracion;
use App\Models\Remate;
use App\Models\User;
use App\Remates\RemateDePrueba;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * El martillero puede hacer su trabajo sin que nadie le explique nada (21/09): la subasta de prueba siempre queda a su
 * cargo, entra con «Ingresar como este martillero» y su inicio le muestra sus remates con el panel en vivo a un clic.
 */
class MartilleroTest extends TestCase
{
    use RefreshDatabase;

    private string $carpeta;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->carpeta = storage_path('framework/testing/martillero-' . getmypid());
        config(['colliers.tiempo_real.carpeta' => $this->carpeta]);
        $this->app->forgetInstance(\App\Subastas\Difusion\Emisor::class);
        Configuracion::sembrarDefectos();
        $this->admin = User::create(['name' => 'Carolina Méndez', 'email' => 'admin@colliers.test', 'password' => 'x-clave-larga-1', 'rol' => User::ROL_ADMIN, 'estado' => User::ESTADO_ACTIVO]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->carpeta);
        parent::tearDown();
    }

    private function martillero(string $nombre, string $correo, string $estado = User::ESTADO_ACTIVO): User
    {
        return User::create(['name' => $nombre, 'email' => $correo, 'password' => 'x-clave-larga-1', 'rol' => User::ROL_MARTILLERO, 'estado' => $estado]);
    }

    public function test_sin_martillero_activo_la_franja_lo_dice_y_lleva_a_usuarios(): void
    {
        $this->martillero('Inactivo', 'inactivo@colliers.test', User::ESTADO_INACTIVO);

        $this->actingAs($this->admin)->get('/admin/subastas')->assertOk()
            ->assertSee('data-sin-martillero', false)
            ->assertSee('No hay ningún martillero activo.')
            ->assertSee('href="' . route('admin.usuarios') . '"', false);
        $this->actingAs($this->admin)->post(route('admin.remates.crear-prueba'), ['lotes' => 1, 'postores' => 1])
            ->assertSessionHas('error');
        $this->assertSame(0, Remate::count());
    }

    public function test_con_un_martillero_se_asigna_solo_y_con_varios_hay_que_elegir(): void
    {
        $uno = $this->martillero('M. Ossandón', 'mossandon@colliers.test');
        $this->actingAs($this->admin)->get('/admin/subastas')->assertOk()
            ->assertSee('data-martillero-unico', false)->assertSee('M. Ossandón')->assertDontSee('data-martillero-prueba', false);
        $this->actingAs($this->admin)->post(route('admin.remates.crear-prueba'), ['lotes' => 1, 'postores' => 1]);
        $this->assertSame($uno->id, Remate::sole()->martillero_id);

        $dos = $this->martillero('C. Vergara', 'cvergara@colliers.test');
        $this->actingAs($this->admin)->get('/admin/subastas')->assertOk()
            ->assertSee('data-martillero-prueba', false)->assertSee('Elige un martillero');
        // Sin elegir no se crea, y lo dice.
        $this->actingAs($this->admin)->post(route('admin.remates.crear-prueba'), ['lotes' => 1, 'postores' => 1])
            ->assertSessionHas('error', 'Elige qué martillero queda a cargo de la subasta de prueba.');
        $this->assertSame(1, Remate::count());
        $this->actingAs($this->admin)->post(route('admin.remates.crear-prueba'), ['lotes' => 1, 'postores' => 1, 'martillero' => $dos->id]);
        $this->assertSame($dos->id, Remate::latest('id')->first()->martillero_id);
    }

    public function test_el_inicio_del_martillero_muestra_sus_remates_con_el_panel_en_vivo(): void
    {
        $suyo = $this->martillero('M. Ossandón', 'mossandon@colliers.test');
        $otro = $this->martillero('C. Vergara', 'cvergara@colliers.test');
        $prueba = app(RemateDePrueba::class)->crear(postores: 1, lotes: 2, inicioMinutos: 12, martilleroId: $suyo->id)['remate'];
        $ajeno = app(RemateDePrueba::class)->crear(postores: 1, lotes: 1, inicioMinutos: 20, martilleroId: $otro->id)['remate'];

        $html = $this->actingAs($suyo)->get('/admin')->assertOk()
            ->assertSee('Tus remates')->assertSee('Próximos')
            ->assertSee($prueba->folio)->assertSee('PRUEBA')
            ->assertSee('href="' . route('admin.remates.en-vivo', $prueba) . '"', false)
            ->assertSee('Abrir panel en vivo')
            ->assertDontSee($ajeno->folio)
            // El resumen general no: trae datos de postores que no le corresponden.
            ->assertDontSee('Pendientes de aprobación')->assertDontSee('Crear subasta')
            ->getContent();
        $this->assertStringContainsString('data-mi-remate="proximo"', $html);

        // Abierto el primer lote, pasa a «En vivo ahora» con el lote que corre.
        $this->travelTo($prueba->lotes->first()->abre_en->addSeconds(5));
        $this->actingAs($suyo)->get('/admin')->assertOk()->assertSee('En vivo ahora')->assertSee('Lote 1 de 2')
            ->assertSee('data-mi-remate="en_vivo"', false);
        // Y el panel en vivo le abre.
        $this->actingAs($suyo)->get(route('admin.remates.en-vivo', $prueba))->assertOk();

        // En «Subastas» también ve su subasta de prueba, marcada, y no la ajena.
        $this->actingAs($suyo)->get('/admin/subastas')->assertOk()->assertSee($prueba->folio . ' · PRUEBA')->assertDontSee($ajeno->folio);
    }

    public function test_sin_remates_asignados_el_inicio_lo_explica(): void
    {
        $solo = $this->martillero('M. Ossandón', 'mossandon@colliers.test');

        $this->actingAs($solo)->get('/admin')->assertOk()->assertSee('Todavía no tienes remates asignados');
    }

    public function test_ingresar_como_martillero_usa_el_acceso_de_administracion(): void
    {
        $correo = 'mossandon@colliers.test';

        $this->get(route('ingresar.como', ['usuario' => $correo, 'portal' => 'admin']))
            ->assertRedirect(route('admin.ingresar', ['usuario' => $correo]));
        $this->get(route('admin.ingresar', ['usuario' => $correo]))->assertOk()
            ->assertSee('ACCESO DE ADMINISTRADORES')->assertSee("acceso('{$correo}')");

        // Con otra sesión abierta: al cambiar, vuelve al acceso de administración con el correo escrito.
        $this->actingAs($this->admin)->get(route('ingresar.como', ['usuario' => $correo, 'portal' => 'admin']))->assertOk()
            ->assertSee('name="portal" value="admin"', false);
        $this->actingAs($this->admin)->post(route('ingresar.como.cambiar'), ['usuario' => $correo, 'portal' => 'admin'])
            ->assertRedirect(route('admin.ingresar', ['usuario' => $correo]));
    }
}
