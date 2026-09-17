<?php

namespace Tests\Feature;

use App\Models\Configuracion;
use App\Models\Remate;
use App\Models\User;
use App\Support\Sitio;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\MailManager;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Bloque V · configuración autoadministrable, correo saliente y UF. */
class ConfiguracionTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Configuracion::sembrarDefectos();
        $this->admin = User::create(['name' => 'Admin', 'email' => 'admin@colliers.test', 'password' => 'x-clave-larga-1', 'rol' => User::ROL_ADMIN, 'estado' => User::ESTADO_ACTIVO]);
    }

    /** Envío completo del formulario, con los valores actuales salvo los cambios. */
    private function formulario(string $seccion, array $cambios = []): array
    {
        $config = [];
        foreach (Configuracion::camposDe($seccion) as $clave => $datos) {
            if (! empty($datos['solo_lectura'])) {
                continue;
            }
            $valor = Configuracion::valor($clave);
            $config[$clave] = match ($datos['tipo']) {
                'lista_montos' => implode(', ', (array) $valor),
                'booleano' => $valor ? '1' : '0',
                'secreto' => '',
                default => $valor,
            };
        }

        return ['config' => $cambios + $config];
    }

    public function test_solo_administradores_y_la_pantalla_muestra_grupos_y_sistema(): void
    {
        $martillero = User::create(['name' => 'M', 'email' => 'm@colliers.test', 'password' => 'x-clave-larga-1', 'rol' => User::ROL_MARTILLERO, 'estado' => User::ESTADO_ACTIVO]);
        $this->actingAs($martillero)->get('/admin/configuracion')->assertForbidden();

        // Una pantalla por tema, con submenú (17/09).
        $this->actingAs($this->admin)->get('/admin/configuracion')->assertRedirect(route('admin.configuracion.seccion', 'remates'));
        $this->actingAs($this->admin)->get(route('admin.configuracion.seccion', 'remates'))->assertOk()
            ->assertSee('Remates y pujas')->assertSee('MARGEN DE LIQUIDACIÓN (SEGUNDOS)')->assertSee('PAUSA ENTRE LOTES (MINUTOS)')
            ->assertSee('Plantillas de correo')->assertSee('Sistema')
            ->assertDontSee('SERVIDOR SMTP')->assertDontSee('INTENTOS DE INGRESO ANTES DE BLOQUEAR');

        $this->actingAs($this->admin)->get(route('admin.configuracion.seccion', 'garantias'))->assertOk()
            ->assertSee('GARANTÍA (% DEL PRECIO BASE)')->assertSee('Datos para constituir la garantía');
        $this->actingAs($this->admin)->get(route('admin.configuracion.seccion', 'correo'))->assertOk()
            ->assertSee('SERVIDOR SMTP')->assertSee('Probar conexión')->assertSee('Enviar correo de prueba');
        $this->actingAs($this->admin)->get(route('admin.configuracion.seccion', 'seguridad'))->assertOk()
            ->assertSee('DURACIÓN DE LA SESIÓN (MINUTOS)');
        $this->actingAs($this->admin)->get(route('admin.configuracion.seccion', 'sitio'))->assertOk()
            ->assertSee('Unidad de fomento')->assertSee('CORREO DE CONTACTO Y DE COMPROBANTES');
        $this->actingAs($this->admin)->get(route('admin.configuracion.seccion', 'sistema'))->assertOk()->assertSee('OPcache (web)');
        $this->actingAs($this->admin)->get(route('admin.configuracion.seccion', 'inventada'))->assertNotFound();
    }

    public function test_guardar_valores_rige_de_inmediato_sin_deploy(): void
    {
        $guardar = fn (string $seccion, array $cambios) => $this->actingAs($this->admin)
            ->put(route('admin.configuracion.update', $seccion), $this->formulario($seccion, $cambios))->assertSessionHas('estado');

        $guardar('remates', ['incremento_minimo' => '250000', 'pujas_rapidas' => '250.000, 1.000.000; 2000000', 'margen_liquidacion_segundos' => '5']);
        $guardar('garantias', ['porcentaje_garantia' => '7.5']);
        $guardar('sitio', ['filtro_garantia_visible' => '1', 'contacto_correo' => 'subastas@colliers.test', 'uf_fuente' => 'manual', 'uf_valor' => '39500.12']);
        $guardar('seguridad', ['login_intentos_maximos' => '3']);

        $this->assertSame(250000, Configuracion::valor('incremento_minimo'));
        $this->assertSame([250000, 1000000, 2000000], Configuracion::valor('pujas_rapidas'));
        $this->assertSame(5, Configuracion::valor('margen_liquidacion_segundos'));
        $this->assertTrue(Configuracion::valor('filtro_garantia_visible'));
        $this->assertSame('subastas@colliers.test', Sitio::correo());
        $this->assertSame(39500.12, Sitio::uf());
        $remate = Remate::create(['folio' => 'R-1', 'slug' => 'r-1', 'titulo' => 'R']);
        $this->assertSame(250000, $remate->incrementoMinimo());
        $this->assertSame('7.5', $remate->porcentajeGarantia());
        auth()->logout();
        $this->get('/ingresar')->assertSee('subastas@colliers.test');

        // Desmarcar la casilla: el campo oculto envía 0.
        $guardar('sitio', ['filtro_garantia_visible' => '0']);
        $this->assertFalse(Configuracion::valor('filtro_garantia_visible'));

        // Una pantalla no puede tocar valores de otra sección.
        $this->actingAs($this->admin)->put(route('admin.configuracion.update', 'seguridad'),
            ['config' => ['incremento_minimo' => '1'] + $this->formulario('seguridad')['config']])->assertSessionHas('estado');
        $this->assertSame(250000, Configuracion::valor('incremento_minimo'));
    }

    public function test_validacion_en_espanol_y_nada_se_guarda_con_errores(): void
    {
        $this->actingAs($this->admin)->put(route('admin.configuracion.update', 'remates'), $this->formulario('remates', [
            'margen_liquidacion_segundos' => '0', 'incremento_minimo' => '500000',
        ]))->assertSessionHasErrors('config.margen_liquidacion_segundos');
        $this->actingAs($this->admin)->put(route('admin.configuracion.update', 'garantias'), $this->formulario('garantias', [
            'porcentaje_garantia' => '150',
        ]))->assertSessionHasErrors('config.porcentaje_garantia');
        $this->assertStringContainsString('garantía', session('errors')->first('config.porcentaje_garantia'), 'el error nombra el campo en español');
        $this->actingAs($this->admin)->put(route('admin.configuracion.update', 'sitio'), $this->formulario('sitio', [
            'contacto_correo' => 'no-es-correo', 'uf_fuente' => 'inventada',
        ]))->assertSessionHasErrors(['config.contacto_correo', 'config.uf_fuente']);

        $this->assertSame(100000, Configuracion::valor('incremento_minimo'), 'nada se guarda con errores');
        // Al volver con los datos ingresados, la lista de montos (ya separada) se muestra otra vez como texto.
        $this->actingAs($this->admin)->get(route('admin.configuracion.seccion', 'remates'))->assertOk()->assertSee('100.000, 500.000, 1.000.000');
    }

    public function test_clave_smtp_cifrada_se_conserva_si_se_deja_vacia_y_se_aplica_al_enviar(): void
    {
        $this->actingAs($this->admin)->put(route('admin.configuracion.update', 'correo'), $this->formulario('correo', [
            'correo_modo' => 'smtp', 'smtp_host' => 'mail.colliers.test', 'smtp_puerto' => '465', 'smtp_cifrado' => 'ssl',
            'smtp_usuario' => 'remates', 'smtp_clave' => 'secreta-123', 'correo_remitente' => 'no-responder@colliers.test',
        ]))->assertSessionHas('estado');

        $guardado = Configuracion::where('clave', 'smtp_clave')->value('valor');
        $this->assertNotSame('secreta-123', $guardado, 'nunca en claro en la base');
        $this->assertSame('secreta-123', Crypt::decryptString($guardado));
        $this->actingAs($this->admin)->get(route('admin.configuracion.seccion', 'correo'))->assertDontSee('secreta-123')->assertSee('(configurada)');

        $this->actingAs($this->admin)->put(route('admin.configuracion.update', 'correo'),
            $this->formulario('correo', ['correo_modo' => 'smtp', 'smtp_host' => 'mail.colliers.test', 'smtp_clave' => '']));
        $this->assertSame('secreta-123', Configuracion::valor('smtp_clave'));

        $this->app->forgetInstance('mail.manager');
        $this->app->make(MailManager::class);
        $this->assertSame('smtp', config('mail.default'));
        $this->assertSame('mail.colliers.test', config('mail.mailers.smtp.host'));
        $this->assertSame('secreta-123', config('mail.mailers.smtp.password'));
        $this->assertSame('no-responder@colliers.test', config('mail.from.address'));
    }

    public function test_correo_de_prueba_en_modo_registro(): void
    {
        Configuracion::guardar('correo_modo', 'log');

        $this->actingAs($this->admin)->post('/admin/configuracion/probar-correo', ['destino' => 'jonas@correo.test'])
            ->assertSessionHas('estado', fn (string $m) => str_contains($m, 'registrado en el log'));
        $this->actingAs($this->admin)->post('/admin/configuracion/probar-correo', ['destino' => 'no'])->assertSessionHasErrors('destino');
    }

    public function test_probar_conexion_smtp_dice_exactamente_que_fallo(): void
    {
        // Modo registro: no hay servidor al que conectarse y se explica.
        Configuracion::guardar('correo_modo', 'log');
        $this->actingAs($this->admin)->post('/admin/configuracion/probar-conexion')
            ->assertSessionHas('error', fn (string $m) => str_contains($m, 'No enviar') && str_contains($m, 'Cambia el modo a SMTP'));

        // Servidor que no existe: lo dice el paso de DNS, sin enviar nada.
        Configuracion::guardar('correo_modo', 'smtp');
        Configuracion::guardar('smtp_host', 'servidor-que-no-existe.colliers-invalido');
        $this->actingAs($this->admin)->post('/admin/configuracion/probar-conexion')
            ->assertSessionHas('error', fn (string $m) => str_contains($m, 'No se pudo resolver el servidor'));

        // Puerto cerrado en un host que sí resuelve.
        Configuracion::guardar('smtp_host', '127.0.0.1');
        Configuracion::guardar('smtp_puerto', '9');
        $respuesta = $this->actingAs($this->admin)->post('/admin/configuracion/probar-conexion');
        $mensaje = (string) $respuesta->getSession()->get('error');
        $this->assertStringContainsString('127.0.0.1:9', $mensaje, "mensaje recibido: {$mensaje}");
        $this->assertStringContainsString('✓ Nombre del servidor', $mensaje, 'el detalle dice qué pasos sí funcionaron');
        $this->assertStringContainsString('Puerto 9', $mensaje);
    }

    public function test_plantillas_de_correo_se_editan_previsualizan_y_se_restauran(): void
    {
        $this->actingAs($this->admin)->get(route('admin.configuracion.seccion', 'plantillas'))->assertOk()
            ->assertSee('Cuenta aprobada')->assertSee('Resumen del remate (administración)')->assertSee('ORIGINAL');

        $this->actingAs($this->admin)->get(route('admin.configuracion.plantilla', 'cuenta_rechazada'))->assertOk()
            ->assertSee('{{ motivo }}')->assertSee('Motivo escrito por quien rechazó')->assertSee('Vista previa');

        // Vista previa sin guardar: usa datos de ejemplo y no toca la plantilla.
        $this->actingAs($this->admin)->put(route('admin.configuracion.plantilla.previa', 'cuenta_rechazada'), [
            'asunto' => 'Revisamos tu cuenta', 'cuerpo' => "Hola de nuevo.\n\nMotivo: {{ motivo }}", 'boton' => null,
        ])->assertSessionHas('previa', fn (array $p) => $p['asunto'] === 'Revisamos tu cuenta'
            && str_contains($p['parrafos'][1], 'El comprobante no corresponde'));
        $this->assertSame(0, \App\Models\PlantillaCorreo::count());

        // Guardar: el correo sale con el texto nuevo.
        $this->actingAs($this->admin)->put(route('admin.configuracion.plantilla.update', 'cuenta_rechazada'), [
            'asunto' => 'Revisamos tu cuenta', 'cuerpo' => "Revisamos tus antecedentes.\n\nMotivo: {{ motivo }}\n\nEscríbenos a {{ contacto }}.",
        ])->assertSessionHas('estado');
        $render = \App\Correo\Plantillas::render('cuenta_rechazada', ['motivo' => 'Faltan documentos']);
        $this->assertSame('Revisamos tu cuenta', $render['asunto']);
        $this->assertSame(['Revisamos tus antecedentes.', 'Motivo: Faltan documentos', 'Escríbenos a ' . Sitio::correo() . '.'], $render['parrafos']);

        $this->actingAs($this->admin)->get(route('admin.configuracion.seccion', 'plantillas'))->assertSee('EDITADA');
        $this->actingAs($this->admin)->put(route('admin.configuracion.plantilla.update', 'cuenta_rechazada'), ['asunto' => '', 'cuerpo' => ''])
            ->assertSessionHasErrors(['asunto', 'cuerpo']);

        // Restaurar: vuelve el texto del código.
        $this->actingAs($this->admin)->delete(route('admin.configuracion.plantilla.restaurar', 'cuenta_rechazada'))->assertSessionHas('estado');
        $this->assertSame('No pudimos aprobar tu cuenta', \App\Correo\Plantillas::render('cuenta_rechazada')['asunto']);

        $this->actingAs($this->admin)->get(route('admin.configuracion.plantilla', 'inventada'))->assertNotFound();
        $martillero = User::create(['name' => 'M', 'email' => 'm@colliers.test', 'password' => 'x-clave-larga-1', 'rol' => User::ROL_MARTILLERO, 'estado' => User::ESTADO_ACTIVO]);
        $this->actingAs($martillero)->get(route('admin.configuracion.plantilla', 'cuenta_rechazada'))->assertForbidden();
    }

    public function test_notificaciones_guarda_los_interruptores_y_muestra_los_envios(): void
    {
        $this->actingAs($this->admin)->get(route('admin.configuracion.seccion', 'notificaciones'))->assertOk()
            ->assertSee('Qué se envía')->assertSee('Últimos envíos')->assertSee('Siempre (correo de la cuenta)');

        $this->actingAs($this->admin)->put(route('admin.configuracion.update', 'notificaciones'),
            $this->formulario('notificaciones', ['recordatorio_horas_antes' => '12'])
            + ['avisos' => ['cuenta_aprobada' => '0', 'adjudicacion' => '1', 'bienvenida' => '0']])->assertSessionHas('estado');

        $this->assertSame(12, Configuracion::valor('recordatorio_horas_antes'));
        $this->assertFalse(\App\Correo\Avisos::activo('cuenta_aprobada'));
        $this->assertTrue(\App\Correo\Avisos::activo('adjudicacion'));
        $this->assertTrue(\App\Correo\Avisos::activo('bienvenida'), 'los correos de la cuenta no se pueden apagar');
    }

    public function test_duracion_de_la_sesion_se_aplica_desde_el_panel(): void
    {
        $this->actingAs($this->admin)->put(route('admin.configuracion.update', 'seguridad'), $this->formulario('seguridad', ['sesion_minutos' => '30']))
            ->assertSessionHas('estado');
        $this->assertSame(30, Configuracion::valor('sesion_minutos'));

        auth()->logout();
        $this->get('/ingresar')->assertOk();
        $this->assertSame(30, config('session.lifetime'), 'la petición siguiente ya usa la duración nueva');

        // Fuera de rango: el formulario lo rechaza (15 min a 12 h).
        $this->actingAs($this->admin)->put(route('admin.configuracion.update', 'seguridad'), $this->formulario('seguridad', ['sesion_minutos' => '5']))
            ->assertSessionHasErrors('config.sesion_minutos');
        $this->actingAs($this->admin)->put(route('admin.configuracion.update', 'seguridad'), $this->formulario('seguridad', ['sesion_minutos' => '1000']))
            ->assertSessionHasErrors('config.sesion_minutos');
    }

    public function test_uf_automatica_desde_mindicador_y_tolerante_a_fallas(): void
    {
        Http::fake(['mindicador.cl/*' => Http::response(['serie' => [['fecha' => '2026-09-17T03:00:00.000Z', 'valor' => 39521.47]]])]);
        $this->artisan('colliers:actualizar-uf')->assertSuccessful();
        $this->assertSame(39521.47, Sitio::uf());
        $this->assertSame('2026-09-17', Configuracion::valor('uf_fecha'));

        // Ya es la de hoy: no vuelve a consultar.
        $this->travelTo(\Carbon\CarbonImmutable::parse('2026-09-17 15:00', 'America/Santiago'));
        Http::fake(fn () => throw new \RuntimeException('no debería consultar'));
        $this->artisan('colliers:actualizar-uf')->assertSuccessful();

        // Servicio caído: se mantiene el último valor.
        Http::fake(['mindicador.cl/*' => Http::response('error', 500)]);
        $this->artisan('colliers:actualizar-uf --forzar')->assertFailed();
        $this->assertSame(39521.47, Sitio::uf());

        // Fuente manual: no consulta.
        Configuracion::guardar('uf_fuente', 'manual');
        Http::fake(fn () => throw new \RuntimeException('no debería consultar'));
        $this->artisan('colliers:actualizar-uf')->assertSuccessful();
    }
}
