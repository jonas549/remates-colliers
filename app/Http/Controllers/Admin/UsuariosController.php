<?php

namespace App\Http\Controllers\Admin;

use App\Autenticacion\Sesiones;
use App\Http\Controllers\Controller;
use App\Models\AccessLog;
use App\Models\Postor;
use App\Models\PostorDocumento;
use App\Models\User;
use App\Support\Formato;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Usuarios internos del panel: administradores y martilleros (Bloque E/F, pedido en el QA del 20/09). Crear, editar,
 * desactivar y restablecer la clave desde la pantalla «06 Usuarios», para no depender de `colliers:crear-usuario`
 * por terminal. Los postores NO se administran aquí: van en «03 Postores», con sus datos personales y su garantía.
 *
 * Reglas: las cuentas internas no se borran (dejan remates y bitácora detrás), se desactivan; nadie se desactiva ni
 * se cambia el rol a sí mismo; y siempre queda al menos un administrador activo.
 */
class UsuariosController extends Controller
{
    public function index(Request $request): View
    {
        $usuarios = User::whereIn('rol', array_keys(User::ROLES_INTERNOS))
            ->withCount(['rematesComoMartillero as remates'])
            ->orderBy('rol')->orderBy('name')->get();

        $ahora = CarbonImmutable::now('UTC');

        return view('admin.usuarios', [
            'usuarios' => $usuarios->map(fn (User $u) => [
                'id' => $u->id,
                'nombre' => $u->name,
                'email' => $u->email,
                'rol' => $u->rol,
                'rolTexto' => $u->nombreRol(),
                'activo' => $u->estado === User::ESTADO_ACTIVO,
                'estadoTexto' => $u->estado === User::ESTADO_ACTIVO ? 'Activo' : 'Inactivo',
                'bloqueado' => $u->bloqueado_hasta !== null && $u->bloqueado_hasta->greaterThan($ahora),
                'debeCambiarClave' => (bool) $u->debe_cambiar_clave,
                'remates' => $u->remates,
                'ultimoIngreso' => ($fecha = AccessLog::where('user_id', $u->id)->where('evento', 'ingreso')->max('created_at'))
                    ? Formato::fechaCorta(CarbonImmutable::parse($fecha, 'UTC')) : null,
                'esTu' => $u->is($request->user()),
                'urls' => [
                    'actualizar' => route('admin.usuarios.update', $u),
                    'estado' => route('admin.usuarios.estado', $u),
                    'clave' => route('admin.usuarios.restablecer-clave', $u),
                    'desbloquear' => route('admin.usuarios.desbloquear', $u),
                    'sesiones' => route('admin.usuarios.cerrar-sesiones', $u),
                ],
            ])->values()->all(),
        ]);
    }

