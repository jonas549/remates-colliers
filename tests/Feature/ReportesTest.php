<?php

namespace Tests\Feature;

use App\Models\Configuracion;
use App\Models\Garantia;
use App\Models\Lote;
use App\Models\Postor;
use App\Models\Remate;
use App\Models\User;
use App\Reportes\ExportacionesReporte;
use App\Reportes\ReporteRemates;
use App\Subastas\Liquidador;
use App\Subastas\MotorPujas;
use App\Support\Rut;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/** Bloque O · reportes post-evento sobre remates reales, generados con el motor de pujas y el liquidador. */
class ReportesTest extends TestCase
{
    use RefreshDatabase;

    private string $carpeta;

    private User $admin;

    /** @var array<string, User> */
    private array $postores = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->carpeta = storage_path('framework/testing/reportes-' . getmypid());
        config(['colliers.tiempo_real.carpeta' => $this->carpeta]);
        $this->app->forgetInstance(\App\Subastas\Difusion\Emisor::class);
        Configuracion::sembrarDefectos();
        $this->admin = User::create(['name' => 'Admin', 'email' => 'admin@colliers.test', 'password' => 'x-clave-larga-1', 'rol' => User::ROL_ADMIN, 'estado' => User::ESTADO_ACTIVO]);
        foreach (['ana', 'beto', 'carla', 'dani'] as $nombre) {
            $this->postores[$nombre] = $this->postor($nombre);
        }

