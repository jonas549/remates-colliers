<?php

namespace Tests\Feature;

use App\Models\AccessLog;
use App\Models\Empresa;
use App\Models\Postor;
use App\Models\PostorDocumento;
use App\Models\User;
use App\Support\Rut;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cookie\CookieValuePrefix;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Bloque D · autenticación y registro de postores, de punta a punta por HTTP. */
class AutenticacionTest extends TestCase
{
    use RefreshDatabase;

    private const CLAVE = 'Clave-de-prueba-2026';

    protected function setUp(): void
    {
        parent::setUp();
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-20 15:00:00', 'UTC'));
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    // ── Ingreso ────────────────────────────────────────────────────────────────────────────────────────────

    public function test_postor_ingresa_con_correo_o_con_rut_en_cualquier_formato(): void
    {
        $user = $this->postor('15482331K', 'mpgonzalez@correo.test');

        foreach (['MPGonzalez@correo.test', '15.482.331-K', '15482331k'] as $usuario) {
            $this->post('/ingresar', ['usuario' => $usuario, 'password' => self::CLAVE])->assertRedirect('/mi-cuenta?ingreso=1');
            $this->assertAuthenticatedAs($user);
            $this->post('/salir');
            $this->assertGuest();
        }

        $this->assertSame(3, AccessLog::where('evento', 'ingreso')->where('user_id', $user->id)->count());
        $this->assertSame(3, AccessLog::where('evento', 'salida')->where('user_id', $user->id)->count());
    }

    public function test_usuario_inexistente_recibe_mensaje_generico_y_queda_registrado(): void
    {
        $this->from('/ingresar')->post('/ingresar', ['usuario' => 'nadie@correo.test', 'password' => 'x'])
            ->assertRedirect('/ingresar')->assertSessionHasErrors(['usuario' => 'Usuario o contraseña incorrectos.']);

        $this->assertSame('usuario_inexistente', AccessLog::where('evento', 'ingreso_fallido')->sole()->detalle['motivo']);
    }

    public function test_cinco_intentos_fallidos_bloquean_la_cuenta_15_minutos_con_contador_en_tabla(): void
    {
        $user = $this->postor('15482331K', 'ana@correo.test');

        foreach ([4, 3, 2, 1] as $quedan) {
            $this->post('/ingresar', ['usuario' => 'ana@correo.test', 'password' => 'incorrecta'])
                ->assertSessionHasErrors(['usuario' => 'Usuario o contraseña incorrectos. Te ' . ($quedan === 1 ? 'queda 1 intento' : "quedan {$quedan} intentos") . ' antes de que bloqueemos la cuenta por seguridad.']);
        }
        $this->assertSame(4, $user->fresh()->intentos_fallidos);

        $this->post('/ingresar', ['usuario' => 'ana@correo.test', 'password' => 'incorrecta'])
            ->assertSessionHasErrors(['usuario' => 'Usuario o contraseña incorrectos. Bloqueamos la cuenta por seguridad durante 15 minutos.']);
        $this->assertSame('2026-09-20 15:15:00', $user->fresh()->bloqueado_hasta->format('Y-m-d H:i:s'));
        $this->assertSame(1, AccessLog::where('evento', 'bloqueo')->count());

        // Bloqueada: ni con la clave correcta, ni vaciando la caché.
        Cache::flush();
        $this->post('/ingresar', ['usuario' => 'ana@correo.test', 'password' => self::CLAVE])->assertSessionHasErrors('usuario');
        $this->assertGuest();

        // A los 15 minutos entra y el contador vuelve a cero.
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-20 15:15:00', 'UTC'));
        $this->post('/ingresar', ['usuario' => 'ana@correo.test', 'password' => self::CLAVE])->assertRedirect('/mi-cuenta?ingreso=1');
        $this->assertNull($user->fresh()->bloqueado_hasta);
        $this->assertSame(0, $user->fresh()->intentos_fallidos);
    }

    public function test_accesos_separados_para_postores_y_administracion(): void
    {
        $this->postor('15482331K', 'ana@correo.test');
        $admin = $this->usuario(User::ROL_ADMIN, 'admin@colliers.test');
        $martillero = $this->usuario(User::ROL_MARTILLERO, 'martillero@colliers.test');

        $this->post('/ingresar', ['usuario' => 'admin@colliers.test', 'password' => self::CLAVE])
            ->assertSessionHasErrors(['usuario' => 'Esta cuenta es de administración: ingresa por el acceso de administradores.']);
        $this->post('/admin/ingresar', ['usuario' => 'ana@correo.test', 'password' => self::CLAVE])
            ->assertSessionHasErrors(['usuario' => 'Esta cuenta es de postor: ingresa por el acceso de postores.']);
        $this->assertGuest();

        $this->post('/admin/ingresar', ['usuario' => 'admin@colliers.test', 'password' => self::CLAVE])->assertRedirect('/admin?ingreso=1');
        $this->assertAuthenticatedAs($admin);
        $this->post('/salir');
        $this->post('/admin/ingresar', ['usuario' => 'martillero@colliers.test', 'password' => self::CLAVE])->assertRedirect('/admin?ingreso=1');
        $this->assertAuthenticatedAs($martillero);
    }

    public function test_ingreso_que_pierde_la_sesion_nunca_termina_en_silencio(): void
    {
        $admin = $this->usuario(User::ROL_ADMIN, 'admin@colliers.test');
        $admin->forceFill(['debe_cambiar_clave' => true])->save();

        // Con sesión: la marca se quita y sigue su flujo (aquí, el cambio de clave obligatorio).
        $this->post('/admin/ingresar', ['usuario' => 'admin@colliers.test', 'password' => self::CLAVE])->assertRedirect('/admin?ingreso=1');
        $this->get('/admin?ingreso=1')->assertRedirect(url('/admin'));
        $this->get('/admin')->assertRedirect(route('cuenta.clave'));
        $this->post('/salir');

        // Sin sesión en la petición siguiente (lo que pasaba en el sandbox): vuelve al acceso diciendo qué pasó y lo anota.
        \Illuminate\Support\Facades\Log::spy();
        $this->app['auth']->forgetGuards();
        $this->get('/admin?ingreso=1')->assertRedirect(route('admin.ingresar', ['sesion' => 'perdida']));
        \Illuminate\Support\Facades\Log::shouldHaveReceived('warning')->withArgs(fn ($mensaje, $datos) => str_starts_with($mensaje, 'Ingreso sin sesión')
            && array_key_exists('cookies_recibidas', $datos) && array_key_exists('https_detectado', $datos))->once();
        $this->get(route('admin.ingresar', ['sesion' => 'perdida']))->assertOk()
            ->assertSee('Tu usuario y contraseña son correctos, pero el navegador no conservó la sesión.');

        // Postores: mismo resguardo, hacia su acceso.
        $this->get('/mi-cuenta?ingreso=1')->assertRedirect(route('login', ['sesion' => 'perdida']));
        $this->get(route('login', ['sesion' => 'perdida']))->assertOk()->assertSee('el navegador no conservó la sesión');
    }

    public function test_cuenta_inactiva_no_ingresa(): void
    {
        $user = $this->postor('15482331K', 'ana@correo.test');
        $user->forceFill(['estado' => User::ESTADO_INACTIVO])->save();

        $this->post('/ingresar', ['usuario' => 'ana@correo.test', 'password' => self::CLAVE])->assertSessionHasErrors('usuario');
        $this->assertGuest();
    }

    public function test_pantallas_de_acceso_responden(): void
    {
        $this->get('/ingresar')->assertOk()->assertSee('ACCESO DE POSTORES')->assertSee('Crear cuenta de postor');
        $this->get('/admin/ingresar')->assertOk()->assertSee('ACCESO DE ADMINISTRADORES')->assertDontSee('Crear cuenta de postor');
        $this->get('/registro')->assertOk()->assertSee('Enviar solicitud de registro');
        $this->get('/recuperar-clave')->assertOk()->assertSee('Recupera tu acceso');
    }

    // ── Registro y verificación de correo ────────────────────────────────────────────────────────────────

    public function test_registro_de_persona_natural_con_documentos_privados_y_verificacion_de_correo(): void
    {
        Storage::fake('local');
        Notification::fake();

        $this->post('/registro', $this->formulario())->assertRedirect(route('verification.notice') . '?ingreso=1');

        $user = User::where('email', 'nueva@correo.test')->sole();
        $this->assertSame(User::ROL_POSTOR, $user->rol);
        $this->assertAuthenticatedAs($user);
        $postor = $user->postor;
        $this->assertSame(Postor::ESTADO_REGISTRADO, $postor->estado);
        $this->assertSame('17.998.221-8', $postor->rut);
        $this->assertStringNotContainsString('17998221', DB::table('postores')->value('rut'));
        $this->assertNotNull($postor->acepta_terminos_en);

        $this->assertSame(['ci-dorso', 'ci-frente', 'domicilio'], $postor->documentos->pluck('tipo')->sort()->values()->all());
        foreach ($postor->documentos as $documento) {
            Storage::disk('local')->assertExists($documento->ruta);
            $this->assertStringStartsWith("postores/{$postor->id}/", $documento->ruta);
        }
        Notification::assertSentTo($user, VerifyEmail::class);

        // Sin verificar, la cuenta no se ve: pide confirmar el correo.
        $this->get('/mi-cuenta')->assertRedirect(route('verification.notice'));
        $this->get('/verificar-correo')->assertOk()->assertSee('nueva@correo.test');

        // Al verificar entra a la cola de revisión de Colliers.
        $enlace = URL::temporarySignedRoute('verification.verify', now()->addHour(), ['id' => $user->id, 'hash' => sha1($user->email)]);
        $this->get($enlace)->assertRedirect();
        $this->assertSame(Postor::ESTADO_EN_REVISION, $postor->fresh()->estado);
        $this->get('/mi-cuenta')->assertOk()->assertSee('Estamos revisando tu registro');
        $this->assertSame(1, AccessLog::where('evento', 'correo_verificado')->count());
    }

    public function test_registro_de_persona_juridica_exige_empresa_y_poder(): void
    {
        Storage::fake('local');
        Notification::fake();
        $datos = $this->formulario(['tipo' => 'juridica']);

        $this->post('/registro', $datos)->assertSessionHasErrors(['razon_social', 'rut_empresa', 'giro', 'calidad', 'documentos.poder']);

        $this->post('/registro', $this->formulario([
            'tipo' => 'juridica', 'razon_social' => 'Inversiones Andes SpA', 'rut_empresa' => '76.543.210-3',
            'giro' => 'Inversiones', 'calidad' => 'Representante legal',
        ], poder: true))->assertRedirect(route('verification.notice') . '?ingreso=1');

        $postor = User::where('email', 'nueva@correo.test')->sole()->postor;
        $this->assertSame(Postor::TIPO_JURIDICA, $postor->tipo);
        $this->assertSame('Inversiones Andes SpA', $postor->empresa->razon_social);
        $this->assertTrue($postor->documentos->contains('tipo', 'poder'));

        // Supuesto vigente: una empresa = una cuenta.
        $this->post('/salir');
        $this->post('/registro', $this->formulario([
            'email' => 'otro@correo.test', 'rut' => '16.774.203-3', 'tipo' => 'juridica', 'razon_social' => 'Andes',
            'rut_empresa' => '76543210-3', 'giro' => 'Inversiones', 'calidad' => 'Representante legal',
        ], poder: true))->assertSessionHasErrors(['rut_empresa' => 'Esta empresa ya tiene una cuenta registrada. Escribe a remates@colliers.cl si necesitas otro representante.']);
        $this->assertSame(1, Empresa::count());
    }

    public function test_registro_acepta_rut_con_o_sin_puntos_y_detecta_el_repetido_en_cualquier_formato(): void
    {
        Storage::fake('local');
        Notification::fake();

        $this->post('/registro', $this->formulario(['rut' => '179982218']))->assertRedirect(route('verification.notice') . '?ingreso=1');
        $this->assertSame('17.998.221-8', User::where('email', 'nueva@correo.test')->sole()->postor->rut, 'sin puntos ni guion queda normalizado');
        $this->post('/salir');

        foreach (['17.998.221-8', '17998221-8', ' 17.998.221-8 '] as $n => $formato) {
            $this->post('/registro', $this->formulario(['rut' => $formato, 'email' => "otra{$n}@correo.test"]))
                ->assertSessionHasErrors(['rut' => 'Ya existe una cuenta con este RUT. Si es tuya, ingresa o recupera tu contraseña.']);
        }

        $this->post('/registro', $this->formulario(['rut' => '15.482.331-k', 'email' => 'conk@correo.test']))->assertRedirect(route('verification.notice') . '?ingreso=1');
        $this->assertSame('15.482.331-K', User::where('email', 'conk@correo.test')->sole()->postor->rut, 'con puntos y k minúscula');
        $this->post('/salir');
        $this->post('/registro', $this->formulario(['rut' => '15482331K', 'email' => 'otrak@correo.test']))->assertSessionHasErrors('rut');
        $this->post('/registro', $this->formulario(['rut' => '17.998.221', 'email' => 'corto@correo.test']))->assertSessionHasErrors('rut');
        $this->assertSame(2, User::count());
    }

    public function test_registro_rechaza_rut_invalido_o_repetido_y_documentos_faltantes(): void
    {
        Storage::fake('local');
        $this->postor('17998221-8', 'existente@correo.test');

        $this->post('/registro', $this->formulario(['rut' => '17.998.221-9']))
            ->assertSessionHasErrors(['rut' => 'El RUT no es válido: revisa el dígito verificador.']);
        $this->post('/registro', $this->formulario(['rut' => '17998221-8']))
            ->assertSessionHasErrors(['rut' => 'Ya existe una cuenta con este RUT. Si es tuya, ingresa o recupera tu contraseña.']);

        $sinCedula = $this->formulario(['rut' => '16.774.203-3']);
        unset($sinCedula['documentos']['ci-frente']);
        $this->post('/registro', $sinCedula)->assertSessionHasErrors('documentos.ci-frente');

        $exe = $this->formulario(['rut' => '16.774.203-3']);
        $exe['documentos']['domicilio'] = UploadedFile::fake()->create('virus.exe', 10, 'application/octet-stream');
        $this->post('/registro', $exe)->assertSessionHasErrors('documentos.domicilio');

        $this->post('/registro', $this->formulario(['rut' => '16.774.203-3', 'acepta' => null]))->assertSessionHasErrors('acepta');

        $this->assertSame(1, User::count());
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    // ── Contraseñas ──────────────────────────────────────────────────────────────────────────────────────────

    public function test_recuperar_y_restablecer_la_contrasena_cierra_sesiones_y_levanta_el_bloqueo(): void
    {
        config(['session.driver' => 'database']);
        Notification::fake();
        $user = $this->postor('15482331K', 'ana@correo.test');
        $user->forceFill(['bloqueado_hasta' => CarbonImmutable::now('UTC')->addMinutes(10), 'intentos_fallidos' => 3])->save();
        $this->sesionEnBase($user, 'otra-sesion');

        $this->post('/recuperar-clave', ['email' => 'ana@correo.test'])->assertSessionHas('status');
        Notification::assertSentTo($user, ResetPassword::class);

        $token = Password::broker()->createToken($user);
        $this->get("/restablecer-clave/{$token}?email=ana@correo.test")->assertOk()->assertSee('Crea tu nueva contraseña');
        $this->post('/restablecer-clave', ['token' => $token, 'email' => 'ana@correo.test', 'password' => 'Nueva-clave-2026', 'password_confirmation' => 'Nueva-clave-2026'])
            ->assertRedirect('/ingresar');

        $user->refresh();
        $this->assertTrue(Hash::check('Nueva-clave-2026', $user->password));
        $this->assertNull($user->bloqueado_hasta);
        $this->assertSame(0, DB::table('sessions')->where('user_id', $user->id)->count());
        $this->post('/ingresar', ['usuario' => '15.482.331-K', 'password' => 'Nueva-clave-2026'])->assertRedirect('/mi-cuenta?ingreso=1');
    }

    public function test_cambiar_la_propia_contrasena_exige_la_actual_y_cierra_las_demas_sesiones(): void
    {
        config(['session.driver' => 'database']);
        $user = $this->postor('15482331K', 'ana@correo.test');
        $actual = $this->ingresarConCookie('ana@correo.test');
        $this->sesionEnBase($user, 'celular');
        $this->sesionEnBase($user, 'notebook');

        $this->get('/mi-cuenta/cambiar-clave')->assertOk()->assertSee('Cambia tu contraseña');
        $this->put('/mi-cuenta/clave', ['current_password' => 'incorrecta', 'password' => 'Nueva-clave-2026', 'password_confirmation' => 'Nueva-clave-2026'])
            ->assertSessionHasErrorsIn('updatePassword', ['current_password' => 'La contraseña actual no es correcta.']);

        $this->put('/mi-cuenta/clave', ['current_password' => self::CLAVE, 'password' => 'Nueva-clave-2026', 'password_confirmation' => 'Nueva-clave-2026'])
            ->assertSessionHas('status', 'password-updated');

        $this->assertTrue(Hash::check('Nueva-clave-2026', $user->fresh()->password));
        $this->assertSame([$actual], DB::table('sessions')->where('user_id', $user->id)->pluck('id')->all());
        $this->assertSame(2, AccessLog::where('evento', 'cambio_clave')->sole()->detalle['sesiones_cerradas']);
    }

    public function test_cambio_de_contrasena_obligatorio_antes_de_usar_el_panel(): void
    {
        $admin = $this->usuario(User::ROL_ADMIN, 'admin@colliers.test');
        $admin->forceFill(['debe_cambiar_clave' => true])->save();

        $this->post('/admin/ingresar', ['usuario' => 'admin@colliers.test', 'password' => self::CLAVE]);
        $this->get('/admin')->assertRedirect(route('cuenta.clave'));
        $this->postJson('/admin/usuarios/' . $admin->id . '/desbloquear')->assertForbidden();
        $this->get('/mi-cuenta/cambiar-clave')->assertOk()->assertSee('Cambia tu contraseña para continuar');

        $this->put('/mi-cuenta/clave', ['current_password' => self::CLAVE, 'password' => 'Nueva-clave-2026', 'password_confirmation' => 'Nueva-clave-2026']);
        $this->assertFalse($admin->fresh()->debe_cambiar_clave);
        $this->get('/admin')->assertOk();
    }

    // ── Roles y administración de cuentas ────────────────────────────────────────────────────────────────

    public function test_panel_exige_rol_de_administracion(): void
    {
        $postor = $this->postor('15482331K', 'ana@correo.test');
        $martillero = $this->usuario(User::ROL_MARTILLERO, 'martillero@colliers.test');
        $admin = $this->usuario(User::ROL_ADMIN, 'admin@colliers.test');

        $this->get('/admin/postores')->assertRedirect(route('admin.ingresar'));
        $this->get('/mi-cuenta')->assertRedirect(route('login'));

        $this->actingAs($postor)->get('/admin')->assertForbidden();
        $this->actingAs($martillero)->get('/admin/subastas')->assertOk();
        $this->actingAs($martillero)->postJson("/admin/usuarios/{$postor->id}/desbloquear")->assertForbidden();
        $this->actingAs($admin)->get('/admin/reportes')->assertOk();
        $this->actingAs($admin)->get('/mi-cuenta')->assertForbidden();
    }

    public function test_admin_restablece_la_clave_de_otra_cuenta_y_cierra_sus_sesiones(): void
    {
        config(['session.driver' => 'database']);
        $admin = $this->usuario(User::ROL_ADMIN, 'admin@colliers.test');
        $postor = $this->postor('15482331K', 'ana@correo.test');
        $postor->forceFill(['bloqueado_hasta' => CarbonImmutable::now('UTC')->addMinutes(5)])->save();
        $this->sesionEnBase($postor, 'sesion-postor');

        $clave = $this->actingAs($admin)->postJson("/admin/usuarios/{$postor->id}/restablecer-clave")
            ->assertOk()->assertJsonPath('sesiones_cerradas', 1)->json('clave_temporal');

        $postor->refresh();
        $this->assertTrue($postor->debe_cambiar_clave);
        $this->assertNull($postor->bloqueado_hasta);
        $this->assertTrue(Hash::check($clave, $postor->password));
        $this->assertSame(0, DB::table('sessions')->where('user_id', $postor->id)->count());
        $this->assertSame($admin->id, AccessLog::where('evento', 'clave_restablecida_por_admin')->sole()->detalle['por_user_id']);

        // Con la clave temporal entra, pero debe cambiarla antes de seguir.
        $this->post('/salir');
        $this->post('/ingresar', ['usuario' => 'ana@correo.test', 'password' => $clave]);
        $this->get('/mi-cuenta')->assertRedirect(route('cuenta.clave'));

        // Sobre su propia cuenta, el admin usa «Cambiar contraseña».
        $this->actingAs($admin)->postJson("/admin/usuarios/{$admin->id}/restablecer-clave")->assertStatus(422);
    }

    public function test_admin_desbloquea_una_cuenta(): void
    {
        $admin = $this->usuario(User::ROL_ADMIN, 'admin@colliers.test');
        $postor = $this->postor('15482331K', 'ana@correo.test');
        $postor->forceFill(['bloqueado_hasta' => CarbonImmutable::now('UTC')->addMinutes(10), 'intentos_fallidos' => 2])->save();

        $this->actingAs($admin)->postJson("/admin/usuarios/{$postor->id}/desbloquear")->assertOk();

        $this->assertNull($postor->fresh()->bloqueado_hasta);
        $this->post('/salir');
        $this->post('/ingresar', ['usuario' => 'ana@correo.test', 'password' => self::CLAVE])->assertRedirect('/mi-cuenta?ingreso=1');
    }

    // ── Sesiones activas ─────────────────────────────────────────────────────────────────────────────────────

    public function test_sesiones_activas_solo_propias_y_cierre_remoto(): void
    {
        config(['session.driver' => 'database']);
        $ana = $this->postor('15482331K', 'ana@correo.test');
        $beto = $this->postor('16774203-3', 'beto@correo.test');
        $actual = $this->ingresarConCookie('ana@correo.test');
        $this->sesionEnBase($ana, 'celular-ana', 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0) Safari/604.1');
        $this->sesionEnBase($beto, 'sesion-beto');

        $this->get('/mi-cuenta/sesiones')->assertOk()->assertSee('ESTE DISPOSITIVO')->assertSee('Safari en iPhone/iPad');

        // No se puede cerrar la sesión de otro usuario conociendo su id.
        $this->delete('/mi-cuenta/sesiones/sesion-beto')->assertNotFound();
        $this->assertSame(1, DB::table('sessions')->where('id', 'sesion-beto')->count());

        $this->delete('/mi-cuenta/sesiones/celular-ana')->assertRedirect();
        $this->assertSame(0, DB::table('sessions')->where('id', 'celular-ana')->count());

        $this->sesionEnBase($ana, 'notebook-ana');
        $this->delete('/mi-cuenta/sesiones')->assertRedirect();
        $this->assertSame([$actual], DB::table('sessions')->where('user_id', $ana->id)->pluck('id')->all());
        $this->assertSame(1, DB::table('sessions')->where('id', 'sesion-beto')->count());
    }

    // ── Documentos del registro ──────────────────────────────────────────────────────────────────────────────

    public function test_documentos_solo_para_su_dueno_y_la_administracion(): void
    {
        Storage::fake('local');
        $ana = $this->postor('15482331K', 'ana@correo.test');
        $beto = $this->postor('16774203-3', 'beto@correo.test');
        $admin = $this->usuario(User::ROL_ADMIN, 'admin@colliers.test');
        $martillero = $this->usuario(User::ROL_MARTILLERO, 'martillero@colliers.test');
        Storage::disk('local')->put('postores/1/ci-frente-abc.pdf', 'contenido');
        $documento = PostorDocumento::create(['postor_id' => $ana->postor->id, 'tipo' => 'ci-frente', 'ruta' => 'postores/1/ci-frente-abc.pdf', 'nombre_original' => 'cedula.pdf']);

        $this->actingAs($ana)->get("/mi-cuenta/documentos/{$documento->id}")->assertOk()->assertDownload('cedula.pdf');
        $this->actingAs($beto)->get("/mi-cuenta/documentos/{$documento->id}")->assertForbidden();

        $this->actingAs($admin)->get("/admin/postores/{$ana->postor->id}/documentos/{$documento->id}")->assertOk();
        $this->actingAs($admin)->get("/admin/postores/{$beto->postor->id}/documentos/{$documento->id}")->assertNotFound();
        $this->actingAs($martillero)->get("/admin/postores/{$ana->postor->id}/documentos/{$documento->id}")->assertForbidden();
    }

    // ── Ayudantes ────────────────────────────────────────────────────────────────────────────────────────────

    private function usuario(string $rol, string $email): User
    {
        $user = User::create(['name' => ucfirst($rol), 'email' => $email, 'password' => self::CLAVE, 'rol' => $rol, 'estado' => User::ESTADO_ACTIVO]);
        $user->forceFill(['email_verified_at' => now()])->save();

        return $user->fresh();
    }

    private function postor(string $rut, string $email): User
    {
        $user = $this->usuario(User::ROL_POSTOR, $email);
        Postor::create(['user_id' => $user->id, 'nombres' => 'Postor', 'apellidos' => 'Prueba', 'rut' => $rut])
            ->forceFill(['estado' => Postor::ESTADO_APROBADO])->save();

        return $user->fresh();
    }

    /**
     * Ingresa y deja la cookie de sesión puesta para las peticiones siguientes (en las pruebas HTTP la cookie no
     * viaja sola). Devuelve el id de la sesión, que queda guardada en la tabla sessions.
     */
    private function ingresarConCookie(string $usuario): string
    {
        $nombre = config('session.cookie');
        $respuesta = $this->post('/ingresar', ['usuario' => $usuario, 'password' => self::CLAVE])->assertRedirect();
        $id = CookieValuePrefix::remove($respuesta->getCookie($nombre, false) ? decrypt($respuesta->getCookie($nombre, false)->getValue(), false) : '');
        $this->assertSame(1, DB::table('sessions')->where('id', $id)->count(), 'la sesión del ingreso quedó en la base');
        $this->withCookie($nombre, $id);

        return $id;
    }

    private function sesionEnBase(User $user, string $id, string $agente = 'Mozilla/5.0 (Windows NT 10.0) Chrome/130.0'): void
    {
        DB::table('sessions')->insert([
            'id' => $id, 'user_id' => $user->id, 'ip_address' => '10.0.0.' . random_int(2, 250), 'user_agent' => $agente,
            'payload' => base64_encode('{}'), 'last_activity' => CarbonImmutable::now('UTC')->getTimestamp(),
        ]);
    }

    private function formulario(array $cambios = [], bool $poder = false): array
    {
        $documentos = [
            'ci-frente' => UploadedFile::fake()->create('cedula-frente.pdf', 200, 'application/pdf'),
            'ci-dorso' => UploadedFile::fake()->image('cedula-dorso.jpg'),
            'domicilio' => UploadedFile::fake()->create('cuenta-luz.pdf', 300, 'application/pdf'),
        ];
        if ($poder) {
            $documentos['poder'] = UploadedFile::fake()->create('poder.pdf', 100, 'application/pdf');
        }

        return array_merge([
            'tipo' => 'natural', 'nombres' => 'Camila', 'apellidos' => 'Ortiz Vera', 'rut' => '17.998.221-8',
            'fecha_nacimiento' => '1990-05-10', 'nacionalidad' => 'Chilena', 'estado_civil' => 'Soltero(a)',
            'email' => 'nueva@correo.test', 'telefono' => '+56 9 7744 1122', 'direccion' => 'Av. Providencia 1234',
            'comuna' => 'Providencia', 'region' => 'Metropolitana', 'origen' => 'Sitio de Colliers',
            'documentos' => $documentos, 'password' => 'Clave-segura-2026', 'password_confirmation' => 'Clave-segura-2026', 'acepta' => '1',
        ], $cambios);
    }
}
