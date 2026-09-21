<?php

namespace Tests\Feature;

use App\Models\Configuracion;
use App\Models\Postor;
use App\Models\Remate;
use App\Models\User;
use App\Remates\RemateDePrueba;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * La experiencia de prueba de punta a punta, como la usa una persona (21/09): del panel a la sala sin escribir una URL,
 * y sin que ninguna pantalla muestre un estado falso.
 */
class PostoresDePruebaTest extends TestCase
{
    use RefreshDatabase;

    private string $carpeta;

    private User $admin;

    private User $martillero;

    protected function setUp(): void
    {
        parent::setUp();
        $this->carpeta = storage_path('framework/testing/pruebas-' . getmypid());
        config(['colliers.tiempo_real.carpeta' => $this->carpeta]);
        $this->app->forgetInstance(\App\Subastas\Difusion\Emisor::class);
        Configuracion::sembrarDefectos();
        $this->admin = User::create(['name' => 'Carolina Méndez', 'email' => 'admin@colliers.test', 'password' => 'x-clave-larga-1', 'rol' => User::ROL_ADMIN, 'estado' => User::ESTADO_ACTIVO]);
        // La subasta de prueba siempre queda a cargo de un martillero activo (21/09).
        $this->martillero = User::create(['name' => 'M. Ossandón', 'email' => 'mossandon@colliers.test', 'password' => 'x-clave-larga-1', 'rol' => User::ROL_MARTILLERO, 'estado' => User::ESTADO_ACTIVO]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->carpeta);
        parent::tearDown();
    }

    public function test_el_postor_de_prueba_ve_su_remate_con_el_estado_real_y_el_boton_a_la_sala(): void
    {
        $creado = app(RemateDePrueba::class)->crear(postores: 2, lotes: 2, inicioMinutos: 12);
        $postor = User::where('email', $creado['postores'][0]['email'])->sole();
        $sala = route('sala.show', $creado['remate']->slug);

        $this->actingAs($postor)->get('/mi-cuenta')->assertOk()
            ->assertSee('GARANTÍA APROBADA · HABILITADO PARA PUJAR')
            ->assertSee('Estás habilitado para pujar')
            ->assertSee('Habilitado')
            ->assertSee($creado['remate']->folio)
            ->assertSee('href="' . $sala . '"', false)
            ->assertSee('Entrar a la sala de pujas')
            ->assertSee('data-cuenta-prueba', false)
            ->assertDontSee('SIN INSCRIPCIONES')
            ->assertDontSee('GARANTÍA PENDIENTE')
            ->assertDontSee('Bloqueado');

        // El botón lleva a la sala y la sala lo deja entrar (no lo devuelve a «Mi cuenta»).
        $this->actingAs($postor)->get($sala)->assertOk();

        // Para el público sigue sin existir.
        $this->get('/')->assertOk()->assertDontSee($creado['remate']->folio);
    }

