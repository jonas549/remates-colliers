<?php

namespace Tests\Feature;

use App\Models\Garantia;
use App\Models\Postor;
use App\Models\Remate;
use App\Models\User;
use App\Postores\Habilitacion;
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

    public function test_un_visitante_sin_sesion_nunca_esta_habilitado(): void
    {
        $remate = $this->remate('uno');

        $this->assertNull(Habilitacion::garantia(null, $remate));
        $this->assertFalse(Habilitacion::conGarantiaAprobada(null, $remate));
        $this->assertFalse(Habilitacion::puedePujar(null, $remate));
        $this->assertFalse(Habilitacion::habilita(null, null));
    }
}
