<?php

use App\Demo\AdminDemo;
use App\Demo\DetalleDemo;
use App\Demo\EstadoCuentaDemo;
use App\Demo\PantallasRevision;
use App\Demo\RematesDemo;
use App\Http\Controllers\AccesoSandboxController;
use Illuminate\Support\Facades\Route;

/*
 * Bloque T: rutas definitivas con vistas alimentadas por datos de ejemplo (App\Demo).
 * Las rutas aún sin pantalla responden con un aviso temporal.
 *
 * La raíz es el listado de remates; /remates redirige a "/".
 */

// Clave de acceso al sandbox (ver App\Http\Middleware\AccesoSandbox).
Route::get('/acceso', [AccesoSandboxController::class, 'formulario'])->name('acceso.formulario');
Route::post('/acceso', [AccesoSandboxController::class, 'ingresar'])->name('acceso.ingresar');

$pendiente = fn (string $nombre) => fn () => response('Pantalla pendiente del Bloque T: ' . $nombre, 200);

// ?sesion= (visitante | registrado | en-revision | aprobada) simula la sesión hasta el Bloque D.
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
}
// Demo: 'apoquindo' es el remate en vivo; cualquier otro muestra la ficha de 'militares' (próximo).
Route::get('/remates/{remate}', fn (string $remate) => view('remates.show', [
    'r' => DetalleDemo::para($remate) ?? DetalleDemo::para('militares'),
    'sesion' => $sesionDemo(),
]))->name('remates.show');
Route::get('/remates/{remate}/sala', fn () => view('sala.show'))->name('sala.show');

Route::get('/ingresar', fn () => view('auth.login', [
    'proximo' => RematesDemo::proximoDestacado(),
]))->name('login');

Route::view('/registro', 'auth.registro')->name('registro');

// ?estado= permite revisar cada variante del prototipo mientras no existe la sesión real.
Route::get('/mi-cuenta', fn () => view('cuenta.estado', [
    'estado' => EstadoCuentaDemo::para(request('estado')),
]))->name('cuenta.estado');

Route::prefix('admin')->name('admin.')->group(function () use ($pendiente) {
    Route::get('/', fn () => view('admin.dashboard', ['datos' => AdminDemo::dashboard()]))->name('dashboard');
    Route::get('/subastas', fn () => view('admin.subastas', ['subastas' => AdminDemo::subastas()]))->name('subastas');
    Route::get('/postores', fn () => view('admin.postores', ['postores' => AdminDemo::postores()]))->name('postores');
    Route::view('/reportes', 'admin.reportes')->name('reportes');
});