    public function test_la_ficha_muestra_correo_y_clave_en_cada_fila_con_copiar_e_ingresar(): void
    {
        $html = $this->actingAs($this->admin)->followingRedirects()
            ->post(route('admin.remates.crear-prueba'), ['lotes' => 1, 'postores' => 3, 'inicio' => 12])
            ->assertOk()->assertSee('La clave solo se muestra esta vez.')->getContent();

        $remate = Remate::where('es_demostracion', true)->sole();
        $postores = app(RemateDePrueba::class)->postores($remate);
        $this->assertCount(3, $postores);
        preg_match_all('/<code class="admin-pruebas__valor admin-pruebas__valor--clave" data-clave>([^<]+)<\/code>/', $html, $claves);
        $this->assertCount(3, $claves[1], 'la clave va en la fila de cada postor');
        $clave = $claves[1][0];
        $this->assertSame([$clave, $clave, $clave], $claves[1]);
        $this->assertTrue(Hash::check($clave, User::where('email', $postores[0]['email'])->sole()->password));

        foreach ($postores as $p) {
            $this->assertStringContainsString($p['email'], $html);
            $this->assertStringContainsString('href="' . e(route('ingresar.como', ['usuario' => $p['email']])) . '"', $html);
        }
        $this->assertSame(7, substr_count($html, 'x-data="copiar('), 'un botón de copiar para cada correo y cada clave, más el correo del martillero');
        $this->assertSame(3, substr_count($html, '>Ingresar como este postor</a>'), 'un enlace por postor');

        // El martillero a cargo, junto a los postores, con su enlace al acceso de administración.
        $this->assertSame($this->martillero->id, $remate->martillero_id, 'el único martillero activo queda a cargo');
        $this->assertStringContainsString('data-martillero-correo>mossandon@colliers.test<', $html);
        $this->assertStringContainsString('href="' . e(route('ingresar.como', ['usuario' => 'mossandon@colliers.test', 'portal' => 'admin'])) . '"', $html);
        $this->assertStringContainsString('>Ingresar como este martillero</a>', $html);

        // Al volver a la ficha la clave ya no está, y la pantalla dice cómo conseguir otra.
        $otraVez = $this->actingAs($this->admin)->get(route('admin.remates.show', $remate))->assertOk()
            ->assertSee('Ya no se muestra')->assertSee('Generar clave nueva')->getContent();
        $this->assertStringNotContainsString($clave, $otraVez);

        // «Generar clave nueva»: se muestra una vez, la anterior deja de servir.
        $nueva = $this->actingAs($this->admin)->followingRedirects()->post(route('admin.remates.prueba-clave', $remate))
            ->assertOk()->assertSee('La clave solo se muestra esta vez.')->getContent();
        preg_match('/data-clave>([^<]+)</', $nueva, $m);
        $usuario = User::where('email', $postores[0]['email'])->sole();
        $this->assertNotSame($clave, $m[1]);
        $this->assertTrue(Hash::check($m[1], $usuario->password));
        $this->assertFalse(Hash::check($clave, $usuario->password));
    }

    public function test_ingresar_como_escribe_el_correo_y_explica_la_sesion_abierta(): void
    {
        $correo = 'prueba1-x@' . RemateDePrueba::DOMINIO_CORREO;

        // Sin sesión: directo al acceso con el correo escrito y el foco en la clave.
        $this->get(route('ingresar.como', ['usuario' => $correo]))->assertRedirect(route('login', ['usuario' => $correo]));
        $this->get(route('login', ['usuario' => $correo]))->assertOk()
            ->assertSee("acceso('{$correo}')")->assertSee('autofocus', false);
        // Un valor que no es un correo no se escribe.
        $this->get(route('ingresar.como', ['usuario' => '<script>']))->assertRedirect(route('login'));

        // Con la sesión de administración abierta: lo explica y ofrece las dos salidas, en vez de volver al panel.
        $this->actingAs($this->admin)->get(route('ingresar.como', ['usuario' => $correo]))->assertOk()
            ->assertSee('Cerrar esta sesión e ingresar como ' . $correo)
            ->assertSee('ventana privada')->assertSee('Copiar enlace');

        $this->actingAs($this->admin)->post(route('ingresar.como.cambiar'), ['usuario' => $correo])
            ->assertRedirect(route('login', ['usuario' => $correo]));
        $this->assertGuest();
    }

