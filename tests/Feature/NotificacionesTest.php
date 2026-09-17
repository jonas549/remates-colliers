<?php

namespace Tests\Feature;

use App\Models\Adjudicacion;
use App\Models\Configuracion;
use App\Models\Garantia;
use App\Models\Lote;
use App\Models\NotificacionLog;
use App\Models\Postor;
use App\Models\Remate;
use App\Models\Suscripcion;
use App\Models\User;
use App\Remates\GestionRemates;
use App\Subastas\Liquidador;
use App\Subastas\MotorPujas;
use App\Support\Rut;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** Bloque M · correos por la cola, bitácora, «Avísame» y recordatorios. En pruebas la cola es síncrona y el correo, en memoria. */
class NotificacionesTest extends TestCase
{
    use RefreshDatabase;

    private string $carpeta;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-20 15:00:00', 'UTC'));
        $this->carpeta = storage_path('framework/testing/avisos-' . getmypid());
        config(['colliers.tiempo_real.carpeta' => $this->carpeta]);
        $this->app->forgetInstance(\App\Subastas\Difusion\Emisor::class);
        Configuracion::sembrarDefectos();
        $this->admin = User::create(['name' => 'Carolina', 'email' => 'admin@colliers.test', 'password' => 'x-clave-larga-1', 'rol' => User::ROL_ADMIN, 'estado' => User::ESTADO_ACTIVO]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->carpeta);
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    /** @return list<\Symfony\Component\Mime\Email> */
    private function correos(): array
    {
        return array_map(fn ($m) => $m->getOriginalMessage(), iterator_to_array(Mail::mailer('array')->getSymfonyTransport()->messages()));
    }

    private function asuntos(): array
    {
        return array_map(fn ($c) => $c->getSubject() . ' → ' . $c->getTo()[0]->getAddress(), $this->correos());
    }

    private function postor(string $estado = Postor::ESTADO_APROBADO, string $nombre = 'Ana'): User
    {
        static $n = 0;
        $n++;
        $user = User::create(['name' => $nombre, 'email' => "p{$n}@correo.test", 'password' => 'x-clave-larga-1', 'rol' => User::ROL_POSTOR, 'estado' => User::ESTADO_ACTIVO]);
        $user->forceFill(['email_verified_at' => now()])->save();
        $cuerpo = (string) (60000000 + $n);
        Postor::create(['user_id' => $user->id, 'nombres' => $nombre, 'apellidos' => 'Prueba', 'rut' => $cuerpo . Rut::digitoVerificador($cuerpo)])->forceFill(['estado' => $estado])->save();

        return $user->fresh();
    }

    private function remate(array $datos = [], int $diasHastaInicio = 5): Remate
    {
        static $n = 0;
        $n++;
        $remate = Remate::create($datos + [
            'folio' => "R-2026-9{$n}", 'slug' => "remate-{$n}", 'titulo' => "Remate {$n}", 'estado' => Remate::ESTADO_PUBLICADO,
            'inicio_en' => CarbonImmutable::now('UTC')->addDays($diasHastaInicio), 'cierre_garantias_en' => CarbonImmutable::now('UTC')->addDays($diasHastaInicio)->subHours(48),
            'martillero_id' => $this->admin->id,
        ]);
        Lote::create(['remate_id' => $remate->id, 'titulo' => "Lote {$n}", 'direccion' => "Dirección {$n}", 'comuna' => 'Ñuñoa', 'region' => 'Región Metropolitana', 'tipo_propiedad' => 'Casa', 'precio_base' => 100000000]);
        $remate->programarLotes();

        return $remate;
    }

