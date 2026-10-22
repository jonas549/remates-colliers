<?php

namespace Tests\Feature;

use App\Http\Middleware\HoraRecepcion;
use App\Models\Configuracion;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

/** Camino de la puja sin OPcache (docs/RENDIMIENTO-SIN-OPCACHE.md). */
class RendimientoTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_hora_de_recepcion_es_la_de_llegada_a_php_y_no_la_del_middleware(): void
    {
        $llegada = microtime(true) - 3.25;
        $peticion = Request::create('/x', 'POST', server: ['REQUEST_TIME_FLOAT' => $llegada]);

        (new HoraRecepcion)->handle($peticion, fn () => response('ok'));

        $this->assertEqualsWithDelta($llegada * 1000, HoraRecepcion::de($peticion)->getTimestampMs(), 1.0);
    }

    public function test_una_hora_de_llegada_absurda_se_descarta(): void
    {
        foreach ([microtime(true) + 30, microtime(true) - 600, 'texto'] as $valor) {
            $peticion = Request::create('/x', 'POST', server: ['REQUEST_TIME_FLOAT' => $valor]);
            $antes = CarbonImmutable::now('UTC')->getTimestampMs();

            $medida = HoraRecepcion::de($peticion)->getTimestampMs();

            $this->assertGreaterThanOrEqual($antes, $medida, 'valor ' . var_export($valor, true));
            $this->assertLessThanOrEqual(CarbonImmutable::now('UTC')->getTimestampMs(), $medida);
        }
    }

    /** 22/10: la medición por tramos de la puja va apagada; encendida, arma la cabecera Server-Timing. */
    public function test_cronometro_de_la_puja_apagado_por_defecto_y_encendido_arma_server_timing(): void
    {
        \App\Subastas\CronometroPuja::iniciar('transaccion');
        \App\Subastas\CronometroPuja::terminar('transaccion');
        $this->assertNull(\App\Subastas\CronometroPuja::cabecera());

        config(['colliers.medir_puja' => true]);
        \App\Subastas\CronometroPuja::iniciar('transaccion');
        usleep(2000);
        \App\Subastas\CronometroPuja::terminar('transaccion');
        \App\Subastas\CronometroPuja::contar('intentos_tx');
        $cabecera = (string) \App\Subastas\CronometroPuja::cabecera();
        $this->assertMatchesRegularExpression('/^transaccion;dur=\d+\.\d, intentos_tx;dur=1\.0, total;dur=[\d.]+, archivos;dur=\d+(\.\d)?, kb_php;dur=[\d.]+$/', $cabecera);
        $this->assertGreaterThanOrEqual(2.0, (float) explode('dur=', explode(',', $cabecera)[0])[1]);
        // Después de armar la cabecera queda limpio para la petición siguiente.
        $this->assertStringNotContainsString('transaccion', (string) \App\Subastas\CronometroPuja::cabecera());
    }

    public function test_hora_php_responde_sin_arrancar_laravel(): void
    {
        $antes = (int) floor(microtime(true) * 1000);
        $salida = shell_exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(public_path('hora.php')));
        $despues = (int) floor(microtime(true) * 1000);

        $json = json_decode((string) $salida, true);
        $this->assertIsInt($json['servidor_ms'] ?? null, (string) $salida);
        $this->assertGreaterThanOrEqual($antes, $json['servidor_ms']);
        $this->assertLessThanOrEqual($despues, $json['servidor_ms']);
    }

    public function test_configuracion_recordada_se_renueva_al_guardar_desde_el_panel(): void
    {
        Configuracion::sembrarDefectos();
        $this->assertSame(100000, Configuracion::valor('incremento_minimo'));

        Configuracion::where('clave', 'incremento_minimo')->first()->update(['valor' => '300000']);

        $this->assertSame(300000, Configuracion::valor('incremento_minimo'));
    }
}
