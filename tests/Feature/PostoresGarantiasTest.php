<?php

namespace Tests\Feature;

use App\Events\ComprobanteRecibido;
use App\Events\CuentaRevisada;
use App\Events\GarantiaRevisada;
use App\Models\AccessLog;
use App\Models\Configuracion;
use App\Models\Garantia;
use App\Models\Lote;
use App\Models\Postor;
use App\Models\Remate;
use App\Models\User;
use App\Support\Rut;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Bloques G y H · revisión de cuentas y garantías por Colliers, inscripción y comprobante del postor. */
class PostoresGarantiasTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Remate $remate;

    protected function setUp(): void
    {
        parent::setUp();
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-20 15:00:00', 'UTC'));
        Storage::fake('local');
        Configuracion::sembrarDefectos();
        config(['colliers.tiempo_real.carpeta' => storage_path('framework/testing/postores-' . getmypid())]);
        $this->admin = User::create(['name' => 'Admin', 'email' => 'admin@colliers.test', 'password' => 'x-clave-larga-1', 'rol' => User::ROL_ADMIN, 'estado' => User::ESTADO_ACTIVO]);

        $this->remate = Remate::create([
            'folio' => 'R-2026-118', 'slug' => 'militares', 'titulo' => 'Los Militares 5620, Depto. 703', 'estado' => Remate::ESTADO_PUBLICADO,
            'inicio_en' => CarbonImmutable::now('UTC')->addDays(5), 'cierre_garantias_en' => CarbonImmutable::now('UTC')->addDays(3),
        ]);
        Lote::create(['remate_id' => $this->remate->id, 'titulo' => 'Los Militares', 'direccion' => 'Los Militares 5620, Depto. 703', 'comuna' => 'Las Condes', 'precio_base' => 142000000]);
        $this->remate->programarLotes();
    }

    private function postor(string $estado = Postor::ESTADO_EN_REVISION, string $nombre = 'Camila'): User
    {
        static $n = 0;
        $n++;
        $user = User::create(['name' => $nombre, 'email' => "p{$n}@correo.test", 'password' => 'x-clave-larga-1', 'rol' => User::ROL_POSTOR, 'estado' => User::ESTADO_ACTIVO]);
        $user->forceFill(['email_verified_at' => now()])->save();
        $cuerpo = (string) (50000000 + $n);
        Postor::create(['user_id' => $user->id, 'nombres' => $nombre, 'apellidos' => 'Ortiz Vera', 'rut' => $cuerpo . Rut::digitoVerificador($cuerpo), 'telefono' => '+56 9 1111 2222'])
            ->forceFill(['estado' => $estado])->save();

        return $user->fresh();
    }

    public function test_listado_real_solo_para_administradores(): void
    {
        $this->postor(nombre: 'Camila');
        $martillero = User::create(['name' => 'M', 'email' => 'm@colliers.test', 'password' => 'x-clave-larga-1', 'rol' => User::ROL_MARTILLERO, 'estado' => User::ESTADO_ACTIVO]);

        $this->actingAs($martillero)->get('/admin/postores')->assertForbidden();
        $this->actingAs($this->admin)->get('/admin/postores')->assertOk()
            ->assertViewHas('postores', fn (array $filas) => $filas[0]['nombre'] === 'Camila Ortiz Vera' && $filas[0]['cuenta'] === 'En revisión'
                && $filas[0]['garantia'] === 'Sin inscripción');

        // Una fila de inscripción trae las acciones de la cuenta Y de la garantía.
        $inscrita = $this->postor(Postor::ESTADO_APROBADO, 'Inscrita');
        $garantia = Garantia::paraRemate($this->remate, $inscrita);
        $this->actingAs($this->admin)->get('/admin/postores')->assertViewHas('postores', function (array $filas) use ($garantia) {
            $fila = collect($filas)->firstWhere('garantiaId', $garantia->id);

            return $fila['urls']['aprobarGarantia'] === route('admin.garantias.aprobar', $garantia) && isset($fila['urls']['bloquear']);
        });
    }

    public function test_aprobar_y_rechazar_cuentas_con_motivo_bitacora_y_evento(): void
    {
        Event::fake([CuentaRevisada::class]);
        $camila = $this->postor();
        $rodrigo = $this->postor(nombre: 'Rodrigo');
        $sinCorreo = $this->postor(Postor::ESTADO_REGISTRADO, 'Jorge');

        $this->actingAs($this->admin)->postJson(route('admin.postores.aprobar', $camila->postor))->assertOk()
            ->assertJsonPath('ok', true)->assertJsonCount(3, 'postores');
        $this->assertSame(Postor::ESTADO_APROBADO, $camila->postor->fresh()->estado);
        $this->assertSame($this->admin->id, $camila->postor->fresh()->revisado_por_id);
        $this->assertTrue(AccessLog::where('user_id', $camila->id)->where('evento', 'cuenta_aprobada')->exists());

        $this->actingAs($this->admin)->postJson(route('admin.postores.aprobar', $sinCorreo->postor))->assertStatus(422)
            ->assertJsonPath('mensaje', 'El postor todavía no confirma su correo.');
        $this->actingAs($this->admin)->postJson(route('admin.postores.rechazar', $rodrigo->postor), ['motivo' => ''])->assertStatus(422)->assertJsonValidationErrors('motivo');
        $this->actingAs($this->admin)->postJson(route('admin.postores.rechazar', $rodrigo->postor), ['motivo' => 'Faltan documentos'])->assertOk();
        $this->assertSame('Faltan documentos', $rodrigo->postor->fresh()->motivo_rechazo);

        Event::assertDispatchedTimes(CuentaRevisada::class, 2);
    }

    public function test_cuenta_bloqueada_no_se_inscribe_y_se_desbloquea(): void
    {
        $user = $this->postor(Postor::ESTADO_APROBADO);
        $this->actingAs($this->admin)->postJson(route('admin.postores.bloquear', $user->postor), ['motivo' => 'Revisión legal'])->assertOk();
        $this->assertSame(Postor::ESTADO_BLOQUEADO, $user->postor->fresh()->estado);
        $user = $user->fresh(); // en una petición real la cuenta se lee de nuevo

        $this->actingAs($user)->post(route('cuenta.inscribirme', $this->remate->slug))->assertSessionHas('error');
        $this->actingAs($user)->get('/mi-cuenta')->assertOk()->assertSee('Tu cuenta está bloqueada');

        $this->actingAs($this->admin)->postJson(route('admin.postores.desbloquear', $user->postor))->assertOk();
        $this->assertSame(Postor::ESTADO_APROBADO, $user->postor->fresh()->estado);
    }

    public function test_inscripcion_comprobante_revision_y_reenvio(): void
    {
        Event::fake([GarantiaRevisada::class, ComprobanteRecibido::class]);
        $user = $this->postor(Postor::ESTADO_APROBADO);

        // Sin inscripción: la cuenta ofrece los remates abiertos.
        $this->actingAs($user)->get('/mi-cuenta')->assertOk()->assertSee('Tu cuenta está aprobada')->assertSee('Inscribirme')->assertSee('R-2026-118');

        $this->actingAs($user)->post(route('cuenta.inscribirme', $this->remate->slug))
            ->assertRedirect(route('cuenta.estado', ['remate' => 'militares']))->assertSessionHas('estado');
        $garantia = Garantia::sole();
        $this->assertSame(Garantia::ESTADO_PENDIENTE, $garantia->estado);
        $this->assertSame(14200000, $garantia->monto, '10 % del precio base');
        $this->actingAs($user)->post(route('cuenta.inscribirme', $this->remate->slug));
        $this->assertSame(1, Garantia::count(), 'inscribirse dos veces no duplica');

        // Sin datos bancarios configurados, la pantalla pide escribir.
        $this->actingAs($user)->get('/mi-cuenta?remate=militares')->assertOk()->assertSee('$14.200.000')->assertSee('para recibir los datos de la cuenta')->assertSee('Subir comprobante');
        Configuracion::guardar('banco_nombre', 'Banco de Chile');
        Configuracion::guardar('banco_numero_cuenta', '000-12345-67');
        Configuracion::guardar('banco_titular', 'Colliers International Chile S.A.');
        $this->actingAs($user)->get('/mi-cuenta?remate=militares')->assertSee('N.º 000-12345-67')->assertSee('Colliers International Chile S.A.');

        $this->actingAs($user)->post(route('cuenta.comprobante.store', $garantia), ['medio' => 'transferencia', 'comprobante' => UploadedFile::fake()->create('transferencia.pdf', 200, 'application/pdf')])
            ->assertSessionHas('estado');
        $garantia->refresh();
        $this->assertSame(Garantia::ESTADO_EN_REVISION, $garantia->estado);
        Storage::disk('local')->assertExists($garantia->comprobante_ruta);
        Event::assertDispatched(ComprobanteRecibido::class);

        // Otro postor no toca ni ve esta garantía.
        $otro = $this->postor(Postor::ESTADO_APROBADO, 'Otro');
        $this->actingAs($otro)->post(route('cuenta.comprobante.store', $garantia), ['medio' => 'transferencia', 'comprobante' => UploadedFile::fake()->create('x.pdf', 10)])->assertNotFound();
        $this->actingAs($otro)->get(route('cuenta.comprobante', $garantia))->assertNotFound();
        $this->actingAs($user)->get(route('cuenta.comprobante', $garantia))->assertOk()->assertDownload('transferencia.pdf');
        $this->actingAs($this->admin)->get(route('admin.garantias.comprobante', $garantia))->assertOk();

        // Colliers rechaza con motivo; el postor corrige y reenvía; Colliers aprueba.
        $this->actingAs($this->admin)->postJson(route('admin.garantias.rechazar', $garantia), ['motivo' => 'El monto no coincide'])->assertOk();
        $this->assertSame(Garantia::ESTADO_RECHAZADA, $garantia->fresh()->estado);
        $this->actingAs($user)->get('/mi-cuenta?remate=militares')->assertSee('El monto no coincide')->assertSee('Subir comprobante');
        $this->actingAs($user)->post(route('cuenta.comprobante.store', $garantia), ['medio' => 'vale_vista', 'comprobante' => UploadedFile::fake()->image('vale.jpg')])->assertSessionHas('estado');
        $this->assertSame(Garantia::ESTADO_EN_REVISION, $garantia->fresh()->estado);

        $this->actingAs($this->admin)->postJson(route('admin.garantias.aprobar', $garantia))->assertOk()->assertJsonPath('ok', true);
        $garantia->refresh();
        $this->assertSame(Garantia::ESTADO_APROBADA, $garantia->estado);
        $this->assertSame($this->admin->id, $garantia->revisado_por_id);
        $this->assertNull($garantia->motivo_rechazo);
        Event::assertDispatchedTimes(GarantiaRevisada::class, 2);

        $this->actingAs($user)->get('/mi-cuenta?remate=militares')->assertSee('Estás habilitado para pujar')->assertSee('Entrar a la sala de pujas');
        $this->actingAs($user)->post(route('cuenta.comprobante.store', $garantia), ['medio' => 'vale_vista', 'comprobante' => UploadedFile::fake()->image('otra.jpg')])
            ->assertSessionHas('error', 'Tu garantía ya está aprobada.');
    }

    public function test_reglas_de_plazo_y_de_cuenta_para_garantias(): void
    {
        $enRevision = $this->postor();
        $this->actingAs($enRevision)->post(route('cuenta.inscribirme', $this->remate->slug))->assertSessionHas('error');
        $this->assertSame(0, Garantia::count());

        // Una garantía no se aprueba si la cuenta no está aprobada.
        $garantia = Garantia::paraRemate($this->remate, $enRevision);
        $this->actingAs($this->admin)->postJson(route('admin.garantias.aprobar', $garantia))->assertStatus(422)->assertJsonPath('mensaje', 'Primero aprueba la cuenta del postor.');

        // Pasado el cierre de garantías no se inscribe ni sube comprobante.
        $aprobado = $this->postor(Postor::ESTADO_APROBADO);
        $pendiente = Garantia::paraRemate($this->remate, $aprobado);
        CarbonImmutable::setTestNow($this->remate->cierre_garantias_en->addMinute());
        $this->actingAs($aprobado)->post(route('cuenta.comprobante.store', $pendiente), ['medio' => 'transferencia', 'comprobante' => UploadedFile::fake()->create('t.pdf', 10)])
            ->assertSessionHas('error', 'El plazo para constituir la garantía de este remate ya cerró.');
        $otro = $this->postor(Postor::ESTADO_APROBADO);
        $this->actingAs($otro)->post(route('cuenta.inscribirme', $this->remate->slug))->assertSessionHas('error');
    }

    public function test_exportar_listado_csv(): void
    {
        $user = $this->postor(Postor::ESTADO_APROBADO, 'Patricia');
        Garantia::paraRemate($this->remate, $user);

        $respuesta = $this->actingAs($this->admin)->get('/admin/postores/exportar')->assertOk();
        $csv = $respuesta->streamedContent();
        $this->assertStringContainsString('Patricia Ortiz Vera', $csv);
        $this->assertStringContainsString('R-2026-118', $csv);
        $this->assertStringContainsString('14200000', $csv);
    }
}
