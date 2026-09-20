<?php

namespace Tests\Feature;

use App\Models\Configuracion;
use App\Models\Garantia;
use App\Models\Postor;
use App\Models\Remate;
use App\Models\User;
use App\Postores\Habilitacion;
use App\Postores\InscripcionGarantias;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * «¿Puede pujar?» en un solo lugar (QA del 20/09, punto 7). Fija la regla vigente —garantía por remate— antes de
 * ofrecer el modo «habilitado una vez», que espera la definición del monto por parte del cliente.
 */
class HabilitacionTest extends TestCase
{
    use RefreshDatabase;

    private function postor(string $email): User
    {
        $user = User::create(['name' => 'Postor', 'email' => $email, 'password' => 'clave-larga-de-prueba', 'rol' => User::ROL_POSTOR, 'estado' => User::ESTADO_ACTIVO]);
        $postor = Postor::create(['user_id' => $user->id, 'nombres' => 'Ana', 'apellidos' => 'Pérez', 'rut' => '17.998.221-8']);
        $postor->forceFill(['estado' => Postor::ESTADO_APROBADO])->save();

        return $user->fresh();
    }

    private function remate(string $slug): Remate
    {
        return Remate::create(['folio' => strtoupper($slug), 'slug' => $slug, 'titulo' => $slug, 'estado' => Remate::ESTADO_PUBLICADO]);
    }

    public function test_el_modo_vigente_es_garantia_por_remate(): void
    {
        $this->assertSame(Habilitacion::MODO_POR_REMATE, Habilitacion::modo());
    }

    public function test_solo_habilita_la_garantia_aprobada_de_ese_remate(): void
    {
        $user = $this->postor('ana@correo.test');
        $uno = $this->remate('uno');
        $otro = $this->remate('otro');

        // Sin inscripción.
        $this->assertNull(Habilitacion::garantia($user, $uno));
        $this->assertFalse(Habilitacion::puedePujar($user, $uno));

        // Inscrito pero pendiente.
        $garantia = Garantia::paraRemate($uno, $user);
        $this->assertFalse(Habilitacion::puedePujar($user, $uno));
        $this->assertFalse(Habilitacion::conGarantiaAprobada($user, $uno));

        // Aprobada: habilita ese remate y ningún otro (regla vigente del acta).
        $garantia->forceFill(['estado' => Garantia::ESTADO_APROBADA])->save();
        $this->assertTrue(Habilitacion::puedePujar($user->fresh(), $uno));
        $this->assertFalse(Habilitacion::puedePujar($user->fresh(), $otro));
    }

    public function test_sin_cuenta_aprobada_no_habilita_aunque_la_garantia_lo_este(): void
    {
        $user = $this->postor('beto@correo.test');
        $remate = $this->remate('uno');
        Garantia::paraRemate($remate, $user)->forceFill(['estado' => Garantia::ESTADO_APROBADA])->save();

        $user->postor->forceFill(['estado' => Postor::ESTADO_BLOQUEADO])->save();
        $this->assertFalse(Habilitacion::puedePujar($user->fresh(), $remate));
        // La garantía sigue existiendo y aprobada: lo que falla es la cuenta.
        $this->assertTrue(Habilitacion::conGarantiaAprobada($user->fresh(), $remate));
    }

    // ── Modo «habilitación única» (interruptor del panel, decisión de Jonas del 20/09) ──────────────────

    private function modoUnico(int $monto = 5000000): void
    {
        Configuracion::guardar('garantia_modo', Habilitacion::MODO_UNICO);
        Configuracion::guardar('garantia_monto_unico', (string) $monto);
    }

    public function test_una_sola_habilitacion_aprobada_sirve_para_todos_los_remates(): void
    {
        $this->modoUnico();
        $user = $this->postor('ana@correo.test');
        $uno = $this->remate('uno');
        $otro = $this->remate('otro');

        // Se constituye una sola vez, por el monto que fijó Colliers, desde el remate que sea.
        $garantia = app(InscripcionGarantias::class)->inscribir($user, $uno);
        $this->assertTrue($garantia->esUnica());
        $this->assertSame(5000000, $garantia->monto);
        $this->assertSame(0, (int) $garantia->porcentaje);   // no aplica: no hay precio base que porcentuar

        // Pedirla de nuevo desde otro remate devuelve la misma: no se duplica.
        $this->assertSame($garantia->id, app(InscripcionGarantias::class)->inscribir($user, $otro)->id);
        $this->assertSame(1, Garantia::count());

        // Pendiente no habilita; aprobada habilita en los dos remates.
        $this->assertFalse(Habilitacion::puedePujar($user, $uno));
        $garantia->forceFill(['estado' => Garantia::ESTADO_APROBADA])->save();
        $this->assertTrue(Habilitacion::puedePujar($user->fresh(), $uno));
        $this->assertTrue(Habilitacion::puedePujar($user->fresh(), $otro));
        $this->assertTrue(Habilitacion::conGarantiaAprobada($user->fresh(), $otro));
    }

