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

    public function test_el_correo_de_prueba_solo_afirma_lo_que_dijo_el_servidor(): void
    {
        // Servidor SMTP falso que acepta la conexión y responde a cada comando: así se ve la respuesta literal.
        [$puerto, $proceso] = $this->servidorFalso();
        Configuracion::guardar('correo_modo', 'smtp');
        Configuracion::guardar('smtp_host', '127.0.0.1');
        Configuracion::guardar('smtp_puerto', (string) $puerto);
        Configuracion::guardar('smtp_cifrado', 'ninguno');
        Configuracion::guardar('smtp_usuario', '');
        Configuracion::guardar('correo_remitente', 'remates@colliers.test');

        $this->actingAs($this->admin)->post('/admin/configuracion/probar-correo', ['destino' => 'jonas@correo.test'])
            ->assertSessionHas('estado', function (string $m) {
                $this->assertStringContainsString('ACEPTÓ el mensaje', $m);
                $this->assertStringContainsString('250 2.0.0 Ok: queued as PRUEBA123', $m, 'la respuesta literal del servidor, con su código');
                $this->assertStringContainsString('no garantiza que llegue', $m);

                return true;
            });
        proc_terminate($proceso);
    }

    public function test_avisa_cuando_el_remitente_no_es_el_usuario_autenticado(): void
    {
        $aviso = \App\Correo\DiagnosticoSmtp::avisoRemitente('remates@colliers.cl', 'noreply@rematescolliers.sandbox');
        $this->assertStringContainsString('no coincide con el usuario autenticado', (string) $aviso);
        $this->assertStringContainsString('rechaza el envío', (string) $aviso);
        $this->assertNull(\App\Correo\DiagnosticoSmtp::avisoRemitente('remates@colliers.cl', 'remates@colliers.cl'));
        $this->assertNull(\App\Correo\DiagnosticoSmtp::avisoRemitente('remates@colliers.cl', ''), 'sin usuario no hay nada que comparar');
        $this->assertStringContainsString('comparten dominio', (string) \App\Correo\DiagnosticoSmtp::avisoRemitente('avisos@colliers.cl', 'remates@colliers.cl'));

        // La pantalla lo avisa antes de intentar enviar.
        Configuracion::guardar('correo_modo', 'smtp');
        Configuracion::guardar('smtp_host', 'mail.colliers.test');
        Configuracion::guardar('smtp_usuario', 'noreply@rematescolliers.sandbox');
        Configuracion::guardar('correo_remitente', 'remates@colliers.cl');
        $this->actingAs($this->admin)->get(route('admin.configuracion.seccion', 'correo'))->assertOk()
            ->assertSee('no coincide con el usuario autenticado');
    }

    public function test_registro_de_correos_con_filtros_busqueda_y_error_completo(): void
    {
        $error = str_repeat('Expected response code "250" but got code "553", with message "553 Sender address rejected". ', 5);
        \App\Models\NotificacionLog::create(['canal' => 'correo', 'tipo' => 'CuentaRevisadaAviso', 'destinatario' => 'ana@correo.test',
            'asunto' => 'Tu cuenta fue aprobada', 'estado' => 'aceptada', 'transporte' => 'smtp', 'remitente' => 'remates@colliers.test',
            'respuesta' => '250 2.0.0 Ok: queued as 4X7B2', 'message_id' => 'abc123@colliers.test', 'enviada_en' => now('UTC')]);
        \App\Models\NotificacionLog::create(['canal' => 'correo', 'tipo' => 'AdjudicacionAviso', 'destinatario' => 'beto@correo.test',
            'asunto' => 'Te adjudicaste la propiedad', 'estado' => 'fallida', 'error' => $error]);
        \App\Models\NotificacionLog::create(['canal' => 'correo', 'tipo' => 'RecordatorioRemateAviso', 'destinatario' => 'carla@correo.test',
            'asunto' => 'Tu remate comienza pronto', 'estado' => 'pendiente']);

        $registro = route('admin.configuracion.seccion', 'correos');
        $this->actingAs($this->admin)->get($registro)->assertOk()
            ->assertSee('Registro de correos')->assertSee('ana@correo.test')->assertSee('beto@correo.test')->assertSee('carla@correo.test')
            ->assertSee('aceptados por el servidor')->assertSee('553 Sender address rejected')
            ->assertSee('no garantiza que haya llegado a la bandeja')
            // Cada fila se abre con todo lo que respondió el transporte.
            ->assertSee('250 2.0.0 Ok: queued as 4X7B2')->assertSee('abc123@colliers.test')->assertSee('remates@colliers.test')
            ->assertSee('Message-ID')->assertSee('Transporte');

        // Un correo que solo quedó en el archivo de registro no puede figurar como enviado.
        \App\Models\NotificacionLog::create(['canal' => 'correo', 'tipo' => 'RemateNuevoAviso', 'destinatario' => 'dina@correo.test',
            'asunto' => 'Remate nuevo publicado', 'estado' => 'registrada', 'transporte' => 'log']);
        \App\Models\NotificacionLog::create(['canal' => 'correo', 'tipo' => 'CuentaRevisadaAviso', 'destinatario' => 'vieja@correo.test',
            'asunto' => 'Correo anterior a la corrección', 'estado' => 'sin_verificar']);
        $this->actingAs($this->admin)->get($registro)->assertOk()
            ->assertSee('NO SALIÓ')->assertSee('No salió: quedó en el archivo de registro')
            ->assertSee('SIN VERIFICAR')->assertSee('anteriores al');

        $this->actingAs($this->admin)->get($registro . '?estado=registrada')->assertOk()
            ->assertSee('dina@correo.test')->assertDontSee('ana@correo.test');
        $this->actingAs($this->admin)->get($registro . '?estado=fallida')->assertOk()
            ->assertSee('beto@correo.test')->assertDontSee('ana@correo.test');
        $this->actingAs($this->admin)->get($registro . '?q=carla')->assertOk()
            ->assertSee('carla@correo.test')->assertDontSee('beto@correo.test');
        $this->actingAs($this->admin)->get($registro . '?tipo=AdjudicacionAviso')->assertOk()
            ->assertSee('beto@correo.test')->assertDontSee('carla@correo.test');
        $this->actingAs($this->admin)->get($registro . '?desde=' . now(\App\Support\Formato::ZONA)->addDay()->format('Y-m-d'))->assertOk()
            ->assertSee('Sin correos con esos filtros');

        $csv = $this->actingAs($this->admin)->get(route('admin.configuracion.correos.exportar', ['estado' => 'fallida']))->assertOk();
        $contenido = $csv->streamedContent();
        $this->assertStringContainsString('beto@correo.test', $contenido);
        $this->assertStringContainsString('553 Sender address rejected', $contenido);
        $this->assertStringContainsString('Message-ID', $contenido);
        $this->assertStringNotContainsString('ana@correo.test', $contenido);

        $martillero = User::create(['name' => 'M2', 'email' => 'm2@colliers.test', 'password' => 'x-clave-larga-1', 'rol' => User::ROL_MARTILLERO, 'estado' => User::ESTADO_ACTIVO]);
        $this->actingAs($martillero)->get($registro)->assertForbidden();
    }

    public function test_la_bitacora_guarda_lo_que_respondio_el_transporte(): void
    {
        $postor = User::create(['name' => 'Ana', 'email' => 'ana@correo.test', 'password' => 'x-clave-larga-1', 'rol' => User::ROL_POSTOR, 'estado' => User::ESTADO_ACTIVO]);
        $aviso = new \App\Notifications\CuentaRevisadaAviso(
            \App\Models\Postor::create(['user_id' => $postor->id, 'nombres' => 'Ana', 'apellidos' => 'Prueba', 'rut' => '21345678-4']),
            'cuenta_aprobada',
        );

        // Modo registro: el correo no sale a Internet y la bitácora lo dice.
        config(['mail.default' => 'log']);
        $postor->notify($aviso);
        $fila = \App\Models\NotificacionLog::sole();
        $this->assertSame('registrada', $fila->estado, 'con transporte log no se puede afirmar que se envió');
        $this->assertSame('log', $fila->transporte);
        $this->assertNull($fila->enviada_en);
        $this->assertNull($fila->respuesta);

        // Con un servidor SMTP de verdad: queda «aceptada», con su respuesta literal y el Message-ID.
        \App\Models\NotificacionLog::query()->delete();
        [$puerto, $proceso] = $this->servidorFalso();
        Configuracion::guardar('correo_modo', 'smtp');
        Configuracion::guardar('smtp_host', '127.0.0.1');
        Configuracion::guardar('smtp_puerto', (string) $puerto);
        Configuracion::guardar('smtp_cifrado', 'ninguno');
        Configuracion::guardar('smtp_usuario', '');
        Configuracion::guardar('correo_remitente', 'remates@colliers.test');
        \App\Support\CorreoSaliente::aplicar(config());
        $this->app->make(MailManager::class)->forgetMailers();

        $postor->notify($aviso);
        proc_terminate($proceso);

        $fila = \App\Models\NotificacionLog::sole();
        $this->assertSame('aceptada', $fila->estado);
        $this->assertSame('smtp', $fila->transporte);
        $this->assertStringContainsString('250 2.0.0 Ok: queued as PRUEBA123', (string) $fila->respuesta);
        $this->assertNotNull($fila->message_id);
        $this->assertSame('remates@colliers.test', $fila->remitente);
        $this->assertNotNull($fila->enviada_en);
    }

    /** Servidor SMTP mínimo en un proceso aparte: acepta el mensaje y responde 250 con su identificador. */
    private function servidorFalso(): array
    {
        $puerto = random_int(20000, 60000);
        $guion = <<<'PHP'
            $servidor = stream_socket_server("tcp://127.0.0.1:" . $argv[1], $e, $m);
            $cliente = stream_socket_accept($servidor, 10);
            fwrite($cliente, "220 prueba.colliers ESMTP\r\n");
            while (($linea = fgets($cliente)) !== false) {
                $linea = trim($linea);
                if (str_starts_with($linea, 'EHLO') || str_starts_with($linea, 'HELO')) {
                    fwrite($cliente, "250-prueba.colliers\r\n250 SIZE 10240000\r\n");
                } elseif (str_starts_with($linea, 'DATA')) {
                    fwrite($cliente, "354 End data with <CR><LF>.<CR><LF>\r\n");
                    while (($cuerpo = fgets($cliente)) !== false && trim($cuerpo) !== '.') {
                    }
                    fwrite($cliente, "250 2.0.0 Ok: queued as PRUEBA123\r\n");
                } elseif (str_starts_with($linea, 'QUIT')) {
                    fwrite($cliente, "221 Bye\r\n");
                    break;
                } else {
                    fwrite($cliente, "250 2.1.0 Ok\r\n");
                }
            }
            PHP;
        $archivo = tempnam(sys_get_temp_dir(), 'smtp') . '.php';
        file_put_contents($archivo, "<?php\n" . $guion);
        $proceso = proc_open([PHP_BINARY, $archivo, (string) $puerto], [], $tuberias);
        usleep(400000);

        return [$puerto, $proceso];
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
