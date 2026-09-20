<?php

namespace Tests\Feature;

use App\Models\AccessLog;
use App\Models\Remate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/** Bloque E/F · usuarios internos del panel (QA del 20/09, punto 2). */
class UsuariosInternosTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::create(['name' => 'Ana Admin', 'email' => 'ana@colliers.test', 'password' => 'clave-larga-de-prueba', 'rol' => User::ROL_ADMIN, 'estado' => User::ESTADO_ACTIVO]);
    }

    public function test_crear_un_martillero_desde_el_panel_con_clave_temporal_de_un_solo_uso(): void
    {
        $respuesta = $this->actingAs($this->admin)->from('/admin/usuarios')->post(route('admin.usuarios.store'), [
            'name' => 'M. Ossandón', 'email' => 'MOssandon@colliers.test', 'rol' => User::ROL_MARTILLERO,
        ]);

        $respuesta->assertRedirect('/admin/usuarios')->assertSessionHas('estado');
        $creado = User::where('email', 'mossandon@colliers.test')->sole();
        $this->assertSame(User::ROL_MARTILLERO, $creado->rol);
        $this->assertTrue($creado->debe_cambiar_clave);
        $this->assertNotNull($creado->email_verified_at);

        // La clave se muestra una sola vez y sirve para ingresar.
        $temporal = session('clave_temporal');
        $this->assertSame('mossandon@colliers.test', $temporal['email']);
        $this->assertTrue(Hash::check($temporal['clave'], $creado->password));
        $this->assertSame(1, AccessLog::where('evento', 'usuario_interno_creado')->count());

        // Con ese martillero ya se puede publicar: aparece en el formulario de subastas.
        $this->actingAs($this->admin)->get('/admin/subastas')->assertOk()->assertSee('M. Ossandón');
    }

    public function test_el_correo_repetido_y_el_rol_invalido_se_rechazan(): void
    {
        $this->actingAs($this->admin)->post(route('admin.usuarios.store'), ['name' => 'Otra', 'email' => 'ana@colliers.test', 'rol' => User::ROL_ADMIN])
            ->assertSessionHasErrors('email');
        $this->actingAs($this->admin)->post(route('admin.usuarios.store'), ['name' => 'Otra', 'email' => 'otra@colliers.test', 'rol' => User::ROL_POSTOR])
            ->assertSessionHasErrors('rol');
        $this->assertSame(1, User::count());
    }

    public function test_editar_nombre_correo_y_rol(): void
    {
        $usuario = User::create(['name' => 'C. Vergara', 'email' => 'c@colliers.test', 'password' => 'clave-larga-de-prueba', 'rol' => User::ROL_MARTILLERO, 'estado' => User::ESTADO_ACTIVO]);

        $this->actingAs($this->admin)->put(route('admin.usuarios.update', $usuario), [
            'name' => 'Carolina Vergara', 'email' => 'carolina@colliers.test', 'rol' => User::ROL_ADMIN,
        ])->assertSessionHas('estado');

        $usuario->refresh();
        $this->assertSame('Carolina Vergara', $usuario->name);
        $this->assertSame('carolina@colliers.test', $usuario->email);
        $this->assertSame(User::ROL_ADMIN, $usuario->rol);
    }

    public function test_desactivar_quita_el_acceso_al_panel_y_activar_lo_devuelve(): void
    {
        $martillero = User::create(['name' => 'M. Ossandón', 'email' => 'm@colliers.test', 'password' => 'clave-larga-de-prueba', 'rol' => User::ROL_MARTILLERO, 'estado' => User::ESTADO_ACTIVO]);

        $this->actingAs($this->admin)->post(route('admin.usuarios.estado', $martillero), ['activar' => 0])->assertSessionHas('estado');
        $this->assertSame(User::ESTADO_INACTIVO, $martillero->fresh()->estado);
        $this->actingAs($martillero->fresh())->get('/admin')->assertForbidden();

        $this->actingAs($this->admin)->post(route('admin.usuarios.estado', $martillero), ['activar' => 1])->assertSessionHas('estado');
        $this->assertSame(User::ESTADO_ACTIVO, $martillero->fresh()->estado);
        $this->actingAs($martillero->fresh())->get('/admin')->assertOk();
    }

    public function test_no_se_puede_dejar_el_panel_sin_administradores_ni_desactivarse_a_si_mismo(): void
    {
        // Único administrador activo: ni se desactiva ni se degrada a martillero.
        $this->actingAs($this->admin)->post(route('admin.usuarios.estado', $this->admin), ['activar' => 0])
            ->assertSessionHas('error', 'No puedes desactivar tu propia cuenta.');
        $this->assertSame(User::ESTADO_ACTIVO, $this->admin->fresh()->estado);

        $this->actingAs($this->admin)->put(route('admin.usuarios.update', $this->admin), [
            'name' => 'Ana Admin', 'email' => 'ana@colliers.test', 'rol' => User::ROL_MARTILLERO,
        ])->assertSessionHas('error', 'No puedes cambiarte el rol a ti mismo: pídeselo a otro administrador.');
        $this->assertSame(User::ROL_ADMIN, $this->admin->fresh()->rol);

        // Con dos administradores, uno sí puede desactivar al otro; el que queda no puede quedarse sin acceso
        // (la regla del «único administrador» además lo protege de un cambio de rol hecho por otro).
        $otro = User::create(['name' => 'Beto Admin', 'email' => 'beto@colliers.test', 'password' => 'clave-larga-de-prueba', 'rol' => User::ROL_ADMIN, 'estado' => User::ESTADO_ACTIVO]);
        $this->actingAs($otro)->post(route('admin.usuarios.estado', $this->admin), ['activar' => 0])->assertSessionHas('estado');
        $this->assertSame(User::ESTADO_INACTIVO, $this->admin->fresh()->estado);

        // Desactivado, ya no entra al panel ni administra usuarios.
        $this->actingAs($this->admin->fresh())->get('/admin/usuarios')->assertForbidden();

        // Beto queda solo: no puede desactivarse a sí mismo.
        $this->actingAs($otro)->post(route('admin.usuarios.estado', $otro), ['activar' => 0])
            ->assertSessionHas('error', 'No puedes desactivar tu propia cuenta.');
        $this->assertSame(User::ESTADO_ACTIVO, $otro->fresh()->estado);
    }

    public function test_el_listado_muestra_el_rol_los_remates_a_cargo_y_no_a_los_postores(): void
    {
        $martillero = User::create(['name' => 'M. Ossandón', 'email' => 'm@colliers.test', 'password' => 'clave-larga-de-prueba', 'rol' => User::ROL_MARTILLERO, 'estado' => User::ESTADO_ACTIVO]);
        Remate::create(['folio' => 'R-2026-001', 'slug' => 'uno', 'titulo' => 'Uno', 'estado' => Remate::ESTADO_BORRADOR, 'martillero_id' => $martillero->id]);
        User::create(['name' => 'Postor', 'email' => 'postor@correo.test', 'password' => 'clave-larga-de-prueba', 'rol' => User::ROL_POSTOR, 'estado' => User::ESTADO_ACTIVO]);

        $this->actingAs($this->admin)->get('/admin/usuarios')->assertOk()
            ->assertSee('M. Ossandón')->assertDontSee('postor@correo.test')
            ->assertViewHas('usuarios', fn (array $filas) => count($filas) === 2
                && collect($filas)->firstWhere('email', 'm@colliers.test')['remates'] === 1);
    }

    public function test_solo_administracion_entra_a_usuarios(): void
    {
        $martillero = User::create(['name' => 'M. Ossandón', 'email' => 'm@colliers.test', 'password' => 'clave-larga-de-prueba', 'rol' => User::ROL_MARTILLERO, 'estado' => User::ESTADO_ACTIVO]);
        $postor = User::create(['name' => 'Postor', 'email' => 'postor@correo.test', 'password' => 'clave-larga-de-prueba', 'rol' => User::ROL_POSTOR, 'estado' => User::ESTADO_ACTIVO]);

        $this->actingAs($martillero)->get('/admin/usuarios')->assertForbidden();
        $this->actingAs($martillero)->post(route('admin.usuarios.store'), ['name' => 'X', 'email' => 'x@colliers.test', 'rol' => User::ROL_ADMIN])->assertForbidden();
        $this->actingAs($postor)->get('/admin/usuarios')->assertForbidden();

        // Un postor no se administra por esta pantalla.
        $this->actingAs($this->admin)->put(route('admin.usuarios.update', $postor), ['name' => 'X', 'email' => 'x@correo.test', 'rol' => User::ROL_ADMIN])
            ->assertNotFound();
    }
}
