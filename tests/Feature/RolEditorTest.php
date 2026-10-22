<?php

namespace Tests\Feature;

use App\Models\Configuracion;
use App\Models\Garantia;
use App\Models\Lote;
use App\Models\Postor;
use App\Models\Remate;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Rol Editor (22/10, propuesta de Jonas a confirmar con el cliente): crea y edita remates y lotes, sube fotos y documentos,
 * edita Configuración → Sitio, publica y despublica. No ve postores, garantías, reportes, el panel en vivo, usuarios ni
 * el resto de Configuración, ni cancela o cierra remates. El menú lo esconde y las rutas lo niegan.
 */
class RolEditorTest extends TestCase
{
    use RefreshDatabase;

    private string $carpeta;

    private User $admin;

    private User $editor;

    private User $martillero;

    protected function setUp(): void
    {
        parent::setUp();
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-20 15:00:00', 'UTC'));
        $this->carpeta = storage_path('framework/testing/editor-' . getmypid());
        config(['colliers.tiempo_real.carpeta' => $this->carpeta]);
        $this->app->forgetInstance(\App\Subastas\Difusion\Emisor::class);
        Configuracion::sembrarDefectos();

        $this->admin = User::create(['name' => 'Carolina Méndez', 'email' => 'admin@colliers.test', 'password' => 'x-clave-larga-1', 'rol' => User::ROL_ADMIN, 'estado' => User::ESTADO_ACTIVO]);
        $this->editor = User::create(['name' => 'Paula Riquelme', 'email' => 'editor@colliers.test', 'password' => 'x-clave-larga-1', 'rol' => User::ROL_EDITOR, 'estado' => User::ESTADO_ACTIVO]);
        $this->martillero = User::create(['name' => 'M. Ossandón', 'email' => 'm@colliers.test', 'password' => 'x-clave-larga-1', 'rol' => User::ROL_MARTILLERO, 'estado' => User::ESTADO_ACTIVO]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->carpeta);
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    private function formulario(array $cambios = []): array
    {
        return $cambios + [
            'direccion' => 'Av. Providencia 2410, Depto. 802', 'comuna' => 'Providencia', 'region' => 'Región Metropolitana',
            'tipo_propiedad' => 'Departamento', 'superficie_util' => '82,5', 'atributos' => ['rol_avaluo' => '1234-56'],
            'ocupacion' => 'Desocupada', 'precio_base' => '120.000.000', 'incremento_minimo' => '', 'porcentaje_garantia' => '',
            'inicio_en' => '2026-09-25T12:00', 'duracion_minutos' => '30', 'cierre_garantias_en' => '',
            'martillero_id' => $this->martillero->id, 'youtube_video_id' => '',
        ];
    }

    public function test_el_menu_muestra_solo_subastas_y_configuracion_sitio(): void
    {
        $this->actingAs($this->editor)->get('/admin')->assertRedirect(route('admin.subastas'));

        $html = $this->actingAs($this->editor)->get('/admin/subastas')->assertOk()->getContent();
        $menu = Str::between($html, 'class="admin-lateral__menu"', 'class="admin-lateral__pie"');
        $this->assertStringContainsString('Subastas', $menu);
        $this->assertStringContainsString('Configuración', $menu);
        foreach (['Dashboard', 'Postores', 'Reportes', 'Usuarios'] as $item) {
            $this->assertStringNotContainsString($item, $menu);
        }
        $this->assertStringContainsString('Editor/a', $html);
        $this->assertStringContainsString('Crear subasta', $html);
        $this->assertStringNotContainsString('Subastas de prueba', $html);

        // Configuración lleva directo a «Sitio», y el submenú no ofrece otras secciones.
        $this->actingAs($this->editor)->get('/admin/configuracion')->assertRedirect(route('admin.configuracion.seccion', 'sitio'));
        $html = $this->actingAs($this->editor)->get('/admin/configuracion/sitio')->assertOk()->getContent();
        $submenu = Str::between($html, 'class="admin-lateral__submenu"', '</div>');
        $this->assertStringContainsString('Sitio', $submenu);
        foreach (['Remates y pujas', 'Garantías', 'Correo', 'Plantillas', 'Seguridad', 'Sistema'] as $seccion) {
            $this->assertStringNotContainsString($seccion, $submenu);
        }
    }

    public function test_puede_crear_editar_publicar_y_despublicar_remates_y_editar_el_sitio(): void
    {
        $this->actingAs($this->editor)->post('/admin/subastas', $this->formulario(['accion' => 'borrador']))->assertSessionHas('estado');
        $remate = Remate::sole();

        $ficha = $this->actingAs($this->editor)->get(route('admin.remates.show', $remate))->assertOk()->getContent();
        $this->assertStringContainsString('Guardar datos', $ficha);
        $this->assertStringContainsString('>Publicar</button>', $ficha);
        $this->assertStringNotContainsString('Cancelar remate', $ficha);

        $lote = $remate->lotes()->sole();
        $this->actingAs($this->editor)->get(route('admin.lotes.edit', [$remate, $lote]))->assertOk();
        $this->actingAs($this->editor)->get(route('admin.lotes.create', $remate))->assertOk();

        $this->actingAs($this->editor)->post(route('admin.remates.publicar', $remate))->assertSessionHas('estado');
        $this->assertSame(Remate::ESTADO_PUBLICADO, $remate->fresh()->estado);
        $json = app(\App\Subastas\Difusion\Emisor::class)->rutaDe($remate);
        $this->assertFileExists($json);

        $ficha = $this->actingAs($this->editor)->get(route('admin.remates.show', $remate))->assertOk()->getContent();
        $this->assertStringContainsString('Despublicar', $ficha);
        $this->assertStringNotContainsString('Panel en vivo', $ficha);

        $this->actingAs($this->editor)->post(route('admin.remates.despublicar', $remate))->assertSessionHas('estado');
        $this->assertSame(Remate::ESTADO_BORRADOR, $remate->fresh()->estado);
        $this->assertFileDoesNotExist($json);
        $this->get(route('remates.show', $remate->slug))->assertNotFound();

        // Configuración → Sitio: guarda; otra sección: no.
        $actual = collect(Configuracion::camposDe('sitio'))->reject(fn ($c) => ! empty($c['solo_lectura']))
            ->map(fn ($c, $clave) => Configuracion::valor($clave))->all();
        $this->actingAs($this->editor)->put(route('admin.configuracion.update', 'sitio'), ['config' => ['contacto_telefono' => '+56 2 2000 0000'] + $actual])
            ->assertSessionHas('estado');
        $this->assertSame('+56 2 2000 0000', Configuracion::valor('contacto_telefono'));
    }

    public function test_con_inscritos_no_despublica_y_republicar_no_repite_el_aviso(): void
    {
        $this->actingAs($this->editor)->post('/admin/subastas', $this->formulario(['accion' => 'publicar']));
        $remate = Remate::sole();
        $user = User::create(['name' => 'Postor', 'email' => 'p@correo.test', 'password' => 'x-clave-larga-1', 'rol' => User::ROL_POSTOR, 'estado' => User::ESTADO_ACTIVO]);
        Postor::create(['user_id' => $user->id, 'nombres' => 'Postor', 'apellidos' => 'Uno', 'rut' => '40000001' . \App\Support\Rut::digitoVerificador('40000001')]);
        Garantia::paraRemate($remate, $user);

        $this->actingAs($this->editor)->get(route('admin.remates.show', $remate))->assertOk()
            ->assertDontSee('>Despublicar</button>', false)->assertSee('administración tiene que cancelarlo');
        $this->actingAs($this->editor)->post(route('admin.remates.despublicar', $remate))->assertSessionHas('error');
        $this->assertSame(Remate::ESTADO_PUBLICADO, $remate->fresh()->estado);

        // Sin inscritos: despublicar y volver a publicar no dispara de nuevo «remate nuevo» a los suscriptores.
        Garantia::where('remate_id', $remate->id)->delete();
        \Illuminate\Support\Facades\Event::fake([\App\Events\RematePublicado::class]);
        $this->actingAs($this->editor)->post(route('admin.remates.despublicar', $remate))->assertSessionHas('estado');
        $this->actingAs($this->editor)->post(route('admin.remates.publicar', $remate))->assertSessionHas('estado');
        \Illuminate\Support\Facades\Event::assertNotDispatched(\App\Events\RematePublicado::class);
    }

    public function test_las_rutas_que_no_le_corresponden_responden_403(): void
    {
        $this->actingAs($this->admin)->post('/admin/subastas', $this->formulario(['accion' => 'publicar']));
        $remate = Remate::sole();
        $user = User::create(['name' => 'Postor', 'email' => 'p@correo.test', 'password' => 'x-clave-larga-1', 'rol' => User::ROL_POSTOR, 'estado' => User::ESTADO_ACTIVO]);
        $postor = Postor::create(['user_id' => $user->id, 'nombres' => 'Postor', 'apellidos' => 'Uno', 'rut' => '40000001' . \App\Support\Rut::digitoVerificador('40000001')]);
        $garantia = Garantia::paraRemate($remate, $user);
        $lote = $remate->lotes()->sole();

        $prohibidas = [
            ['get', route('admin.postores')],
            ['get', route('admin.postores.exportar')],
            ['post', route('admin.postores.aprobar', $postor)],
            ['post', route('admin.postores.rechazar', $postor)],
            ['post', route('admin.garantias.aprobar', $garantia)],
            ['post', route('admin.garantias.rechazar', $garantia)],
            ['get', route('admin.garantias.comprobante', $garantia)],
            ['get', route('admin.reportes')],
            ['get', route('admin.reportes.exportar', ['libro', 'xlsx'])],
            ['get', route('admin.remates.en-vivo', $remate)],
            ['get', route('admin.remates.presencia', $remate)],
            ['post', route('admin.remates.cancelar', $remate), ['motivo' => 'x']],
            ['post', route('admin.remates.cerrar-ahora', $remate), ['motivo' => 'x']],
            ['post', route('admin.remates.crear-prueba')],
            ['get', route('admin.usuarios')],
            ['post', route('admin.usuarios.store'), ['name' => 'X', 'email' => 'x@colliers.test', 'rol' => 'admin']],
            ['get', route('admin.configuracion.seccion', 'remates')],
            ['put', route('admin.configuracion.update', 'garantias'), ['config' => ['garantia_porcentaje' => '50']]],
            ['get', route('admin.configuracion.seccion', 'plantillas')],
            ['get', route('admin.configuracion.plantilla', 'bienvenida')],
            ['post', route('admin.configuracion.probar-correo'), ['destino' => 'x@colliers.test']],
            ['get', route('admin.configuracion.seccion', 'correos')],
            ['post', route('admin.sala.cerrar-lote', [$remate->slug, $lote->id])],
            ['post', route('admin.sala.mensaje', $remate->slug), ['mensaje' => 'hola']],
        ];
        foreach ($prohibidas as $peticion) {
            [$metodo, $url] = $peticion;
            $this->actingAs($this->editor)->{$metodo . 'Json'}($url, $peticion[2] ?? [])->assertForbidden();
        }
        $this->assertSame(Remate::ESTADO_PUBLICADO, $remate->fresh()->estado);
        $this->assertSame(Garantia::ESTADO_PENDIENTE, $garantia->fresh()->estado);
        $this->assertSame(4, User::count());
    }

    public function test_administracion_y_martillero_no_cambian(): void
    {
        $html = $this->actingAs($this->admin)->get('/admin')->assertOk()->getContent();
        $menu = Str::between($html, 'class="admin-lateral__menu"', 'class="admin-lateral__pie"');
        foreach (['Dashboard', 'Subastas', 'Postores', 'Reportes', 'Configuración', 'Usuarios'] as $item) {
            $this->assertStringContainsString($item, $menu);
        }

        $html = $this->actingAs($this->martillero)->get('/admin')->assertOk()->getContent();
        $menu = Str::between($html, 'class="admin-lateral__menu"', 'class="admin-lateral__pie"');
        foreach (['Dashboard', 'Subastas', 'Reportes'] as $item) {
            $this->assertStringContainsString($item, $menu);
        }
        foreach (['Postores', 'Configuración', 'Usuarios'] as $item) {
            $this->assertStringNotContainsString($item, $menu);
        }
        $this->actingAs($this->martillero)->post('/admin/subastas', $this->formulario())->assertForbidden();
    }

    public function test_el_administrador_crea_un_editor_desde_usuarios(): void
    {
        $this->actingAs($this->admin)->post(route('admin.usuarios.store'), ['name' => 'Nueva Editora', 'email' => 'nueva@colliers.test', 'rol' => User::ROL_EDITOR])
            ->assertSessionHas('estado', 'Editor Nueva Editora creado.');
        $this->assertSame(User::ROL_EDITOR, User::where('email', 'nueva@colliers.test')->value('rol'));
        $this->actingAs($this->admin)->get(route('admin.usuarios'))->assertOk()->assertSee('Editor/a');
    }
}
