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
    private function formulario(array $cambios): array
    {
        $config = [];
        foreach (Configuracion::DEFECTOS as $clave => $datos) {
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

        $this->actingAs($this->admin)->get('/admin/configuracion')->assertOk()
            ->assertSee('Pujas y cierre')->assertSee('Correo saliente (SMTP)')->assertSee('MARGEN DE LIQUIDACIÓN (SEGUNDOS)')
            ->assertSee('OPcache (web)')->assertSee('Configuración');
    }

    public function test_guardar_valores_rige_de_inmediato_sin_deploy(): void
    {
        $this->actingAs($this->admin)->put('/admin/configuracion', $this->formulario([
            'incremento_minimo' => '250000', 'pujas_rapidas' => '250.000, 1.000.000; 2000000', 'margen_liquidacion_segundos' => '5',
            'porcentaje_garantia' => '7.5', 'filtro_garantia_visible' => '1', 'contacto_correo' => 'subastas@colliers.test',
            'login_intentos_maximos' => '3', 'uf_fuente' => 'manual', 'uf_valor' => '39500.12',
        ]))->assertSessionHas('estado');

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
        $this->actingAs($this->admin)->put('/admin/configuracion', $this->formulario(['filtro_garantia_visible' => '0']))->assertSessionHas('estado');
        $this->assertFalse(Configuracion::valor('filtro_garantia_visible'));
    }

    public function test_validacion_en_espanol_y_nada_se_guarda_con_errores(): void
    {
        $this->actingAs($this->admin)->put('/admin/configuracion', $this->formulario([
            'porcentaje_garantia' => '150', 'margen_liquidacion_segundos' => '0', 'contacto_correo' => 'no-es-correo', 'uf_fuente' => 'inventada',
            'incremento_minimo' => '500000',
        ]))->assertSessionHasErrors(['config.porcentaje_garantia', 'config.margen_liquidacion_segundos', 'config.contacto_correo', 'config.uf_fuente']);

        $this->assertSame(100000, Configuracion::valor('incremento_minimo'));
        $this->assertStringContainsString('garantía', session('errors')->first('config.porcentaje_garantia'));
        // Al volver con los datos ingresados, la lista de montos (ya separada) se muestra otra vez como texto.
        $this->actingAs($this->admin)->get('/admin/configuracion')->assertOk()->assertSee('100000, 500000, 1000000');
    }

    public function test_clave_smtp_cifrada_se_conserva_si_se_deja_vacia_y_se_aplica_al_enviar(): void
    {
        $this->actingAs($this->admin)->put('/admin/configuracion', $this->formulario([
            'correo_modo' => 'smtp', 'smtp_host' => 'mail.colliers.test', 'smtp_puerto' => '465', 'smtp_cifrado' => 'ssl',
            'smtp_usuario' => 'remates', 'smtp_clave' => 'secreta-123', 'correo_remitente' => 'no-responder@colliers.test',
        ]))->assertSessionHas('estado');

        $guardado = Configuracion::where('clave', 'smtp_clave')->value('valor');
        $this->assertNotSame('secreta-123', $guardado, 'nunca en claro en la base');
        $this->assertSame('secreta-123', Crypt::decryptString($guardado));
        $this->actingAs($this->admin)->get('/admin/configuracion')->assertDontSee('secreta-123')->assertSee('(configurada)');

        $this->actingAs($this->admin)->put('/admin/configuracion', $this->formulario(['correo_modo' => 'smtp', 'smtp_host' => 'mail.colliers.test', 'smtp_clave' => '']));
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
            ->assertSessionHas('estado', fn (string $m) => str_contains($m, 'registrado en storage/logs'));
        $this->actingAs($this->admin)->post('/admin/configuracion/probar-correo', ['destino' => 'no'])->assertSessionHasErrors('destino');
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
