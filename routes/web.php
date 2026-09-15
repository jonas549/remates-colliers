<?php

use App\Demo\EstadoCuentaDemo;
use App\Demo\PantallasRevision;
use App\Demo\RematesDemo;
use Illuminate\Support\Facades\Route;

/*
 * Bloque T: rutas definitivas con vistas alimentadas por datos de ejemplo (App\Demo).
 * Las rutas aún sin pantalla responden con un aviso temporal.
 *
 * Durante el Bloque T la raíz es el índice de revisión y el listado vive en /remates.
 * Al cerrar T, "/" pasa a ser el listado y el índice se elimina.
 */

$pendiente = fn (string $nombre) => fn () => response('Pantalla pendiente del Bloque T: ' . $nombre, 200);

Route::get('/', fn () => view('revision', ['grupos' => PantallasRevision::todas()]))->name('revision');

Route::get('/remates', $pendiente('listado'))->name('remates.index');
Route::get('/remates/{remate}', $pendiente('detalle de remate'))->name('remates.show');
Route::get('/remates/{remate}/sala', $pendiente('sala de puja'))->name('sala.show');

Route::get('/ingresar', fn () => view('auth.login', [
    'proximo' => RematesDemo::proximoDestacado(),
]))->name('login');

Route::view('/registro', 'auth.registro')->name('registro');

// ?estado= permite revisar cada variante del prototipo mientras no existe la sesión real.
Route::get('/mi-cuenta', fn () => view('cuenta.estado', [
    'estado' => EstadoCuentaDemo::para(request('estado')),
]))->name('cuenta.estado');

Route::get('/admin', $pendiente('dashboard'))->name('admin.dashboard');