    public function test_al_volver_al_modo_por_remate_la_habilitacion_unica_deja_de_servir_en_los_demas(): void
    {
        $this->modoUnico();
        $user = $this->postor('beto@correo.test');
        $origen = $this->remate('origen');
        $otro = $this->remate('otro');
        app(InscripcionGarantias::class)->inscribir($user, $origen)->forceFill(['estado' => Garantia::ESTADO_APROBADA])->save();

        Configuracion::guardar('garantia_modo', Habilitacion::MODO_POR_REMATE);

        // Cambiar de modo no borra nada: la garantía sigue valiendo para el remate desde el que se constituyó.
        $this->assertTrue(Habilitacion::puedePujar($user->fresh(), $origen));
        $this->assertFalse(Habilitacion::puedePujar($user->fresh(), $otro));
    }

    public function test_sin_monto_fijado_no_se_puede_constituir_la_habilitacion(): void
    {
        Configuracion::guardar('garantia_modo', Habilitacion::MODO_UNICO);
        Configuracion::guardar('garantia_monto_unico', '');
        $user = $this->postor('caro@correo.test');

        $this->assertNull(Habilitacion::montoUnico());
        $this->expectException(DomainException::class);
        app(InscripcionGarantias::class)->inscribir($user, $this->remate('uno'));
    }

    public function test_el_panel_no_deja_activar_el_modo_sin_monto(): void
    {
        $admin = User::create(['name' => 'Ana', 'email' => 'ana@colliers.test', 'password' => 'clave-larga-de-prueba', 'rol' => User::ROL_ADMIN, 'estado' => User::ESTADO_ACTIVO]);

        $this->actingAs($admin)->from('/admin/configuracion/garantias')
            ->put('/admin/configuracion/garantias', ['config' => [
                'garantia_modo' => Habilitacion::MODO_UNICO, 'garantia_monto_unico' => '',
                'porcentaje_garantia' => '10', 'garantias_cierre_horas_antes' => '48', 'garantias_revision_horas' => '24',
                'banco_nombre' => '', 'banco_tipo_cuenta' => '', 'banco_numero' => '', 'banco_titular' => '', 'banco_rut' => '', 'banco_correo' => '',
            ]])
            ->assertSessionHasErrors('config.garantia_monto_unico');

        $this->assertSame(Habilitacion::MODO_POR_REMATE, Habilitacion::modo());

        // Con monto sí se activa.
        $this->actingAs($admin)->put('/admin/configuracion/garantias', ['config' => [
            'garantia_modo' => Habilitacion::MODO_UNICO, 'garantia_monto_unico' => '5000000',
            'porcentaje_garantia' => '10', 'garantias_cierre_horas_antes' => '48', 'garantias_revision_horas' => '24',
            'banco_nombre' => '', 'banco_tipo_cuenta' => '', 'banco_numero' => '', 'banco_titular' => '', 'banco_rut' => '', 'banco_correo' => '',
        ]])->assertSessionHasNoErrors();

        Configuracion::olvidar();
        $this->assertSame(Habilitacion::MODO_UNICO, Habilitacion::modo());
        $this->assertSame(5000000, Habilitacion::montoUnico());
    }

    public function test_un_visitante_sin_sesion_nunca_esta_habilitado(): void
    {
        $remate = $this->remate('uno');

        $this->assertNull(Habilitacion::garantia(null, $remate));
        $this->assertFalse(Habilitacion::conGarantiaAprobada(null, $remate));
        $this->assertFalse(Habilitacion::puedePujar(null, $remate));
        $this->assertFalse(Habilitacion::habilita(null, null));
    }
}