        // Agosto 2026 (hora de Chile): un departamento adjudicado por tiempo, una casa desierta, un depto. con cierre anticipado.
        $this->remate('R-2026-201', 'Departamento', 100_000_000, '2026-08-10 12:00', [['ana', 100_000_000, 0], ['beto', 110_000_000, 10], ['ana', 120_000_000, 25]], ['ana', 'beto', 'carla']);
        $this->remate('R-2026-202', 'Casa', 200_000_000, '2026-08-15 12:00', [], ['dani'], rechazadas: ['carla']);
        $this->remate('R-2026-203', 'Departamento', 50_000_000, '2026-08-20 12:00', [['carla', 50_000_000, 0], ['dani', 55_000_000, 3]], ['carla', 'dani'], anticipado: true);
        // Julio: otro período. Demostración y cancelado en agosto: no cuentan nunca.
        $this->remate('R-2026-199', 'Casa', 80_000_000, '2026-07-05 12:00', [['beto', 80_000_000, 0]], ['beto']);
        $this->remate('R-DEMO-1', 'Casa', 90_000_000, '2026-08-12 12:00', [['ana', 90_000_000, 0]], ['ana'], demo: true);
        Remate::create(['folio' => 'R-2026-204', 'slug' => 'cancelado', 'titulo' => 'Cancelado', 'estado' => Remate::ESTADO_CANCELADO]);

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-20 15:00:00', 'UTC'));
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->carpeta);
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_desempeno_participacion_dinamica_y_categorias_del_mes(): void
    {
        $r = new ReporteRemates('2026-08');
        $filas = collect($r->desempeno())->keyBy('folio');

        $this->assertSame(['R-2026-203', 'R-2026-202', 'R-2026-201'], $filas->keys()->all(), 'sin julio, demostración ni cancelados; el más reciente primero');
        $this->assertSame(['Adjudicado', 120_000_000, 3, 2], [$filas['R-2026-201']['resultado'], $filas['R-2026-201']['final'], $filas['R-2026-201']['pujas'], $filas['R-2026-201']['postores']]);
        $this->assertEqualsWithDelta(0.20, $filas['R-2026-201']['sobreprecio'], 1e-9);
        $this->assertEqualsWithDelta(25.0, $filas['R-2026-201']['minutos'], 1e-6, 'primera a última puja');
        $this->assertSame(['No adjudicado', null, null], [$filas['R-2026-202']['resultado'], $filas['R-2026-202']['final'], $filas['R-2026-202']['minutos']]);
        $this->assertSame('Cierre anticipado', $filas['R-2026-203']['resultado']);

        $t = $r->totales();
        $this->assertSame([175_000_000, 3, 2, 5], [$t['volumen'], $t['lotes'], $t['vendidos'], $t['pujas']]);
        $this->assertEqualsWithDelta(2 / 3, $t['tasa'], 1e-9);
        $this->assertEqualsWithDelta((0.20 + 0.10) / 2, $t['sobreprecio'], 1e-9);
        $this->assertSame(4, $t['registrados']);
        $this->assertSame(4, $t['con_garantia'], 'ana, beto, carla y dani con garantía aprobada en algún remate del mes');
        $this->assertSame(4, $t['activos']);
        $this->assertSame(2, $t['adjudicatarios']);
        $this->assertSame(1, $t['garantias_rechazadas']);

        $this->assertSame(['R-201', 'R-203'], array_column($r->dinamica(), 'etiqueta'));
        $categorias = collect($r->categorias())->keyBy('categoria');
        $this->assertSame([0, 1], [$categorias['Casa']['vendidos'], $categorias['Casa']['lotes']]);
        $this->assertSame(175_000_000, $categorias['Departamento']['volumen']);
        $this->assertTrue($categorias['Total período']['total']);

        $this->assertSame(['R-2026-199'], array_column((new ReporteRemates('2026-07'))->desempeno(), 'folio'));
        $this->assertCount(4, (new ReporteRemates('2026-T3'))->desempeno());
        $this->assertCount(4, (new ReporteRemates('todo'))->desempeno());
        $this->assertCount(0, (new ReporteRemates('2026-09'))->desempeno());
    }

    public function test_periodos_en_hora_de_chile_y_por_defecto_el_ultimo_mes_con_cierres(): void
    {
        [$inicio, $fin, $etiqueta] = ReporteRemates::rango('2026-08');
        $this->assertSame(['2026-08-01 04:00:00', '2026-09-01 04:00:00', 'agosto 2026'], [$inicio->format('Y-m-d H:i:s'), $fin->format('Y-m-d H:i:s'), $etiqueta]);
        $this->assertSame('julio a septiembre 2026', ReporteRemates::rango('2026-T3')[2]);
        $this->assertSame('2026-08', ReporteRemates::periodoPorDefecto());

        $opciones = ReporteRemates::opciones();
        $this->assertSame(['2026-09', '2026-08', '2026-07'], array_keys($opciones['Meses']), 'meses con cierres y el mes en curso');
        $this->assertArrayHasKey('todo', $opciones['Años']);
    }

    public function test_pantalla_con_datos_reales_para_administracion_y_martillero(): void
    {
        $this->actingAs($this->admin)->get('/admin/reportes')->assertOk()
            ->assertSee('Remates realizados · agosto 2026')->assertSee('$175M')->assertSee('67%')->assertSee('+15%')
            ->assertSee('R-2026-201')->assertSee('$120.000.000')->assertSee('+20%')->assertSee('25 min')
            ->assertSee('Cierre anticipado')->assertSee('No adjudicado')->assertDontSee('R-DEMO-1')->assertDontSee('Datos de ejemplo')
            ->assertDontSee('ana@correo.test');

        $this->actingAs($this->admin)->get('/admin/reportes?periodo=2026-07')->assertOk()->assertSee('R-2026-199')->assertDontSee('R-2026-201');
        $this->actingAs($this->admin)->get('/admin/reportes?periodo=2026-13')->assertNotFound();
        $this->actingAs($this->admin)->get('/admin/reportes?periodo=2026-09')->assertOk()->assertSee('Sin remates cerrados en septiembre 2026');

        $martillero = User::create(['name' => 'M', 'email' => 'm@colliers.test', 'password' => 'x-clave-larga-1', 'rol' => User::ROL_MARTILLERO, 'estado' => User::ESTADO_ACTIVO]);
        $this->actingAs($martillero)->get('/admin/reportes')->assertOk();
        $this->actingAs($this->postores['ana'])->get('/admin/reportes')->assertForbidden();
    }

    public function test_exportables_xlsx_y_csv_sin_identidades(): void
    {
        $libro = $this->actingAs($this->admin)->get('/admin/reportes/exportar/libro.xlsx?periodo=2026-08')->assertOk()->assertDownload('reporte-remates-2026-08.xlsx');
        $hojas = \PhpOffice\PhpSpreadsheet\IOFactory::load($libro->baseResponse->getFile()->getPathname());
        $this->assertSame(['Resumen', 'Desempeño comercial', 'Por categoría', 'Participación', 'Pujas', 'Garantías'], $hojas->getSheetNames());
        $desempeno = $hojas->getSheetByName('Desempeño comercial')->toArray();
        $this->assertSame('Folio', $desempeno[0][0]);
        $this->assertCount(4, $desempeno, 'encabezado + 3 lotes');
        $pujas = $hojas->getSheetByName('Pujas')->toArray();
        $this->assertCount(6, $pujas);
        $this->assertContains('Postor #1', array_column($pujas, 5));
        $this->assertStringNotContainsString('ana@', json_encode($hojas->getSheetByName('Pujas')->toArray()));

        $csv = $this->actingAs($this->admin)->get('/admin/reportes/exportar/desempeno.csv?periodo=2026-08')->assertOk()->assertDownload('reporte-desempeno-2026-08.csv');
        $contenido = file_get_contents($csv->baseResponse->getFile()->getPathname());
        $this->assertStringStartsWith("\xEF\xBB\xBF", $contenido, 'BOM para Excel');
        $this->assertStringContainsString('"R-2026-201";', $contenido);
        $this->assertStringContainsString('"Cierre anticipado"', $contenido);

        foreach (array_keys(ExportacionesReporte::HOJAS) as $hoja) {
            $this->actingAs($this->admin)->get("/admin/reportes/exportar/{$hoja}.xlsx?periodo=2026-08")->assertOk();
        }
        $this->actingAs($this->admin)->get('/admin/reportes/exportar/libro.csv')->assertNotFound();
        $this->actingAs($this->admin)->get('/admin/reportes/exportar/usuarios.xlsx')->assertNotFound();
    }

    public function test_el_paquete_de_excel_no_se_carga_en_las_demas_peticiones(): void
    {
        $this->actingAs($this->admin)->get('/admin/reportes')->assertOk();
        $this->assertFalse($this->app->providerIsLoaded(\Maatwebsite\Excel\ExcelServiceProvider::class));
    }

    private function postor(string $nombre): User
    {
        static $n = 0;
        $n++;
        $user = User::create(['name' => ucfirst($nombre), 'email' => "{$nombre}@correo.test", 'password' => 'x-clave-larga-1', 'rol' => User::ROL_POSTOR, 'estado' => User::ESTADO_ACTIVO]);
        $cuerpo = (string) (20000000 + $n);
        Postor::create(['user_id' => $user->id, 'nombres' => ucfirst($nombre), 'apellidos' => 'Prueba', 'rut' => $cuerpo . Rut::digitoVerificador($cuerpo)])
            ->forceFill(['estado' => Postor::ESTADO_APROBADO, 'revisado_en' => '2026-06-01 12:00:00'])->save();

        return $user;
    }

    /** @param list<array{0: string, 1: int, 2: int}> $pujas [postor, monto, minuto desde la apertura] */
    private function remate(string $folio, string $tipo, int $base, string $inicioChile, array $pujas, array $inscritos, array $rechazadas = [], bool $anticipado = false, bool $demo = false): void
    {
        $abre = CarbonImmutable::parse($inicioChile, 'America/Santiago')->utc();
        $remate = Remate::create(['folio' => $folio, 'slug' => strtolower($folio), 'titulo' => "Remate {$folio}", 'estado' => Remate::ESTADO_PUBLICADO]);
        $remate->forceFill(['es_demostracion' => $demo])->save();
        $lote = Lote::create(['remate_id' => $remate->id, 'titulo' => "Propiedad {$folio}", 'direccion' => "Calle {$folio}", 'tipo_propiedad' => $tipo,
            'precio_base' => $base, 'abre_en' => $abre, 'cierra_en' => $abre->addMinutes(30)]);
        foreach ($inscritos as $nombre) {
            Garantia::paraRemate($remate, $this->postores[$nombre])->forceFill(['estado' => Garantia::ESTADO_APROBADA])->save();
        }
        foreach ($rechazadas as $nombre) {
            Garantia::paraRemate($remate, $this->postores[$nombre])->forceFill(['estado' => Garantia::ESTADO_RECHAZADA])->save();
        }
        foreach ($pujas as [$nombre, $monto, $minuto]) {
            CarbonImmutable::setTestNow($abre->addMinutes($minuto));
            app(MotorPujas::class)->pujar($this->postores[$nombre], $lote->id, $monto, $abre->addMinutes($minuto));
        }
        $liquidador = app(Liquidador::class);
        if ($anticipado) {
            $cierre = $abre->addMinutes(5);
            CarbonImmutable::setTestNow($cierre);
            $liquidador->cerrarAnticipadamente($lote->fresh(), $this->admin, $cierre);
        }
        $vence = $lote->fresh()->cierra_en->addSeconds(10);
        CarbonImmutable::setTestNow($vence);
        $liquidador->liquidarPorId($lote->id, $vence);
    }
}
