<?php

namespace Tests\Feature;

use App\Models\Configuracion;
use App\Models\User;
use App\Subastas\ConexionPuja;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PDOException;
use Tests\TestCase;

/**
 * Reintento de la conexión en la puja y margen de liquidación seguro (21/09, riesgo del Bloque R).
 * La prueba de punta a punta con una conexión real rechazada está en ConexionPujaMysqlTest (solo MySQL/MariaDB).
 */
class ConexionPujaTest extends TestCase
{
    use RefreshDatabase;

    /** Conexión simulada: cada llamada toma el siguiente resultado del guion (null = abre). */
    private function conexion(array $guion): ConexionPuja
    {
        return new class($guion) extends ConexionPuja
        {
            public array $esperas = [];

            public function __construct(private array $guion) {}

            protected function conectar(): void
            {
                $siguiente = array_shift($this->guion);
                if ($siguiente !== null) {
                    throw new PDOException($siguiente);
                }
            }

            protected function esperar(int $ms): void
            {
                $this->esperas[] = $ms;
            }
        };
    }

    public function test_reintenta_ante_errores_de_conexion_y_se_recupera(): void
    {
        $conexion = $this->conexion(['SQLSTATE[08004] [1040] Too many connections', 'SQLSTATE[HY000] [2002] No such file or directory', null]);

        $this->assertSame(3, $conexion->abrir());
        $this->assertSame([500, 500], $conexion->esperas);
    }

    public function test_se_rinde_al_agotar_1_5_segundos(): void
    {
        $conexion = $this->conexion(array_fill(0, 10, 'SQLSTATE[08004] [1040] Too many connections'));

        try {
            $conexion->abrir();
            $this->fail('Tenía que rendirse');
        } catch (PDOException $e) {
            $this->assertStringContainsString('[1040]', $e->getMessage());
        }
        $this->assertSame([500, 500, 500], $conexion->esperas, 'nunca espera más que el presupuesto');
        $this->assertSame(1500, array_sum($conexion->esperas));
    }

    public function test_un_error_que_no_es_de_conexion_sale_de_inmediato(): void
    {
        foreach (['SQLSTATE[22001]: String data, right truncated: 1406 Data too long', 'SQLSTATE[HY000] [1045] Access denied for user'] as $mensaje) {
            $conexion = $this->conexion([$mensaje, null]);
            try {
                $conexion->abrir();
                $this->fail("No tenía que reintentar: {$mensaje}");
            } catch (PDOException) {
                $this->assertSame([], $conexion->esperas);
            }
        }
    }

    public function test_reconoce_solo_los_codigos_de_conexion(): void
    {
        foreach ([1040 => '08004', 1203 => '42000', 1226 => '42000', 2002 => 'HY000', 2006 => 'HY000'] as $codigo => $estado) {
            $this->assertSame($codigo, ConexionPuja::codigo(new PDOException("SQLSTATE[{$estado}] [{$codigo}] algo")));
        }
        // Envuelto por Laravel, como llega desde una consulta.
        $envuelto = new QueryException('mysql', 'select 1', [], new PDOException('SQLSTATE[08004] [1040] Too many connections'));
        $this->assertSame(1040, ConexionPuja::codigo($envuelto));
        // Un deadlock o un dato demasiado largo NO son de conexión.
        $this->assertNull(ConexionPuja::codigo(new PDOException('SQLSTATE[40001]: Serialization failure: 1213 Deadlock found')));
        $this->assertNull(ConexionPuja::codigo(new PDOException('SQLSTATE[HY000] [1045] Access denied')));
    }

    public function test_el_margen_seguro_sale_del_reintento_mas_el_procesamiento(): void
    {
        $this->assertSame(5, ConexionPuja::margenSeguroSegundos());
        config(['colliers.conexion_puja.procesamiento_ms' => 400]);
        $this->assertSame(2, ConexionPuja::margenSeguroSegundos(), '1,5 s + 0,4 s, hacia arriba');
    }

    public function test_el_reintento_solo_corre_en_la_ruta_de_la_puja(): void
    {
        $llamadas = 0;
        $this->app->bind(ConexionPuja::class, function () use (&$llamadas) {
            return new class($llamadas) extends ConexionPuja
            {
                public function __construct(private int &$llamadas) {}

                public function abrir(): int
                {
                    $this->llamadas++;

                    return 1;
                }
            };
        });

        $this->get('/');
        $this->post('/remates/cualquiera/estado');
        $this->assertSame(0, $llamadas);

        $this->post('/remates/cualquiera/lotes/1/pujas', ['monto' => 1]);
        $this->assertSame(1, $llamadas);
    }

    public function test_el_panel_pide_confirmar_un_margen_bajo_lo_seguro_y_lo_advierte(): void
    {
        Configuracion::sembrarDefectos();
        Configuracion::guardar('margen_liquidacion_segundos', 5);
        $admin = User::create(['name' => 'Admin', 'email' => 'admin@colliers.test', 'password' => 'x-clave-larga-1', 'rol' => User::ROL_ADMIN, 'estado' => User::ESTADO_ACTIVO]);
        $url = route('admin.configuracion.seccion', 'remates');

        $this->actingAs($admin)->get($url)->assertOk()->assertDontSee('data-aviso-margen', false);

        // Bajarlo sin confirmar: no se guarda y dice por qué.
        $this->actingAs($admin)->from($url)->put(route('admin.configuracion.update', 'remates'), $this->formulario(['margen_liquidacion_segundos' => 2]))
            ->assertSessionHasErrors('config.margen_liquidacion_segundos');
        Configuracion::olvidar();
        $this->assertSame(5, (int) Configuracion::valor('margen_liquidacion_segundos'));

        // Confirmado: se guarda, y la pantalla lo advierte mientras siga bajo lo seguro.
        $this->actingAs($admin)->from($url)->put(route('admin.configuracion.update', 'remates'), $this->formulario(['margen_liquidacion_segundos' => 2]) + ['confirmar_margen' => '1'])
            ->assertSessionHasNoErrors();
        Configuracion::olvidar();
        $this->assertSame(2, (int) Configuracion::valor('margen_liquidacion_segundos'));
        $this->actingAs($admin)->get($url)->assertOk()->assertSee('data-aviso-margen', false)
            ->assertSee('una puja recibida a tiempo puede reintentar la conexión');

        // Guardar otra cosa con el margen como estaba no vuelve a pedir la confirmación.
        $this->actingAs($admin)->from($url)->put(route('admin.configuracion.update', 'remates'), $this->formulario(['incremento_minimo' => 200000]))
            ->assertSessionHasNoErrors();
        Configuracion::olvidar();
        $this->assertSame(200000, (int) Configuracion::valor('incremento_minimo'));

        // Subirlo a lo seguro no pide nada y el aviso desaparece.
        $this->actingAs($admin)->from($url)->put(route('admin.configuracion.update', 'remates'), $this->formulario(['margen_liquidacion_segundos' => 5]))
            ->assertSessionHasNoErrors();
        $this->actingAs($admin)->get($url)->assertDontSee('data-aviso-margen', false);
    }

    /** Envío completo de «Remates y pujas», con los valores actuales salvo los cambios. */
    private function formulario(array $cambios): array
    {
        Configuracion::olvidar();
        $config = [];
        foreach (Configuracion::camposDe('remates') as $clave => $datos) {
            if (! empty($datos['solo_lectura'])) {
                continue;
            }
            $valor = Configuracion::valor($clave);
            $config[$clave] = $datos['tipo'] === 'lista_montos' ? implode(', ', (array) $valor) : $valor;
        }

        return ['config' => $cambios + $config];
    }
}
