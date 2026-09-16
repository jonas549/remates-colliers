<?php

use App\Demo\AdminDemo;
use App\Demo\DetalleDemo;
use App\Demo\PantallasRevision;
use App\Demo\RematesDemo;
use App\Http\Controllers\AccesoSandboxController;
use App\Http\Controllers\Admin\SalaMartilleroController;
use App\Http\Controllers\Admin\UsuariosController;
use App\Http\Controllers\CuentaController;
use App\Http\Controllers\PujaController;
use App\Http\Controllers\RevisionController;
use App\Http\Controllers\TiempoRealController;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Http\Controllers\AuthenticatedSessionController;

/*
 * Bloque T: rutas definitivas con vistas alimentadas por datos de ejemplo (App\Demo).
 *
 * La raíz es el listado de remates; /remates redirige a "/".
 */

// Clave de acceso al sandbox (ver App\Http\Middleware\AccesoSandbox).
Route::get('/acceso', [AccesoSandboxController::class, 'formulario'])->name('acceso.formulario');
Route::post('/acceso', [AccesoSandboxController::class, 'ingresar'])->name('acceso.ingresar');

/*
 * Bloque J: motor de pujas y tiempo real. Los espectadores leen /tiempo-real/{remate}.json (estático);
 * estas rutas ejecutan PHP solo para pujar, sincronizar el reloj, reconectar y cerrar.
 */
Route::get('/hora', [TiempoRealController::class, 'hora'])->name('tiempo-real.hora');
Route::get('/remates/{remate:slug}/estado', [TiempoRealController::class, 'estado'])
    ->middleware('throttle:estado-remate')->name('tiempo-real.estado');
Route::post('/remates/{remate:slug}/lotes/{lote}/pujas', [PujaController::class, 'store'])
    ->whereNumber('lote')->middleware(['auth', 'clave.vigente', 'throttle:pujas'])->name('pujas.store');
Route::post('/admin/remates/{remate:slug}/lotes/{lote}/cerrar', [SalaMartilleroController::class, 'cerrarLote'])
    ->whereNumber('lote')->middleware(['auth', 'clave.vigente'])->name('admin.sala.cerrar-lote');
Route::post('/admin/remates/{remate:slug}/mensaje', [SalaMartilleroController::class, 'mensaje'])
    ->middleware(['auth', 'clave.vigente'])->name('admin.sala.mensaje');

// ?sesion= (visitante | registrado | en-revision | aprobada) simula la sesión en las páginas públicas hasta K y N.
$sesionDemo = fn () => in_array(request('sesion'), ['registrado', 'en-revision', 'aprobada'], true) ? request('sesion') : 'visitante';

Route::get('/', fn () => view('remates.index', [
    'remates' => RematesDemo::todos(),
    'hero' => RematesDemo::buscar('militares'),
    'sesion' => $sesionDemo(),
]))->name('remates.index');
Route::permanentRedirect('/remates', '/');

// Índice de pantallas para revisar variantes: solo en el entorno local, nunca en el servidor.
if (app()->isLocal()) {
    Route::get('/revision', fn () => view('revision', ['grupos' => PantallasRevision::todas()]))->name('revision');
    // Entra como el primer usuario de un rol del seeder (pantallas protegidas y arnés visual).
    Route::get('/revision/entrar/{rol}', [RevisionController::class, 'entrar'])->name('revision.entrar');
}
// Demo: 'apoquindo' es el remate en vivo; cualquier otro muestra la ficha de 'militares' (próximo).
Route::get('/remates/{remate}', fn (string $remate) => view('remates.show', [
    'r' => DetalleDemo::para($remate) ?? DetalleDemo::para('militares'),
    'sesion' => $sesionDemo(),
]))->name('remates.show');
Route::get('/remates/{remate}/sala', fn () => view('sala.show'))->name('sala.show');

/*
 * Bloque D: autenticación. Fortify registra /ingresar, /registro, /salir, /recuperar-clave, /restablecer-clave y
 * /verificar-correo (config/fortify.php). Aquí: el acceso separado de administración y el área de la cuenta.
 */
Route::middleware('guest')->group(function () {
    Route::get('/admin/ingresar', fn () => view('auth.login', ['proximo' => RematesDemo::proximoDestacado(), 'portal' => 'administracion']))
        ->name('admin.ingresar');
    Route::post('/admin/ingresar', [AuthenticatedSessionController::class, 'store'])
        ->middleware('throttle:login')->name('admin.ingresar.store');
});

Route::middleware('auth')->group(function () {
    Route::get('/mi-cuenta/cambiar-clave', [CuentaController::class, 'clave'])->name('cuenta.clave');

    Route::middleware('clave.vigente')->group(function () {
        Route::get('/mi-cuenta/sesiones', [CuentaController::class, 'sesiones'])->name('cuenta.sesiones');
        Route::delete('/mi-cuenta/sesiones', [CuentaController::class, 'cerrarOtrasSesiones'])->name('cuenta.sesiones.cerrar-otras');
        Route::delete('/mi-cuenta/sesiones/{sesion}', [CuentaController::class, 'cerrarSesion'])->name('cuenta.sesiones.cerrar');
    });

    Route::middleware(['rol:postor', 'verified', 'clave.vigente'])->group(function () {
        Route::get('/mi-cuenta', [CuentaController::class, 'estado'])->name('cuenta.estado');
        Route::get('/mi-cuenta/documentos/{documento}', [CuentaController::class, 'documento'])->name('cuenta.documento');
    });
});

// Panel: administradores y martilleros. Sin sesión redirige a /admin/ingresar.
Route::prefix('admin')->name('admin.')->middleware(['rol:admin,martillero', 'clave.vigente'])->group(function () {
    Route::get('/', fn () => view('admin.dashboard', ['datos' => AdminDemo::dashboard()]))->name('dashboard');
    Route::get('/subastas', fn () => view('admin.subastas', ['subastas' => AdminDemo::subastas()]))->name('subastas');
    Route::get('/postores', fn () => view('admin.postores', ['postores' => AdminDemo::postores()]))->name('postores');
    Route::view('/reportes', 'admin.reportes')->name('reportes');

    Route::middleware('rol:admin')->group(function () {
        Route::post('/usuarios/{usuario}/restablecer-clave', [UsuariosController::class, 'restablecerClave'])->name('usuarios.restablecer-clave');
        Route::post('/usuarios/{usuario}/desbloquear', [UsuariosController::class, 'desbloquear'])->name('usuarios.desbloquear');
        Route::delete('/usuarios/{usuario}/sesiones', [UsuariosController::class, 'cerrarSesiones'])->name('usuarios.cerrar-sesiones');
        Route::get('/postores/{postor}/documentos/{documento}', [UsuariosController::class, 'documento'])
            ->whereNumber('documento')->name('postores.documento');
    });
});
