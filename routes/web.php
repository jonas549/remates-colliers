<?php

use App\Demo\RematesDemo;
use Illuminate\Support\Facades\Route;

/*
 * Bloque T: rutas definitivas con vistas alimentadas por datos de ejemplo (App\Demo).
 * Las rutas aún sin pantalla responden con un aviso temporal.
 */

$pendiente = fn (string $nombre) => fn () => response('Pantalla pendiente del Bloque T: ' . $nombre, 200);

Route::get('/', $pendiente('listado'))->name('remates.index');

Route::get('/ingresar', fn () => view('auth.login', [
    'proximo' => RematesDemo::proximoDestacado(),
]))->name('login');

Route::view('/registro', 'auth.registro')->name('registro');
Route::get('/mi-cuenta', $pendiente('estado de cuenta'))->name('cuenta.estado');
Route::get('/admin', $pendiente('dashboard'))->name('admin.dashboard');
