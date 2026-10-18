<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\Garantia;
use App\Models\Lote;
use App\Models\Postor;
use App\Models\Remate;
use App\Models\User;
use App\Subastas\Difusion\Emisor;
use App\Subastas\Presencia;
use App\Subastas\PresenciaSala;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Presencia en la sala (QA de Jonas, 18/10): el postor ve cuántos están conectados; el martillero y la administración,
 * además, quiénes (nombre y apellido, o la empresa). El latido va a public/presencia.php sin arrancar Laravel, y el JSON
 * público lleva solo el número.
 */
class PresenciaSalaTest extends TestCase
{
    use RefreshDatabase;

    private string $carpeta;

    private Remate $remate;

    private User $martillero;

    protected function setUp(): void
    {
        parent::setUp();
        // Hora real: el latido del script usa el reloj del sistema y el token vence según la hora de Laravel.
        $this->carpeta = storage_path('framework/testing/presencia-json-' . getmypid());
        config(['colliers.tiempo_real.carpeta' => $this->carpeta]);
        $this->app->forgetInstance(Emisor::class);

        $this->martillero = User::create(['name' => 'M. Ossandón', 'email' => 'm@colliers.test', 'password' => 'x-clave-larga-1', 'rol' => User::ROL_MARTILLERO, 'estado' => User::ESTADO_ACTIVO]);
        $this->remate = Remate::create(['folio' => 'R-2026-200', 'slug' => 'presencia', 'titulo' => 'Presencia', 'estado' => Remate::ESTADO_PUBLICADO, 'martillero_id' => $this->martillero->id]);
        Lote::create([
            'remate_id' => $this->remate->id, 'titulo' => 'Lote', 'direccion' => 'Av. Apoquindo 4501', 'precio_base' => 185000000,
            'abre_en' => CarbonImmutable::now('UTC')->subMinutes(5), 'cierra_en' => CarbonImmutable::now('UTC')->addMinutes(30),
        ]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->carpeta);
        parent::tearDown();
    }

    public function test_el_token_solo_sirve_firmado_vigente_y_con_un_slug_valido(): void
    {
        $datos = ['r' => 7, 's' => 'apoquindo', 'u' => 3, 'e' => 2_000];
        $token = PresenciaSala::firmar($datos, 'clave');

        $this->assertSame($datos, PresenciaSala::verificar($token, 'clave', 1_000));
        $this->assertNull(PresenciaSala::verificar($token, 'otra-clave', 1_000), 'firmado con otra clave');
        $this->assertNull(PresenciaSala::verificar($token, 'clave', 2_001), 'vencido');
        $this->assertNull(PresenciaSala::verificar($token, '', 1_000));
        $this->assertNull(PresenciaSala::verificar('basura', 'clave', 1_000));

        // Cambiar el usuario del token sin la clave no sirve.
        [$cuerpo, $firma] = explode('.', $token);
        $ajeno = rtrim(strtr(base64_encode(json_encode(['r' => 7, 's' => 'apoquindo', 'u' => 99, 'e' => 2_000])), '+/', '-_'), '=');
        $this->assertNull(PresenciaSala::verificar($ajeno . '.' . $firma, 'clave', 1_000));

        // El slug arma rutas de archivo: aunque esté firmado, solo letras, números y guiones.
        $this->assertNull(PresenciaSala::verificar(PresenciaSala::firmar(['r' => 7, 's' => '../x', 'u' => 3, 'e' => 2_000], 'clave'), 'clave', 1_000));
    }

    public function test_cuenta_los_latidos_de_los_ultimos_45_segundos_y_la_salida_baja_al_instante(): void
    {
        $sala = new PresenciaSala($this->carpetaPresencia, $this->carpeta, 45_000);
        $this->assertSame(0, $sala->conectados(1, 100_000));
        $this->assertDirectoryDoesNotExist($this->carpetaPresencia, 'leer no crea archivos');

        $this->assertSame(1, $sala->latido(1, 'presencia', 10, 100_000));
        $this->assertSame(2, $sala->latido(1, 'presencia', 11, 110_000));
        $this->assertSame(2, $sala->latido(1, 'presencia', 10, 115_000), 'el mismo postor late de nuevo: no se duplica');
        $this->assertSame(1, $sala->latido(2, 'otro', 10, 115_000), 'cada remate cuenta por separado');

        $this->assertSame([10 => 115_000, 11 => 110_000], $sala->activos(1, 150_000));
        $this->assertSame(1, $sala->conectados(1, 155_000), 'el 11 latió hace 45 s: vencido');
        $this->assertSame(0, $sala->salir(1, 'presencia', 10, 156_000));
    }