    public function test_revision_de_cuenta_y_garantia_envia_correos_en_espanol_y_los_registra(): void
    {
        $user = $this->postor(Postor::ESTADO_EN_REVISION, 'Camila');
        $remate = $this->remate();

        $this->actingAs($this->admin)->postJson(route('admin.postores.aprobar', $user->postor))->assertOk();
        $garantia = Garantia::paraRemate($remate, $user->fresh());
        $this->actingAs($this->admin)->postJson(route('admin.garantias.rechazar', $garantia), ['motivo' => 'El titular no coincide'])->assertOk();

        $this->assertSame([
            'Tu cuenta fue aprobada · Remates Colliers → ' . $user->email,
            'No pudimos aprobar tu garantía · Remates Colliers → ' . $user->email,
        ], $this->asuntos());
        $html = $this->correos()[1]->getHtmlBody();
        $this->assertStringContainsString('Motivo: El titular no coincide', $html);
        $this->assertStringContainsString('Hola Camila', $html);
        $this->assertStringContainsString('Si no puedes hacer clic en el botón', $html);
        $this->assertStringNotContainsString('Regards', $html);

        $log = NotificacionLog::orderBy('id')->get();
        $this->assertSame(['CuentaRevisadaAviso', 'GarantiaRevisadaAviso'], $log->pluck('tipo')->all());
        $this->assertSame(['enviada', 'enviada'], $log->pluck('estado')->all());
        $this->assertSame($garantia->id, $log[1]->notificable_id);
        $this->assertSame($user->id, $log[1]->user_id);
    }

    public function test_al_cerrar_avisa_al_adjudicatario_a_quienes_no_ganaron_y_resume_a_la_administracion(): void
    {
        $remate = $this->remate([], 0);
        $lote = $remate->lotes()->first();
        $lote->forceFill(['abre_en' => CarbonImmutable::now('UTC')->subMinutes(5), 'cierra_en' => CarbonImmutable::now('UTC')->addMinutes(5)])->save();
        $ana = $this->postor();
        $beto = $this->postor(nombre: 'Beto');
        foreach ([$ana, $beto] as $postor) {
            Garantia::paraRemate($remate, $postor)->forceFill(['estado' => Garantia::ESTADO_APROBADA])->save();
        }
        app(MotorPujas::class)->pujar($beto, $lote->id, 100000000, CarbonImmutable::now('UTC'));
        app(MotorPujas::class)->pujar($ana, $lote->id, 100100000, CarbonImmutable::now('UTC'));

        CarbonImmutable::setTestNow($lote->cierra_en->addSeconds(3));
        app(Liquidador::class)->liquidarVencidos($remate);

        // Un correo al ganador, uno a quien pujó y no ganó, y UN resumen a la administración (decisión del 17/09).
        $this->assertSame([
            'Te adjudicaste la propiedad · Remates Colliers → ' . $ana->email,
            'Resultado del remate · Remates Colliers → ' . $beto->email,
            'Remate cerrado: resumen · Remates Colliers → admin@colliers.test',
        ], $this->asuntos());
        $this->assertStringContainsString('$100.100.000', $this->correos()[0]->getHtmlBody());
        $resumen = $this->correos()[2]->getHtmlBody();
        $this->assertStringContainsString('adjudicado en $100.100.000', $resumen);
        $this->assertStringContainsString('Adjudicados: 1 de 1', $resumen);
        $adjudicacion = Adjudicacion::sole();
        $this->assertNotNull($adjudicacion->notificado_ganador_en);
        $this->assertNotNull($adjudicacion->notificado_admin_en);

        // Desierto: solo el resumen, al correo de avisos si está configurado.
        Configuracion::guardar('correo_avisos_admin', 'avisos@colliers.test');
        $desierto = $this->remate([], 0);
        $l2 = $desierto->lotes()->first();
        $l2->forceFill(['abre_en' => CarbonImmutable::now('UTC')->subMinutes(10), 'cierra_en' => CarbonImmutable::now('UTC')->subMinute()])->save();
        app(Liquidador::class)->liquidarVencidos($desierto);
        $this->assertSame('Remate cerrado: resumen · Remates Colliers → avisos@colliers.test', last($this->asuntos()));
        $this->assertStringContainsString('desierto', last($this->correos())->getHtmlBody());
    }

    public function test_los_interruptores_de_notificaciones_apagan_un_correo(): void
    {
        \App\Correo\Avisos::guardar('cuenta_aprobada', false);
        $user = $this->postor(Postor::ESTADO_EN_REVISION, 'Camila');

        $this->actingAs($this->admin)->postJson(route('admin.postores.aprobar', $user->postor))->assertOk();

        $this->assertSame([], $this->asuntos(), 'apagado no se envía');
        $this->assertSame(0, NotificacionLog::count(), 'y no se anota en la bitácora');

        // El correo de la cuenta del postor (confirmar correo) no se puede apagar.
        \App\Correo\Avisos::guardar('bienvenida', false);
        $this->assertTrue(\App\Correo\Avisos::activo('bienvenida'));
    }

