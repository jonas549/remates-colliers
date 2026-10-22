<?php

namespace Tests\Feature;

use App\Models\AccessLog;
use App\Models\Empresa;
use App\Models\Postor;
use App\Models\PostorDocumento;
use App\Models\User;
use App\Support\Rut;
use App\Support\Turnstile;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cookie\CookieValuePrefix;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
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

    /** @var list<string> archivos temporales creados por pdf() */
    private array $temporales = [];

    protected function setUp(): void
    {
        parent::setUp();
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-20 15:00:00', 'UTC'));
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        foreach ($this->temporales as $ruta) {
            @unlink($ruta);
        }
        $this->temporales = [];
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
        Notification::fake();
        $this->postor('15482331K', 'ana@correo.test');
        $admin = $this->usuario(User::ROL_ADMIN, 'admin@colliers.test');
        $martillero = $this->usuario(User::ROL_MARTILLERO, 'martillero@colliers.test');

        $this->post('/ingresar', ['usuario' => 'admin@colliers.test', 'password' => self::CLAVE])
            ->assertSessionHasErrors(['usuario' => 'Esta cuenta es de administración: ingresa por el acceso de administradores.']);
        $this->post('/admin/ingresar', ['usuario' => 'ana@correo.test', 'password' => self::CLAVE])
            ->assertSessionHasErrors(['usuario' => 'Esta cuenta es de postor: ingresa por el acceso de postores.']);
        $this->assertGuest();

        // Administración: la clave lleva al código del correo (segundo factor, 22/10); con el código, al panel.
        $this->post('/admin/ingresar', ['usuario' => 'admin@colliers.test', 'password' => self::CLAVE])->assertRedirect(route('admin.ingresar.codigo'));
        $this->assertGuest();
        $this->conCodigo($admin)->assertRedirect('/admin?ingreso=1');
        $this->assertAuthenticatedAs($admin);
        $this->post('/salir');
        $this->post('/admin/ingresar', ['usuario' => 'martillero@colliers.test', 'password' => self::CLAVE])->assertRedirect('/admin?ingreso=1');
        $this->assertAuthenticatedAs($martillero);
    }

    public function test_ingreso_que_pierde_la_sesion_nunca_termina_en_silencio(): void
    {
        Notification::fake();
        $admin = $this->usuario(User::ROL_ADMIN, 'admin@colliers.test');
        $admin->forceFill(['debe_cambiar_clave' => true])->save();

        // Con sesión: la marca se quita y sigue su flujo (aquí, el cambio de clave obligatorio).
        $this->post('/admin/ingresar', ['usuario' => 'admin@colliers.test', 'password' => self::CLAVE]);
        $this->conCodigo($admin)->assertRedirect('/admin?ingreso=1');
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

    /** QA 21/10, 2.3: con las claves de Turnstile, el registro exige el captcha y lo valida en el servidor. */
    public function test_registro_con_turnstile_valida_el_token_en_el_servidor(): void
    {
        Storage::fake('local');
        Notification::fake();
        $this->get('/registro')->assertOk()->assertDontSee('cf-turnstile', false)->assertDontSee('challenges.cloudflare.com', false);

        config(['services.turnstile.site_key' => '1x00000000000000000000AA', 'services.turnstile.secret_key' => 'secreto-de-prueba']);
        $this->get('/registro')->assertOk()->assertSee('data-sitekey="1x00000000000000000000AA"', false)->assertSee('challenges.cloudflare.com/turnstile/v0/api.js', false);

        // Sin token: rechazado sin consultar a Cloudflare, sin cuenta y sin adjuntos guardados.
        // Un solo fake (los fakes de Http se apilan): la respuesta de Cloudflare cambia con $cloudflare.
        $cloudflare = fn () => Http::response(['success' => false, 'error-codes' => ['invalid-input-response']]);
        Http::fake(['challenges.cloudflare.com/*' => function () use (&$cloudflare) { return $cloudflare(); }]);
        $this->from('/registro')->post('/registro', $this->formulario())->assertRedirect('/registro')->assertSessionHasErrors('captcha');
        Http::assertNothingSent();
        $this->assertSame(0, User::count());
        $this->assertSame([], Storage::disk('local')->allFiles());

        // Token que Cloudflare rechaza.
        $this->from('/registro')->post('/registro', $this->formulario([Turnstile::CAMPO => 'token-falso']))->assertSessionHasErrors('captcha');
        $this->assertSame(0, User::count());

        // Cloudflare no responde: falla cerrado.
        $cloudflare = fn () => throw new \Illuminate\Http\Client\ConnectionException('sin red');
        $this->from('/registro')->post('/registro', $this->formulario([Turnstile::CAMPO => 'token']))->assertSessionHasErrors('captcha');
        $this->assertSame(0, User::count());

        // Token válido: entra, y la verificación llevó la clave secreta y el token.
        $cloudflare = fn () => Http::response(['success' => true]);
        $this->post('/registro', $this->formulario([Turnstile::CAMPO => 'token-bueno']))->assertRedirect(route('verification.notice') . '?ingreso=1');
        $this->assertSame(1, User::count());
        Http::assertSent(fn ($r) => $r->url() === 'https://challenges.cloudflare.com/turnstile/v0/siteverify'
            && $r['secret'] === 'secreto-de-prueba' && $r['response'] === 'token-bueno');
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

        // Sesión limpia: sin documentos conservados de los intentos anteriores (ver la prueba de más abajo).
        $this->flushSession();
        $sinCedula = $this->formulario(['rut' => '16.774.203-3']);
        unset($sinCedula['documentos']['ci-frente']);
        $this->post('/registro', $sinCedula)->assertSessionHasErrors('documentos.ci-frente');

        $this->flushSession();
        $exe = $this->formulario(['rut' => '16.774.203-3']);
        $exe['documentos']['domicilio'] = UploadedFile::fake()->create('virus.exe', 10, 'application/octet-stream');
        $this->post('/registro', $exe)->assertSessionHasErrors('documentos.domicilio');

        $this->flushSession();
        $this->post('/registro', $this->formulario(['rut' => '16.774.203-3', 'acepta' => null]))->assertSessionHasErrors('acepta');

        $this->assertSame(1, User::count());
        // No se creó ninguna cuenta: nada en postores/. Lo que quede en registro-temporal/ es del intento siguiente.
        $this->assertSame([], Storage::disk('local')->files('postores'));
    }

    public function test_al_fallar_la_validacion_conserva_lo_escrito_y_los_documentos_adjuntados(): void
    {
        Storage::fake('local');

        // Falla por teléfono: todo lo demás estaba bien.
        $completo = $this->formulario(['telefono' => 'nueve nueve']);
        $completo['documentos']['ci-frente'] = $this->pdf('cedula-frente.pdf');
        $completo['documentos']['domicilio'] = $this->pdf('cuenta-luz.pdf');
        $this->from('/registro')->post('/registro', $completo)->assertRedirect('/registro');

        // El formulario vuelve con el motivo, con lo escrito y con los documentos ya adjuntados.
        // (Las aserciones de sesión van después: en las pruebas reinician la sesión y envejecen el flash.)
        $this->get('/registro')->assertOk()
            ->assertSee('Revisa lo que falta')
            ->assertSee('Ingresa un teléfono válido, por ejemplo +56 9 1234 5678.')
            ->assertSee('value="Camila"', false)
            ->assertSee('value="nueva@correo.test"', false)
            ->assertSee('Ya adjuntado: cuenta-luz.pdf')
            ->assertSee('Ya adjuntado: cedula-frente.pdf')
            ->assertDontSee('Clave-segura-2026');

        // Los tres quedaron guardados en el disco privado, nunca en public.
        $archivos = Storage::disk('local')->allFiles();
        $this->assertCount(3, $archivos);
        $this->assertStringStartsWith('registro-temporal/', $archivos[0]);

        // Segundo intento sin volver a adjuntar: el registro entra con los documentos del primero.
        $sinDocumentos = $this->formulario();
        unset($sinDocumentos['documentos']);
        $this->post('/registro', $sinDocumentos)->assertSessionHasNoErrors()->assertRedirectContains('/verificar-correo');

        $postor = User::where('email', 'nueva@correo.test')->sole()->postor;
        $this->assertSame(['ci-dorso', 'ci-frente', 'domicilio'], $postor->documentos->pluck('tipo')->sort()->values()->all());
        $this->assertSame('cuenta-luz.pdf', $postor->documentos->firstWhere('tipo', 'domicilio')->nombre_original);
        foreach ($postor->documentos as $documento) {
            $this->assertStringStartsWith("postores/{$postor->id}/", $documento->ruta);
            Storage::disk('local')->assertExists($documento->ruta);
        }
        // Los temporales se borran al registrarse: solo quedan los definitivos del postor.
        $this->assertSame([], Storage::disk('local')->allFiles('registro-temporal'));
    }

    public function test_un_envio_mas_pesado_que_el_limite_del_servidor_lo_dice_en_vez_de_volver_en_blanco(): void
    {
        // Cuando el envío supera post_max_size, PHP descarta campos, archivos y token: el formulario volvía vacío
        // (o con un 419) sin explicación. Se simula con el límite configurado y un cuerpo que ya llegó vacío.
        config(['colliers.limite_post' => '1M']);

        $respuesta = $this->withServerVariables(['CONTENT_LENGTH' => (string) (3 * 1024 * 1024)])
            ->from('/registro')->post('/registro');

        // Explica el motivo y cuánto acepta el servidor, en vez del 419 «sesión expirada» o el formulario en blanco.
        $respuesta->assertStatus(413)
            ->assertSee('El envío es demasiado pesado')
            ->assertSee('este servidor acepta hasta 1 MB')
            ->assertSee('Volver al formulario');
        $this->assertSame(0, User::count());

        // Un envío normal sigue pasando.
        config(['colliers.limite_post' => '32M']);
        $this->post('/registro', $this->formulario())->assertSessionHasNoErrors();
    }

    public function test_un_documento_rechazado_no_se_conserva_pero_los_validos_si(): void
    {
        Storage::fake('local');

        $malo = $this->formulario();
        $malo['documentos']['ci-frente'] = $this->pdf('cedula-frente.pdf');
        $malo['documentos']['domicilio'] = UploadedFile::fake()->create('virus.exe', 10, 'application/octet-stream');
        $this->post('/registro', $malo)->assertSessionHasErrors('documentos.domicilio');

        // Se guardaron los dos válidos; el rechazado no (habría vuelto a fallar).
        $this->assertSame(2, count(Storage::disk('local')->allFiles()));
        $this->get('/registro')->assertOk()->assertSee('Ya adjuntado: cedula-frente.pdf')->assertDontSee('virus.exe');

        // Hay que adjuntar el que faltaba; los otros dos ya no.
        $segundo = $this->formulario();
        $segundo['documentos'] = ['domicilio' => $this->pdf('cuenta-agua.pdf')];
        $this->post('/registro', $segundo)->assertSessionHasNoErrors()->assertRedirectContains('/verificar-correo');

        $postor = User::where('email', 'nueva@correo.test')->sole()->postor;
        $this->assertSame('cuenta-agua.pdf', $postor->documentos->firstWhere('tipo', 'domicilio')->nombre_original);
        $this->assertSame('cedula-frente.pdf', $postor->documentos->firstWhere('tipo', 'ci-frente')->nombre_original);
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
        Notification::fake();
        $admin = $this->usuario(User::ROL_ADMIN, 'admin@colliers.test');
        $admin->forceFill(['debe_cambiar_clave' => true])->save();

        $this->post('/admin/ingresar', ['usuario' => 'admin@colliers.test', 'password' => self::CLAVE]);
        $this->conCodigo($admin);
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

    /** Segundo paso del ingreso de Administración: escribe el código que «llegó al correo». */
    private function conCodigo(User $user)
    {
        $aviso = Notification::sent($user, \App\Notifications\CodigoIngresoAviso::class)->last();
        $this->assertNotNull($aviso, 'No se envió el código de ingreso');

        return $this->post(route('admin.ingresar.codigo.verificar'), ['codigo' => $aviso->codigo]);
    }

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

    /** PDF real (mínimo válido): la validación `mimes` mira el contenido, no el nombre ni el mime declarado. */
    private function pdf(string $nombre): UploadedFile
    {
        $ruta = tempnam(sys_get_temp_dir(), 'colliers-pdf-');
        file_put_contents($ruta, "%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF\n");
        $this->temporales[] = $ruta;

        return new UploadedFile($ruta, $nombre, 'application/pdf', null, true);
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