    public function test_los_remates_de_prueba_y_los_reales_no_se_mezclan(): void
    {
        $servicio = app(RemateDePrueba::class);
        $prueba = $servicio->crear(postores: 1, lotes: 1, inicioMinutos: 30);
        // Un remate «real» publicado y con inscripción abierta.
        $real = $servicio->crear(postores: 1, lotes: 1, inicioMinutos: 30)['remate'];
        $real->forceFill(['es_demostracion' => false, 'titulo' => 'Remate real abierto'])->save();

        // El postor de prueba no ve ni puede inscribirse en remates reales.
        $postorPrueba = User::where('email', $prueba['postores'][0]['email'])->sole();
        $this->actingAs($postorPrueba)->get('/mi-cuenta')->assertOk()->assertDontSee('Remate real abierto');
        $this->actingAs($postorPrueba)->post(route('cuenta.inscribirme', $real->slug))->assertSessionHas('error');
        $this->assertSame(0, $real->garantias()->where('user_id', $postorPrueba->id)->count());

        // Un postor real que llega al enlace de un remate de prueba no queda con una garantía que nadie revisará.
        $cliente = User::create(['name' => 'Ana', 'email' => 'ana@correo.test', 'password' => 'x-clave-larga-1', 'rol' => User::ROL_POSTOR, 'estado' => User::ESTADO_ACTIVO]);
        $cliente->forceFill(['email_verified_at' => now()])->save();
        Postor::create(['user_id' => $cliente->id, 'nombres' => 'Ana', 'apellidos' => 'Prueba', 'rut' => '10000001' . \App\Support\Rut::digitoVerificador('10000001')])
            ->forceFill(['estado' => Postor::ESTADO_APROBADO])->save();
        $this->actingAs($cliente)->post(route('cuenta.inscribirme', $prueba['remate']->slug))->assertSessionHas('error');
        $this->assertSame(0, $prueba['remate']->garantias()->where('user_id', $cliente->id)->count());
    }

    public function test_mi_cuenta_de_un_remate_cancelado_no_dice_habilitado(): void
    {
        $creado = app(RemateDePrueba::class)->crear(postores: 1, lotes: 1, inicioMinutos: 30);
        $postor = User::where('email', $creado['postores'][0]['email'])->sole();
        app(\App\Remates\GestionRemates::class)->cancelar($creado['remate'], 'Retiro de la propiedad');

        $this->actingAs($postor)->get('/mi-cuenta')->assertOk()
            ->assertSee('REMATE CANCELADO')->assertSee('Retiro de la propiedad')->assertSee('data-remate-cancelado', false)
            ->assertSee('Remate cancelado')
            ->assertDontSee('HABILITADO PARA PUJAR')->assertDontSee('CÓMO CONSTITUIRLA')->assertDontSee('Entrar a la sala de pujas')
            ->assertDontSee('Subir comprobante');
    }

    public function test_los_numeros_de_los_formularios_no_pierden_ceros(): void
    {
        // Con rtrim a secas, el 10 % global se mostraba como «1 (global)» (visto en la ficha de la prueba, 21/09).
        $this->assertSame('10', \App\Support\Formato::campo('10'));
        $this->assertSame('10', \App\Support\Formato::campo('10.00'));
        $this->assertSame('82.5', \App\Support\Formato::campo('82.50'));
        $this->assertSame('120', \App\Support\Formato::campo(120));
        $this->assertSame('', \App\Support\Formato::campo(null));

        $remate = app(RemateDePrueba::class)->crear(postores: 1, lotes: 1, inicioMinutos: 12)['remate'];
        $this->actingAs($this->admin)->get(route('admin.remates.show', $remate))->assertOk()
            ->assertSee('placeholder="10 (global)"', false);
        $this->actingAs($this->admin)->get('/admin/subastas')->assertOk()->assertSee('placeholder="10 (global)"', false);
    }

    public function test_el_panel_no_cuenta_postores_de_prueba_como_clientes(): void
    {
        $creado = app(RemateDePrueba::class)->crear(postores: 3, lotes: 1, inicioMinutos: 12);

        // Antes salían en Postores como «Sin inscripción» (falso) y sumaban en «Postores registrados».
        $this->actingAs($this->admin)->get('/admin/postores')->assertOk()->assertDontSee($creado['postores'][0]['email']);
        $this->actingAs($this->admin)->get('/admin')->assertOk()
            ->assertViewHas('datos', fn (array $d) => collect($d['kpis'])->firstWhere(0, 'POSTORES REGISTRADOS')[1] === '0');
    }
}