    public function test_el_json_publico_lleva_solo_el_numero_y_el_emisor_no_lo_pisa(): void
    {
        $ana = $this->postor('Ana', 'Rojas');
        $emisor = app(Emisor::class);
        $emisor->publicarEstado($this->remate);
        $this->assertSame(['conectados' => 0], $this->estadoPublico()['presencia']);

        $ahora = (int) CarbonImmutable::now('UTC')->format('Uv');
        Presencia::sala()->latido($this->remate->id, 'presencia', $ana->id, $ahora);
        $this->assertSame(['conectados' => 1], $this->estadoPublico()['presencia']);

        // Una puja (o cualquier evento) reescribe el JSON desde la base: el número sigue ahí.
        $emisor->publicarEstado($this->remate);
        $this->assertSame(['conectados' => 1], $this->estadoPublico()['presencia']);

        $crudo = File::get($this->carpeta . '/presencia.json');
        $this->assertStringNotContainsString('Ana', $crudo);
        $this->assertStringNotContainsString('Rojas', $crudo);
        $this->assertStringNotContainsString('"' . $ana->id . '"', $crudo);
    }

    public function test_el_cron_limpia_a_los_que_se_fueron_sin_avisar(): void
    {
        $ana = $this->postor('Ana', 'Rojas');
        app(Emisor::class)->publicarEstado($this->remate);
        Presencia::sala()->latido($this->remate->id, 'presencia', $ana->id, (int) CarbonImmutable::now('UTC')->subSeconds(50)->format('Uv'));
        $this->assertSame(1, $this->estadoPublico()['presencia']['conectados']);

        Artisan::call('colliers:liquidar');

        $this->assertSame(0, $this->estadoPublico()['presencia']['conectados']);
        $this->assertFileDoesNotExist($this->carpetaPresencia . '/' . $this->remate->id . '.json', 'sin nadie, no queda archivo');
    }

    public function test_presencia_php_late_sin_arrancar_laravel_y_rechaza_tokens_ajenos(): void
    {
        $ana = $this->postor('Ana', 'Rojas');
        app(Emisor::class)->publicarEstado($this->remate);
        $token = Presencia::token($this->remate, $ana);

        $salida = $this->presenciaPhp(['token' => $token, 'accion' => 'latido']);
        [$cuerpo, $archivos] = explode('|', $salida);
        $this->assertSame('{"conectados":1}', $cuerpo);
        $this->assertLessThanOrEqual(3, (int) $archivos, 'solo el script y PresenciaSala: ni Composer ni Laravel');
        $this->assertSame(1, $this->estadoPublico()['presencia']['conectados']);

        $this->assertStringStartsWith('{"error":"token"}', $this->presenciaPhp(['token' => $token . 'x', 'accion' => 'latido']));
        $this->assertStringStartsWith('{"conectados":0}', $this->presenciaPhp(['token' => $token, 'accion' => 'salir']));
        $this->assertStringStartsWith('{"error":"metodo"}', $this->presenciaPhp(['token' => $token], 'GET'));
    }

    public function test_la_sala_entrega_el_token_al_postor_y_ningun_nombre(): void
    {
        $this->postor('Rival', 'Secreto');
        $ana = $this->postor('Ana', 'Rojas');

        $html = $this->actingAs($ana)->get('/remates/presencia/sala')->assertOk()->getContent();
        preg_match('/x-data="salaPuja\(JSON\.parse\(\'(.*?)\'\)\)"/s', html_entity_decode($html, ENT_QUOTES), $m);
        $config = json_decode(json_decode('"' . $m[1] . '"'), true);

        $this->assertStringEndsWith('/presencia.php', $config['urls']['presencia']);
        $this->assertSame(15000, $config['presencia']['latidoMs']);
        $datos = PresenciaSala::verificar($config['presencia']['token'], json_decode(File::get(config('colliers.presencia.ajustes')), true)['clave'],
            (int) CarbonImmutable::now('UTC')->format('Uv'));
        $this->assertSame([$this->remate->id, 'presencia', $ana->id], [$datos['r'], $datos['s'], $datos['u']]);
        $this->assertSame(['conectados' => 0], $config['estado']['presencia']);
        $this->assertStringNotContainsString('Secreto', $html);
    }