    /** Crea un administrador, martillero o editor con clave temporal de un solo uso (la misma regla que el comando). */
    public function store(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique(User::class, 'email')],
            'rol' => ['required', Rule::in(array_keys(User::ROLES_INTERNOS))],
        ], [], ['name' => 'nombre', 'email' => 'correo', 'rol' => 'rol']);

        $temporal = Str::password(14, symbols: false);
        $user = User::create([
            'name' => $datos['name'],
            'email' => mb_strtolower($datos['email']),
            'password' => $temporal,
            'rol' => $datos['rol'],
            'estado' => User::ESTADO_ACTIVO,
            'debe_cambiar_clave' => true,
        ]);
        // Las cuentas internas no confirman correo: las crea un administrador.
        $user->forceFill(['email_verified_at' => CarbonImmutable::now('UTC')])->save();
        $this->anotar($request, $user, 'usuario_interno_creado', ['rol' => $user->rol]);

        return back()->with('estado', (['admin' => 'Administrador', 'martillero' => 'Martillero', 'editor' => 'Editor'][$user->rol] ?? $user->nombreRol()) . " {$user->name} creado.")
            ->with('clave_temporal', ['email' => $user->email, 'clave' => $temporal]);
    }

    public function update(Request $request, User $usuario): RedirectResponse
    {
        $this->soloInternos($usuario);
        $datos = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique(User::class, 'email')->ignore($usuario->id)],
            'rol' => ['required', Rule::in(array_keys(User::ROLES_INTERNOS))],
        ], [], ['name' => 'nombre', 'email' => 'correo', 'rol' => 'rol']);

        if ($usuario->is($request->user()) && $datos['rol'] !== $usuario->rol) {
            return back()->with('error', 'No puedes cambiarte el rol a ti mismo: pídeselo a otro administrador.');
        }
        if ($datos['rol'] !== User::ROL_ADMIN && $this->esElUltimoAdmin($usuario)) {
            return back()->with('error', 'Es el único administrador activo: primero crea o activa otro.');
        }

        $usuario->update($datos + ['email' => mb_strtolower($datos['email'])]);
        $this->anotar($request, $usuario, 'usuario_interno_editado', ['rol' => $usuario->rol]);

        return back()->with('estado', "Datos de {$usuario->name} guardados.");
    }

    /** Activar o desactivar: nunca se borra una cuenta interna (queda en remates y en la bitácora). */
    public function estado(Request $request, User $usuario): RedirectResponse
    {
        $this->soloInternos($usuario);
        $activar = $request->boolean('activar');

        if (! $activar && $usuario->is($request->user())) {
            return back()->with('error', 'No puedes desactivar tu propia cuenta.');
        }
        if (! $activar && $this->esElUltimoAdmin($usuario)) {
            return back()->with('error', 'Es el único administrador activo: primero crea o activa otro.');
        }

        $usuario->forceFill(['estado' => $activar ? User::ESTADO_ACTIVO : User::ESTADO_INACTIVO])->save();
        $cerradas = $activar ? 0 : Sesiones::cerrarTodas($usuario);
        $this->anotar($request, $usuario, $activar ? 'usuario_interno_activado' : 'usuario_interno_desactivado', ['sesiones_cerradas' => $cerradas]);

        return back()->with('estado', $activar
            ? "{$usuario->name} vuelve a tener acceso al panel."
            : "{$usuario->name} queda sin acceso" . ($cerradas ? " y se cerraron {$cerradas} sesión(es)." : '.'));
    }

    private function soloInternos(User $usuario): void
    {
        abort_unless($usuario->esAdministracion(), 404);
    }

    private function esElUltimoAdmin(User $usuario): bool
    {
        return $usuario->rol === User::ROL_ADMIN && $usuario->estado === User::ESTADO_ACTIVO
            && User::where('rol', User::ROL_ADMIN)->where('estado', User::ESTADO_ACTIVO)->where('id', '!=', $usuario->id)->doesntExist();
    }

    /**
     * Restablece la contraseña de otra cuenta: genera una clave temporal que se muestra UNA vez, obliga a cambiarla
     * al ingresar, levanta el bloqueo y cierra todas sus sesiones.
     */
    public function restablecerClave(Request $request, User $usuario): JsonResponse
    {
        abort_if($usuario->is($request->user()), 422, 'Para tu propia cuenta usa «Cambiar contraseña».');

        $temporal = Str::password(14, symbols: false);
        $usuario->forceFill(['password' => $temporal, 'debe_cambiar_clave' => true, 'intentos_fallidos' => 0, 'bloqueado_hasta' => null])->save();
        $cerradas = Sesiones::cerrarTodas($usuario);
        $this->anotar($request, $usuario, 'clave_restablecida_por_admin', ['sesiones_cerradas' => $cerradas]);

        return response()->json([
            'clave_temporal' => $temporal,
            'sesiones_cerradas' => $cerradas,
            'mensaje' => 'Entrega la clave temporal por un canal seguro. Se pedirá cambiarla al ingresar.',
        ]);
    }

    public function desbloquear(Request $request, User $usuario): JsonResponse
    {
        $usuario->forceFill(['intentos_fallidos' => 0, 'bloqueado_hasta' => null])->save();
        $this->anotar($request, $usuario, 'desbloqueo_por_admin', []);

        return response()->json(['desbloqueado' => true]);
    }

    public function cerrarSesiones(Request $request, User $usuario): JsonResponse
    {
        $cerradas = Sesiones::cerrarTodas($usuario, $usuario->is($request->user()) ? $request->session()->getId() : null);
        $this->anotar($request, $usuario, 'sesiones_cerradas_por_admin', ['cantidad' => $cerradas]);

        return response()->json(['sesiones_cerradas' => $cerradas]);
    }

    /** Documento de un postor: se busca DENTRO del postor de la ruta, nunca solo por su id. */
    public function documento(Postor $postor, int $documento): StreamedResponse
    {
        $modelo = PostorDocumento::where('postor_id', $postor->id)->findOrFail($documento);
        Gate::authorize('descargar', $modelo);

        return Storage::disk('local')->download($modelo->ruta, $modelo->nombre_original);
    }

    private function anotar(Request $request, User $afectado, string $evento, array $detalle): void
    {
        AccessLog::create([
            'user_id' => $afectado->id, 'email' => $afectado->email, 'evento' => $evento,
            'ip' => $request->ip(), 'user_agent' => mb_substr((string) $request->userAgent(), 0, 512),
            'detalle' => $detalle + ['por_user_id' => $request->user()->id],
        ]);
    }
}