    public function test_los_remates_de_demostracion_no_envian_correos(): void
    {
        $remate = $this->remate([], 0);
        $remate->forceFill(['es_demostracion' => true])->save();
        $lote = $remate->lotes()->first();
        $lote->forceFill(['abre_en' => CarbonImmutable::now('UTC')->subMinutes(10), 'cierra_en' => CarbonImmutable::now('UTC')->subMinute()])->save();

        app(Liquidador::class)->liquidarVencidos($remate);

        $this->assertSame([], $this->asuntos());
    }

    public function test_avisame_publicacion_y_baja(): void
    {
        $this->postJson('/avisame', ['email' => 'Interesado@Correo.test'])->assertOk()->assertJsonPath('ok', true);
        $this->postJson('/avisame', ['email' => 'interesado@correo.test'])->assertOk();
        $this->postJson('/avisame', ['email' => 'no-es-correo'])->assertStatus(422);
        $this->assertSame(1, Suscripcion::count(), 'el mismo correo no se duplica');

        $borrador = $this->remate(['estado' => Remate::ESTADO_BORRADOR]);
        app(GestionRemates::class)->publicar($borrador);

        $this->assertSame(['Remate nuevo publicado · Remates Colliers → interesado@correo.test'], $this->asuntos());
        $suscripcion = Suscripcion::sole();
        $this->assertStringContainsString(route('suscripciones.baja', $suscripcion->token), $this->correos()[0]->getHtmlBody());

        $this->get(route('suscripciones.baja', $suscripcion->token))->assertOk()->assertSee('no te enviaremos más avisos');
        $this->assertNotNull($suscripcion->fresh()->baja_en);
        app(GestionRemates::class)->publicar($this->remate(['estado' => Remate::ESTADO_BORRADOR]));
        $this->assertCount(1, $this->asuntos(), 'dado de baja no recibe más');
    }

    public function test_recordatorios_antes_del_remate_una_sola_vez(): void
    {
        $remate = $this->remate([], 2);
        $aprobada = $this->postor(nombre: 'Aprobada');
        $pendiente = $this->postor(nombre: 'Pendiente');
        Garantia::paraRemate($remate, $aprobada)->forceFill(['estado' => Garantia::ESTADO_APROBADA])->save();
        Garantia::paraRemate($remate, $pendiente);
        $this->postJson('/avisame', ['email' => 'curioso@correo.test', 'remate' => $remate->slug])->assertOk();
        $this->postJson('/avisame', ['email' => 'general@correo.test'])->assertOk();

        // Lejos del inicio y del cierre de garantías: nada.
        $this->artisan('colliers:recordatorios')->assertSuccessful();
        $this->assertSame([], $this->asuntos());

        // 47 h antes del cierre de garantías: aviso a suscriptores generales.
        CarbonImmutable::setTestNow($remate->cierre_garantias_en->subHours(47));
        $this->artisan('colliers:recordatorios')->assertSuccessful();
        $this->assertSame(['Cierra el plazo de garantías · Remates Colliers → general@correo.test'], $this->asuntos());

        // 23 h antes del inicio (recordatorio de 24 h): inscritos aprobados y suscriptores del remate; a la pendiente, que falta.
        CarbonImmutable::setTestNow($remate->abreEn()->subHours(23));
        $remate->forceFill(['cierre_garantias_en' => $remate->abreEn()->subHour()])->save();
        $this->artisan('colliers:recordatorios')->assertSuccessful();
        $this->artisan('colliers:recordatorios')->assertSuccessful();

        $asuntos = $this->asuntos();
        sort($asuntos);
        $this->assertSame([
            'Cierra el plazo de garantías · Remates Colliers → general@correo.test',
            'Falta aprobar tu garantía · Remates Colliers → ' . $pendiente->email,
            'Tu remate comienza pronto · Remates Colliers → curioso@correo.test',
            'Tu remate comienza pronto · Remates Colliers → ' . $aprobada->email,
        ], $asuntos);
        $this->assertSame(0, NotificacionLog::where('estado', 'pendiente')->count(), 'los pendientes pasan a enviados');
    }
}