    public function test_el_panel_ve_quien_esta_con_nombre_o_empresa_y_nada_mas(): void
    {
        $ana = $this->postor('Ana María', 'Rojas Muñoz');
        $empresa = $this->postor('Pedro', 'Pérez', Empresa::create(['rut' => '76543210-3', 'razon_social' => 'Inversiones Andes SpA']));
        $ausente = $this->postor('Luis', 'Ausente');
        $ahora = (int) CarbonImmutable::now('UTC')->format('Uv');
        Presencia::sala()->latido($this->remate->id, 'presencia', $ana->id, $ahora);
        Presencia::sala()->latido($this->remate->id, 'presencia', $empresa->id, $ahora);

        $admin = User::create(['name' => 'Admin', 'email' => 'a@colliers.test', 'password' => 'x-clave-larga-1', 'rol' => User::ROL_ADMIN, 'estado' => User::ESTADO_ACTIVO]);
        $esperado = ['conectados' => 2, 'usuarios' => [['nombre' => 'Ana María Rojas Muñoz'], ['nombre' => 'Inversiones Andes SpA']]];
        $this->actingAs($admin)->getJson("/admin/subastas/{$this->remate->id}/presencia")->assertOk()->assertExactJson($esperado)
            ->assertDontSee('Ausente')->assertDontSee('@correo.test')->assertDontSee('Pérez');
        $this->actingAs($this->martillero)->getJson("/admin/subastas/{$this->remate->id}/presencia")->assertOk()->assertExactJson($esperado);

        // El panel abre con la lista ya cargada.
        $this->actingAs($this->martillero)->get("/admin/subastas/{$this->remate->id}/en-vivo")->assertOk()
            ->assertSee('En la sala ahora')->assertSee('Inversiones Andes SpA');

        // Otro martillero y los postores no la ven.
        $otro = User::create(['name' => 'Otro', 'email' => 'o@colliers.test', 'password' => 'x-clave-larga-1', 'rol' => User::ROL_MARTILLERO, 'estado' => User::ESTADO_ACTIVO]);
        $this->actingAs($otro)->getJson("/admin/subastas/{$this->remate->id}/presencia")->assertForbidden();
        $this->actingAs($ausente)->getJson("/admin/subastas/{$this->remate->id}/presencia")->assertForbidden();
    }

    /** Ejecuta public/presencia.php como lo haría LiteSpeed, en un proceso aparte. Devuelve «cuerpo|archivos cargados». */
    private function presenciaPhp(array $post, string $metodo = 'POST'): string
    {
        $codigo = '$_SERVER["REQUEST_METHOD"] = ' . var_export($metodo, true) . '; $_POST = ' . var_export($post, true) . ';'
            . ' require ' . var_export(public_path('presencia.php'), true) . '; echo "|" . count(get_included_files());';
        $proceso = proc_open([PHP_BINARY, '-r', $codigo], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $tubos, null,
            ['COLLIERS_PRESENCIA_AJUSTES' => config('colliers.presencia.ajustes')] + getenv());
        $salida = stream_get_contents($tubos[1]);
        proc_close($proceso);

        return (string) $salida;
    }

    private function estadoPublico(): array
    {
        return json_decode(File::get($this->carpeta . '/presencia.json'), true);
    }

    private function postor(string $nombres, string $apellidos, ?Empresa $empresa = null): User
    {
        static $n = 0;
        $n++;
        $user = User::create(['name' => "{$nombres} {$apellidos}", 'email' => "p{$n}@correo.test", 'password' => 'x-clave-larga-1', 'rol' => User::ROL_POSTOR]);
        $user->forceFill(['email_verified_at' => now(), 'estado' => User::ESTADO_ACTIVO])->save();
        $cuerpo = (string) (31000000 + $n);
        Postor::create(['user_id' => $user->id, 'nombres' => $nombres, 'apellidos' => $apellidos, 'empresa_id' => $empresa?->id,
            'rut' => $cuerpo . \App\Support\Rut::digitoVerificador($cuerpo)])->forceFill(['estado' => Postor::ESTADO_APROBADO])->save();
        Garantia::paraRemate($this->remate, $user)->forceFill(['estado' => Garantia::ESTADO_APROBADA])->save();

        return $user->fresh();
    }
}
