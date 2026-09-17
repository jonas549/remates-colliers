<?php

namespace Tests\Feature;

use App\Models\Adjudicacion;
use App\Models\Configuracion;
use App\Models\Documento;
use App\Models\Garantia;
use App\Models\Lote;
use App\Models\Postor;
use App\Models\Puja;
use App\Models\Remate;
use App\Models\User;
use App\Subastas\Liquidador;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Bloque I · gestión de remates y lotes desde el panel, y panel del martillero. */
class RematesAdminTest extends TestCase
{
    use RefreshDatabase;

    private string $carpeta;

    private User $admin;

    private User $martillero;

    protected function setUp(): void
    {
        parent::setUp();
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-20 15:00:00', 'UTC'));
        $this->carpeta = storage_path('framework/testing/remates-' . getmypid());
        config(['colliers.tiempo_real.carpeta' => $this->carpeta]);
        $this->app->forgetInstance(\App\Subastas\Difusion\Emisor::class);
        Configuracion::sembrarDefectos();

        $this->admin = User::create(['name' => 'Carolina Méndez', 'email' => 'admin@colliers.test', 'password' => 'x-clave-larga-1', 'rol' => User::ROL_ADMIN, 'estado' => User::ESTADO_ACTIVO]);
        $this->martillero = User::create(['name' => 'M. Ossandón', 'email' => 'm@colliers.test', 'password' => 'x-clave-larga-1', 'rol' => User::ROL_MARTILLERO, 'estado' => User::ESTADO_ACTIVO]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->carpeta);
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    private function formulario(array $cambios = []): array
    {
        return $cambios + [
            'direccion' => 'Av. Providencia 2410, Depto. 802', 'comuna' => 'Providencia', 'region' => 'Región Metropolitana',
            'tipo_propiedad' => 'Departamento', 'superficie_util' => '82,5', 'atributos' => ['rol_avaluo' => '1234-56', 'mandante' => 'Banco Consorcio'],
            'ocupacion' => 'Desocupada', 'precio_base' => '120.000.000', 'incremento_minimo' => '', 'porcentaje_garantia' => '',
            'inicio_en' => '2026-09-25T12:00', 'duracion_minutos' => '30', 'cierre_garantias_en' => '',
            'martillero_id' => $this->martillero->id, 'youtube_video_id' => 'jfKfPfyJRdk',
        ];
    }

    public function test_crear_subasta_como_borrador_con_su_primer_lote(): void
    {
        $respuesta = $this->actingAs($this->admin)->post('/admin/subastas', $this->formulario(['accion' => 'borrador']));

        $remate = Remate::sole();
        $respuesta->assertRedirect(route('admin.remates.show', $remate))->assertSessionHas('estado');
        $this->assertSame('R-2026-001', $remate->folio);
        $this->assertSame('av-providencia-2410-depto-802', $remate->slug);
        $this->assertSame(Remate::ESTADO_BORRADOR, $remate->estado);
        $this->assertSame(1800, $remate->duracion_lote_segundos);
        // 12:00 en Santiago (UTC−3 en septiembre) = 15:00 UTC.
        $this->assertSame('2026-09-25 15:00:00', $remate->inicio_en->format('Y-m-d H:i:s'));

        $lote = $remate->lotes()->sole();
        $this->assertSame(120000000, $lote->precio_base);
        $this->assertSame('82.50', $lote->superficie_util);
        $this->assertSame(['rol_avaluo' => '1234-56', 'mandante' => 'Banco Consorcio'], $lote->atributos);
        $this->assertSame('2026-09-25 15:30:00', $lote->cierra_en->format('Y-m-d H:i:s'));
        $this->assertSame(12000000, $remate->montoGarantia(), '10 % global del precio base');
    }

    public function test_publicar_desde_el_formulario_fija_el_cierre_de_garantias_y_publica_el_estado(): void
    {
        $this->actingAs($this->admin)->post('/admin/subastas', $this->formulario(['accion' => 'publicar']))->assertSessionHas('estado');

        $remate = Remate::sole();
        $this->assertSame(Remate::ESTADO_PUBLICADO, $remate->estado);
        $this->assertSame('2026-09-23 15:00:00', $remate->cierre_garantias_en->format('Y-m-d H:i:s'), '48 h antes del inicio');
        $this->assertFileExists("{$this->carpeta}/{$remate->slug}.json");
        $this->assertSame(Remate::VISTA_PROXIMO, $remate->estadoVisible());
    }

    public function test_publicar_con_datos_faltantes_queda_en_borrador_y_dice_que_falta(): void
    {
        $this->actingAs($this->admin)->post('/admin/subastas', $this->formulario(['accion' => 'publicar', 'inicio_en' => '', 'martillero_id' => '']))
            ->assertSessionHas('error', fn (string $e) => str_contains($e, 'Falta la fecha y hora de inicio.') && str_contains($e, 'Asigna un martillero.'));

        $remate = Remate::sole();
        $this->assertSame(Remate::ESTADO_BORRADOR, $remate->estado);
        $this->actingAs($this->admin)->get(route('admin.remates.show', $remate))->assertOk()->assertSee('Para publicar falta:');
    }

    public function test_validacion_del_formulario_en_espanol(): void
    {
        $this->actingAs($this->admin)->post('/admin/subastas', $this->formulario(['precio_base' => '', 'region' => 'Narnia', 'youtube_video_id' => 'no válido!']))
            ->assertSessionHasErrors(['precio_base', 'region', 'youtube_video_id']);
        $this->assertSame(0, Remate::count());
        $this->assertStringContainsString('precio base', session('errors')->first('precio_base'));
    }

    public function test_solo_administracion_gestiona_y_el_martillero_ve_solo_su_panel_en_vivo(): void
    {
        $remate = $this->rematePublicado();
        $otro = User::create(['name' => 'C. Vergara', 'email' => 'c@colliers.test', 'password' => 'x-clave-larga-1', 'rol' => User::ROL_MARTILLERO, 'estado' => User::ESTADO_ACTIVO]);

        $this->actingAs($this->martillero)->post('/admin/subastas', $this->formulario())->assertForbidden();
        $this->actingAs($this->martillero)->get(route('admin.remates.show', $remate))->assertForbidden();
        $this->actingAs($this->martillero)->get('/admin/subastas')->assertOk()->assertDontSee('Crear subasta');
        $this->actingAs($this->martillero)->get(route('admin.remates.en-vivo', $remate))->assertOk()->assertSee('adminEnVivo(', false);
        $this->actingAs($otro)->get(route('admin.remates.en-vivo', $remate))->assertForbidden();
    }

    public function test_listado_y_dashboard_con_datos_reales(): void
    {
        $remate = $this->rematePublicado();

        $this->actingAs($this->admin)->get('/admin/subastas')->assertOk()->assertSee($remate->folio)
            ->assertViewHas('subastas', fn (array $filas) => $filas[0]['estado'] === 'Próxima' && $filas[0]['garantia'] === 12000000
                && $filas[0]['urls']['ficha'] === route('admin.remates.show', $remate) && $filas[0]['inscritos'] === '0 inscritos');
        $this->actingAs($this->admin)->get('/admin')->assertOk()
            ->assertSee('Domingo 20 de septiembre de 2026')
            ->assertSee('SUBASTAS ACTIVAS')->assertSee($remate->folio)
            ->assertSee('Se publicó el remate ' . $remate->folio);
    }

    public function test_condiciones_fijas_una_vez_que_abre_el_primer_lote(): void
    {
        $remate = $this->rematePublicado();
        CarbonImmutable::setTestNow($remate->inicio_en->addMinute());

        $this->actingAs($this->admin)->put(route('admin.remates.update', $remate), ['titulo' => 'Nuevo título', 'inicio_en' => '2026-10-01T12:00'])
            ->assertSessionHas('error');
        $this->assertNotSame('Nuevo título', $remate->fresh()->titulo, 'nada se guarda si se intentó cambiar el horario');

        $this->actingAs($this->admin)->put(route('admin.remates.update', $remate), ['titulo' => 'Nuevo título', 'youtube_video_id' => 'abcdefghijk'])
            ->assertSessionHas('estado');
        $this->assertSame('Nuevo título', $remate->fresh()->titulo);
        $this->assertSame('abcdefghijk', $remate->fresh()->youtube_video_id);
    }

    public function test_varios_lotes_con_horario_fijo_y_pausa(): void
    {
        $remate = $this->rematePublicado();
        $this->actingAs($this->admin)->put(route('admin.remates.update', $remate), ['pausa_minutos' => '5', 'duracion_minutos' => '20'])->assertSessionHas('estado');

        $this->actingAs($this->admin)->post(route('admin.lotes.store', $remate), [
            'direccion' => 'Estacionamiento 12', 'comuna' => 'Providencia', 'region' => 'Región Metropolitana', 'tipo_propiedad' => 'Bodega',
            'precio_base' => '9.000.000', 'duracion_minutos' => '10',
        ])->assertSessionHas('estado');

        [$uno, $dos] = $remate->lotes()->get()->all();
        $this->assertSame(2, $dos->orden);
        $this->assertSame('2026-09-25 15:20:00', $uno->cierra_en->format('Y-m-d H:i:s'));
        $this->assertSame('2026-09-25 15:25:00', $dos->abre_en->format('Y-m-d H:i:s'), 'abre al cerrar el anterior + 5 min de pausa');
        $this->assertSame('2026-09-25 15:35:00', $dos->cierra_en->format('Y-m-d H:i:s'), 'duración propia de 10 min');
    }

    public function test_reordenar_lotes_reprograma_horarios_y_se_bloquea_al_comenzar(): void
    {
        $remate = $this->rematePublicado();
        $this->actingAs($this->admin)->put(route('admin.remates.update', $remate), ['pausa_minutos' => '5', 'duracion_minutos' => '20'])->assertSessionHas('estado');
        foreach (['Bodega 7' => '10', 'Estacionamiento 12' => '15'] as $direccion => $minutos) {
            $this->actingAs($this->admin)->post(route('admin.lotes.store', $remate), [
                'direccion' => $direccion, 'comuna' => 'Providencia', 'region' => 'Región Metropolitana', 'tipo_propiedad' => 'Bodega',
                'precio_base' => '9.000.000', 'duracion_minutos' => $minutos,
            ])->assertSessionHas('estado');
        }
        [$uno, $dos, $tres] = $remate->lotes()->get()->all();

        $this->actingAs($this->admin)->get(route('admin.remates.show', $remate))->assertOk()->assertSee('Subir lote 3')->assertDontSee('Subir lote 1');

        $this->actingAs($this->admin)->post(route('admin.lotes.mover', [$remate, $tres]), ['direccion' => 'subir'])->assertSessionHas('estado');
        $this->assertSame([$uno->id, $tres->id, $dos->id], $remate->lotes()->pluck('id')->all());
        $this->assertSame([1, 2, 3], $remate->lotes()->pluck('orden')->all());
        $tres->refresh();
        $this->assertSame('2026-09-25 15:25:00', $tres->abre_en->format('Y-m-d H:i:s'), 'el lote movido abre segundo');
        $this->assertSame('2026-09-25 15:45:00', $dos->fresh()->abre_en->format('Y-m-d H:i:s'), 'y el desplazado después (15 min + 5 de pausa)');

        $this->actingAs($this->admin)->post(route('admin.lotes.mover', [$remate, $uno]), ['direccion' => 'subir'])->assertSessionHas('estado');
        $this->assertSame([$uno->id, $tres->id, $dos->id], $remate->lotes()->pluck('id')->all(), 'el primero no sube más');
        $this->actingAs($this->admin)->post(route('admin.lotes.mover', [$remate, $uno]), ['direccion' => 'otra'])->assertSessionHasErrors('direccion');

        $otro = $this->rematePublicado();
        $this->actingAs($this->admin)->post(route('admin.lotes.mover', [$remate, $otro->lotes()->first()]), ['direccion' => 'bajar'])->assertNotFound();
        $this->actingAs($this->martillero)->post(route('admin.lotes.mover', [$remate, $uno]), ['direccion' => 'bajar'])->assertForbidden();

        CarbonImmutable::setTestNow($remate->fresh()->inicio_en->addMinute());
        $this->actingAs($this->admin)->post(route('admin.lotes.mover', [$remate, $uno]), ['direccion' => 'bajar'])->assertSessionHas('error');
        $this->assertSame([$uno->id, $tres->id, $dos->id], $remate->lotes()->pluck('id')->all(), 'con el remate comenzado no cambia');
    }

    public function test_fotos_visitas_y_documentos_del_lote(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        $remate = $this->rematePublicado();
        $lote = $remate->lotes()->first();

        $this->actingAs($this->admin)->post(route('admin.lotes.imagenes.store', [$remate, $lote]), [
            'imagenes' => [UploadedFile::fake()->image('grande.jpg', 2560, 1920), UploadedFile::fake()->image('otra.png', 800, 600)],
        ])->assertSessionHas('estado', '2 fotos agregadas.');
        [$primera, $segunda] = $lote->imagenes()->get()->all();
        Storage::disk('public')->assertExists($primera->ruta);
        $this->assertSame([1920, 1440], array_slice(getimagesize(Storage::disk('public')->path($primera->ruta)), 0, 2), 'reducida a 1920 px');

        $this->actingAs($this->admin)->post(route('admin.lotes.imagenes.portada', [$remate, $lote, $segunda]))->assertSessionHas('estado');
        $this->assertSame($segunda->id, $lote->imagenes()->first()->id);
        $this->actingAs($this->admin)->delete(route('admin.lotes.imagenes.destroy', [$remate, $lote, $primera]))->assertSessionHas('estado');
        Storage::disk('public')->assertMissing($primera->ruta);

        $this->actingAs($this->admin)->post(route('admin.lotes.visitas.store', [$remate, $lote]), ['inicia_en' => '2026-09-22T11:00', 'termina_en' => '2026-09-22T13:00'])
            ->assertSessionHas('estado');
        $this->assertSame('2026-09-22 14:00:00', $lote->visitas()->first()->inicia_en->format('Y-m-d H:i:s'));

        $this->actingAs($this->admin)->post(route('admin.documentos.store', $remate), [
            'titulo' => 'Bases especiales', 'archivo' => UploadedFile::fake()->create('bases.pdf', 120, 'application/pdf'), 'publico' => '1',
        ])->assertSessionHas('estado');
        $documento = Documento::sole();
        Storage::disk('local')->assertExists($documento->ruta);
        $this->actingAs($this->admin)->get(route('admin.documentos.descargar', [$remate, $documento]))->assertOk()->assertDownload('bases.pdf');

        // Un lote de otro remate no existe en la ruta de este remate.
        $otro = $this->rematePublicado('otro');
        $this->actingAs($this->admin)->get(route('admin.lotes.edit', [$otro, $lote]))->assertNotFound();
    }

    public function test_cancelar_solo_antes_de_comenzar(): void
    {
        $remate = $this->rematePublicado();
        $this->actingAs($this->admin)->post(route('admin.remates.cancelar', $remate), ['motivo' => ''])->assertSessionHasErrors('motivo');
        $this->actingAs($this->admin)->post(route('admin.remates.cancelar', $remate), ['motivo' => 'Retiro de la propiedad'])->assertSessionHas('estado');
        $this->assertSame(Remate::ESTADO_CANCELADO, $remate->fresh()->estado);
        $this->assertSame('Retiro de la propiedad', $remate->fresh()->motivo_cancelacion);

        $enVivo = $this->rematePublicado('vivo');
        CarbonImmutable::setTestNow($enVivo->inicio_en->addMinute());
        $this->actingAs($this->admin)->post(route('admin.remates.cancelar', $enVivo), ['motivo' => 'x'])->assertSessionHas('error');
        $this->assertSame(Remate::ESTADO_PUBLICADO, $enVivo->fresh()->estado);
    }

    public function test_cerrar_ahora_en_vivo_adjudica_la_mejor_puja_con_el_motivo(): void
    {
        $remate = $this->rematePublicado();
        $lote = $remate->lotes()->first();
        CarbonImmutable::setTestNow($remate->inicio_en->addMinutes(5));
        $postor = $this->postorHabilitado($remate);
        $this->actingAs($postor)->postJson("/remates/{$remate->slug}/lotes/{$lote->id}/pujas", ['monto' => 120000000])->assertCreated();

        $this->actingAs($this->admin)->post(route('admin.remates.cerrar-ahora', $remate), ['motivo' => 'Instrucción del mandante'])
            ->assertSessionHas('estado');
        $lote->refresh();
        $this->assertSame(Lote::CIERRE_ANTICIPADO, $lote->motivo_cierre);
        $this->assertSame('Instrucción del mandante', $lote->nota_cierre);
        $this->assertSame($this->admin->id, $lote->cerrado_por_id);

        CarbonImmutable::setTestNow(CarbonImmutable::now('UTC')->addSeconds(3));
        app(Liquidador::class)->liquidarVencidos($remate);
        $this->assertSame(120000000, Adjudicacion::sole()->monto);
        $this->assertSame(Remate::VISTA_ADJUDICADO, $remate->fresh()->estadoVisible());
    }

    public function test_crear_remate_nuevo_a_partir_de_uno_desierto(): void
    {
        Storage::fake('public');
        $remate = $this->rematePublicado();
        $lote = $remate->lotes()->first();
        $lote->imagenes()->create(['ruta' => 'img/demo/prop-montt.jpg', 'orden' => 1]);
        CarbonImmutable::setTestNow($lote->cierra_en->addMinutes(1));
        app(Liquidador::class)->liquidarVencidos($remate);
        $this->assertSame(Remate::VISTA_CERRADO, $remate->fresh()->estadoVisible());

        $this->actingAs($this->admin)->post(route('admin.remates.republicar', $remate))->assertSessionHas('estado');

        $nuevo = Remate::where('remate_origen_id', $remate->id)->sole();
        $this->assertSame(Remate::ESTADO_BORRADOR, $nuevo->estado);
        $this->assertNotSame($remate->folio, $nuevo->folio);
        $this->assertNull($nuevo->inicio_en, 'la fecha se fija de nuevo');
        $copia = $nuevo->lotes()->sole();
        $this->assertSame($lote->id, $copia->lote_origen_id);
        $this->assertSame(Lote::ESTADO_PROGRAMADO, $copia->estado);
        $this->assertSame(['rol_avaluo' => '1234-56', 'mandante' => 'Banco Consorcio'], $copia->atributos);
        $this->assertSame('img/demo/prop-montt.jpg', $copia->imagenes()->sole()->ruta);
        $this->assertSame(Lote::ESTADO_DESIERTO, $lote->fresh()->estado, 'el original no se toca');
    }

    public function test_panel_en_vivo_muestra_identidades_solo_al_panel(): void
    {
        $remate = $this->rematePublicado();
        $postor = $this->postorHabilitado($remate, 'Ana Postora');

        $html = $this->actingAs($this->admin)->get(route('admin.remates.en-vivo', $remate))->assertOk()->getContent();
        $this->assertStringContainsString('Ana Postora', html_entity_decode($html));
        $this->assertStringContainsString('/hora.php', $html);
        $this->assertStringNotContainsString('Ana Postora', (string) file_get_contents("{$this->carpeta}/{$remate->slug}.json"));
    }

    private function rematePublicado(string $sufijo = ''): Remate
    {
        $this->actingAs($this->admin)->post('/admin/subastas', $this->formulario([
            'accion' => 'publicar', 'direccion' => 'Av. Providencia 2410' . ($sufijo ? " {$sufijo}" : ''),
        ]));

        return Remate::latest('id')->firstOrFail();
    }

    private function postorHabilitado(Remate $remate, string $nombre = 'Postor'): User
    {
        static $n = 0;
        $n++;
        $user = User::create(['name' => $nombre, 'email' => "p{$n}@correo.test", 'password' => 'x-clave-larga-1', 'rol' => User::ROL_POSTOR, 'estado' => User::ESTADO_ACTIVO]);
        $user->forceFill(['email_verified_at' => now()])->save();
        $cuerpo = (string) (40000000 + $n);
        Postor::create(['user_id' => $user->id, 'nombres' => $nombre, 'apellidos' => 'Prueba', 'rut' => $cuerpo . \App\Support\Rut::digitoVerificador($cuerpo)])
            ->forceFill(['estado' => Postor::ESTADO_APROBADO])->save();
        Garantia::paraRemate($remate, $user)->forceFill(['estado' => Garantia::ESTADO_APROBADA])->save();

        return $user;
    }
}
