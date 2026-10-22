<?php

namespace Tests\Feature;

use App\Autenticacion\SegundoFactor;
use App\Models\AccessLog;
use App\Models\CodigoIngreso;
use App\Models\Configuracion;
use App\Models\DispositivoConfiable;
use App\Models\NotificacionLog;
use App\Models\User;
use App\Notifications\CodigoIngresoAviso;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Tests\TestCase;

/**
 * Segundo factor por correo para Administración al ingresar al panel (22/10): sin el código correcto no hay sesión.
 */
class SegundoFactorTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-22 15:00:00', 'UTC'));
        Configuracion::sembrarDefectos();
        $this->admin = User::create(['name' => 'Carolina Méndez', 'email' => 'admin@colliers.test', 'password' => 'x-clave-larga-1', 'rol' => User::ROL_ADMIN, 'estado' => User::ESTADO_ACTIVO]);
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    private function ingresar(string $correo = 'admin@colliers.test', bool $recordarSesion = true)
    {
        return $this->post('/admin/ingresar', ['usuario' => $correo, 'password' => 'x-clave-larga-1'] + ($recordarSesion ? ['remember' => 'on'] : []));
    }

    /** El código que «llegó al correo»: el de la última notificación enviada. */
    private function ultimoCodigo(): string
    {
        $codigo = null;
        Notification::assertSentTo($this->admin, CodigoIngresoAviso::class, function (CodigoIngresoAviso $aviso) use (&$codigo) {
            $codigo = $aviso->codigo;

            return true;
        });

        return $codigo;
    }

    private function avanzar(int $segundos): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::now('UTC')->addSeconds($segundos));
    }

    public function test_la_clave_correcta_no_abre_sesion_hasta_escribir_el_codigo(): void
    {
        Notification::fake();

        $this->ingresar()->assertRedirect(route('admin.ingresar.codigo'));
        $this->assertGuest();
        $this->get('/admin')->assertRedirect(route('admin.ingresar'));

        $codigo = $this->ultimoCodigo();
        $this->assertMatchesRegularExpression('/^\d{6}$/', $codigo);
        // En la base solo queda el hash.
        $guardado = CodigoIngreso::sole();
        $this->assertNotSame($codigo, $guardado->codigo_hash);
        $this->assertSame('2026-10-22 15:10:00', $guardado->expira_en->format('Y-m-d H:i:s'));

        $this->get(route('admin.ingresar.codigo'))->assertOk()
            ->assertSee('Revisa tu correo')->assertSee('a••••@colliers.test')->assertSee('Vence en 10 minutos')
            ->assertSee('No pedir el código en este dispositivo por 7 días');

        $this->post(route('admin.ingresar.codigo.verificar'), ['codigo' => $codigo])->assertRedirect();
        $this->assertAuthenticatedAs($this->admin);
        $this->get('/admin')->assertOk();
        $this->assertNotNull(CodigoIngreso::sole()->usado_en);
        $this->assertTrue(AccessLog::where('evento', 'segundo_factor_correcto')->exists());
        $this->assertTrue(AccessLog::where('evento', 'ingreso')->where('user_id', $this->admin->id)->exists());

        // Un solo uso: con la sesión cerrada, el mismo código no sirve.
        $this->post(route('logout'));
        $this->ingresar();
        $this->post(route('admin.ingresar.codigo.verificar'), ['codigo' => $codigo])->assertSessionHasErrors('codigo');
        $this->assertGuest();
    }

    public function test_codigo_incorrecto_descuenta_intentos_y_al_agotarlos_deja_de_servir(): void
    {
        Notification::fake();
        $this->ingresar();
        $codigo = $this->ultimoCodigo();
        $malo = $codigo === '000000' ? '111111' : '000000';

        for ($i = 4; $i >= 1; $i--) {
            $this->post(route('admin.ingresar.codigo.verificar'), ['codigo' => $malo]);
            $this->get(route('admin.ingresar.codigo'))->assertSee($i === 1 ? 'Te queda 1 intento' : "Te quedan {$i} intentos");
        }
        $this->post(route('admin.ingresar.codigo.verificar'), ['codigo' => $malo]);
        $this->get(route('admin.ingresar.codigo'))->assertSee('se agotaron los intentos');

        // Ni siquiera el correcto sirve ya.
        $this->post(route('admin.ingresar.codigo.verificar'), ['codigo' => $codigo]);
        $this->assertGuest();
        $this->assertNotNull(CodigoIngreso::sole()->descartado_en);
        $this->assertTrue(AccessLog::where('evento', 'segundo_factor_agotado')->exists());
    }

    public function test_el_codigo_vence(): void
    {
        Notification::fake();
        $this->ingresar();
        $codigo = $this->ultimoCodigo();
        $this->avanzar(10 * 60 + 1);

        $this->post(route('admin.ingresar.codigo.verificar'), ['codigo' => $codigo]);
        $this->get(route('admin.ingresar.codigo'))->assertSee('El código venció');
        $this->assertGuest();
    }

    public function test_reenvio_con_espera_tope_por_hora_y_el_anterior_deja_de_servir(): void
    {
        Notification::fake();
        $this->ingresar();
        $primero = $this->ultimoCodigo();

        // Antes del minuto, no se envía otro.
        $this->post(route('admin.ingresar.codigo.reenviar'));
        $this->get(route('admin.ingresar.codigo'))->assertSee('Espera');
        Notification::assertSentToTimes($this->admin, CodigoIngresoAviso::class, 1);

        $this->avanzar(61);
        $this->post(route('admin.ingresar.codigo.reenviar'));
        $this->get(route('admin.ingresar.codigo'))->assertSee('Te enviamos un código nuevo');
        $segundo = $this->ultimoCodigo();

        if ($primero !== $segundo) {
            $this->post(route('admin.ingresar.codigo.verificar'), ['codigo' => $primero]);
            $this->assertGuest();
        }

        // Tope: 5 por hora y cuenta (el ingreso cuenta), aunque se espere el minuto entre uno y otro.
        foreach (range(1, 3) as $n) {
            $this->avanzar(61);
            $this->post(route('admin.ingresar.codigo.reenviar'));
        }
        $this->avanzar(61);
        $this->post(route('admin.ingresar.codigo.reenviar'));
        $this->get(route('admin.ingresar.codigo'))->assertSee('Ya enviamos 5 códigos a esta cuenta en la última hora');
        Notification::assertSentToTimes($this->admin, CodigoIngresoAviso::class, 5);
        // Ingresar de nuevo con la clave tampoco salta el tope.
        $this->post(route('admin.ingresar.codigo.cancelar'));
        $this->ingresar();
        Notification::assertSentToTimes($this->admin, CodigoIngresoAviso::class, 5);
        $this->assertTrue(AccessLog::where('evento', 'segundo_factor_tope')->exists());
    }

    public function test_si_el_correo_falla_lo_dice_queda_registrado_y_no_deja_entrar(): void
    {
        Mail::extend('caido', fn () => new class extends AbstractTransport
        {
            protected function doSend(SentMessage $message): void
            {
                throw new TransportException('Connection could not be established with host "smtp.test:587"');
            }

            public function __toString(): string
            {
                return 'caido://';
            }
        });
        config(['mail.mailers.caido' => ['transport' => 'caido'], 'mail.default' => 'caido']);

        $this->ingresar()->assertRedirect(route('admin.ingresar.codigo'));
        $this->assertGuest();
        $this->get(route('admin.ingresar.codigo'))->assertOk()
            ->assertSee('No pudimos enviar el código a tu correo')->assertSee('Sin el código no se puede entrar')
            ->assertDontSee('Te enviamos un código de 6 dígitos');

        $fila = NotificacionLog::sole();
        $this->assertSame('fallida', $fila->estado);
        $this->assertSame('CodigoIngresoAviso', $fila->tipo);
        $this->assertStringContainsString('could not be established', $fila->error);
        $this->assertTrue(AccessLog::where('evento', 'segundo_factor_envio_fallido')->exists());
        $this->assertNotNull(CodigoIngreso::sole()->descartado_en);
        $this->get('/admin')->assertRedirect(route('admin.ingresar'));
    }

    public function test_fuera_de_local_un_correo_que_no_sale_a_internet_no_se_da_por_enviado(): void
    {
        config(['colliers.seguridad.codigo_por_log' => false, 'mail.default' => 'log']);

        $this->ingresar();
        $this->get(route('admin.ingresar.codigo'))->assertSee('el correo saliente del sitio no está configurado para salir a Internet');
        $this->assertGuest();
        $this->assertSame(0, NotificacionLog::count());
    }

    public function test_sin_correo_la_terminal_del_servidor_entrega_un_codigo_de_emergencia(): void
    {
        config(['colliers.seguridad.codigo_por_log' => false, 'mail.default' => 'log']);
        $this->ingresar();
        $this->assertGuest();

        // El código solo se ve en la salida del comando: se toma de ahí.
        $this->assertSame(0, \Illuminate\Support\Facades\Artisan::call('colliers:codigo-ingreso', ['email' => 'admin@colliers.test']));
        preg_match('/: (\d{6}) \(/', \Illuminate\Support\Facades\Artisan::output(), $m);
        $codigo = $m[1] ?? null;
        $this->assertNotNull($codigo);

        $this->post(route('admin.ingresar.codigo.verificar'), ['codigo' => $codigo])->assertRedirect();
        $this->assertAuthenticatedAs($this->admin);
        $this->assertTrue(AccessLog::where('evento', 'segundo_factor_terminal')->exists());
        $this->artisan('colliers:codigo-ingreso', ['email' => 'nadie@colliers.test'])->assertFailed();
    }

    public function test_el_correo_lleva_el_codigo_y_el_asunto_no(): void
    {
        $correo = (new CodigoIngresoAviso($this->admin, '482913', 10))->toMail($this->admin);
        $this->assertSame('Tu código de ingreso · Remates Colliers', $correo->subject);
        $this->assertContains('482913', $correo->introLines);
        $this->assertStringNotContainsString('482913', $correo->subject);

        // Una plantilla editada sin {{ codigo }} igual entrega el código.
        \App\Models\PlantillaCorreo::create(['clave' => 'codigo_ingreso', 'asunto' => 'Ingreso', 'cuerpo' => 'Hola.', 'boton' => null]);
        $correo = (new CodigoIngresoAviso($this->admin, '482913', 10))->toMail($this->admin);
        $this->assertContains('Código: 482913', $correo->introLines);
    }

    public function test_recordar_el_dispositivo_y_revocarlo_desde_sesiones_activas(): void
    {
        Notification::fake();
        $this->ingresar();
        $respuesta = $this->post(route('admin.ingresar.codigo.verificar'), ['codigo' => $this->ultimoCodigo(), 'recordar_dispositivo' => '1']);
        $cookie = $respuesta->getCookie(SegundoFactor::COOKIE)?->getValue();
        $this->assertNotNull($cookie);
        $dispositivo = DispositivoConfiable::sole();
        $this->assertSame('2026-10-29 15:00:00', $dispositivo->expira_en->format('Y-m-d H:i:s'));
        $this->assertStringNotContainsString(explode('|', $cookie)[1], $dispositivo->token_hash);

        // Con la cookie, la clave basta.
        $this->post(route('logout'));
        $this->withCookie(SegundoFactor::COOKIE, $cookie);
        $this->ingresar()->assertRedirect();
        $this->assertAuthenticatedAs($this->admin);
        Notification::assertSentToTimes($this->admin, CodigoIngresoAviso::class, 1);

        // Aparece en «Sesiones activas» y se puede olvidar.
        $this->get(route('cuenta.sesiones'))->assertOk()->assertSee('DISPOSITIVOS QUE NO PIDEN CÓDIGO')->assertSee('RECORDADO HASTA 29-10-2026');
        $this->delete(route('cuenta.dispositivos.olvidar', $dispositivo->id))->assertSessionHas('estado');
        $this->assertNotNull($dispositivo->fresh()->revocado_en);

        $this->post(route('logout'));
        $this->withCookie(SegundoFactor::COOKIE, $cookie);
        $this->ingresar()->assertRedirect(route('admin.ingresar.codigo'));
        $this->assertGuest();
    }

    public function test_la_cookie_vence_es_de_su_cuenta_y_cambiar_la_clave_la_revoca(): void
    {
        Notification::fake();
        $this->ingresar();
        $cookie = $this->post(route('admin.ingresar.codigo.verificar'), ['codigo' => $this->ultimoCodigo(), 'recordar_dispositivo' => '1'])
            ->getCookie(SegundoFactor::COOKIE)->getValue();

        // Otra cuenta de administración con la cookie de esta: pide código.
        $otra = User::create(['name' => 'Otra', 'email' => 'otra@colliers.test', 'password' => 'x-clave-larga-1', 'rol' => User::ROL_ADMIN, 'estado' => User::ESTADO_ACTIVO]);
        $this->post(route('logout'));
        $this->withCookie(SegundoFactor::COOKIE, $cookie);
        $this->ingresar('otra@colliers.test')->assertRedirect(route('admin.ingresar.codigo'));
        $this->post(route('admin.ingresar.codigo.cancelar'));

        // Vencida (8 días después): pide código.
        $this->avanzar(8 * 86400);
        $this->withCookie(SegundoFactor::COOKIE, $cookie);
        $this->ingresar()->assertRedirect(route('admin.ingresar.codigo'));
        $this->post(route('admin.ingresar.codigo.cancelar'));

        // Restablecer la clave desde «06 Usuarios» olvida los dispositivos.
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-22 16:00:00', 'UTC'));
        $this->assertSame(1, DispositivoConfiable::vigentes()->count());
        $this->actingAs($otra)->post(route('admin.usuarios.restablecer-clave', $this->admin));
        $this->assertSame(0, DispositivoConfiable::vigentes()->count());
    }

    public function test_postores_martilleros_y_editores_no_lo_tienen_por_ahora(): void
    {
        Notification::fake();
        foreach ([User::ROL_MARTILLERO, User::ROL_EDITOR] as $rol) {
            User::create(['name' => $rol, 'email' => "{$rol}@colliers.test", 'password' => 'x-clave-larga-1', 'rol' => $rol, 'estado' => User::ESTADO_ACTIVO]);
            $this->ingresar("{$rol}@colliers.test")->assertRedirect();
            $this->assertAuthenticated();
            $this->post(route('logout'));
        }
        Notification::assertNothingSent();
        $this->assertSame(0, CodigoIngreso::count());
    }

    public function test_clave_incorrecta_no_envia_codigo_y_sin_ingreso_pendiente_vuelve_al_acceso(): void
    {
        Notification::fake();
        $this->post('/admin/ingresar', ['usuario' => 'admin@colliers.test', 'password' => 'otra-clave'])->assertSessionHasErrors('usuario');
        Notification::assertNothingSent();

        $this->get(route('admin.ingresar.codigo'))->assertRedirect(route('admin.ingresar'));
        $this->post(route('admin.ingresar.codigo.verificar'), ['codigo' => '123456'])->assertRedirect(route('admin.ingresar'));
        $this->assertGuest();

        // El ingreso pendiente dura 30 minutos.
        $this->ingresar();
        $this->avanzar(31 * 60);
        $this->get(route('admin.ingresar.codigo'))->assertRedirect(route('admin.ingresar'));
    }
}
