<?php

use App\Demo\PantallasRevision;
use App\Http\Controllers\AccesoSandboxController;
use App\Http\Controllers\Admin\ConfiguracionController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DocumentosController;
use App\Http\Controllers\Admin\EnVivoController;
use App\Http\Controllers\Admin\LotesController;
use App\Http\Controllers\Admin\PostoresController;
use App\Http\Controllers\Admin\PlantillasCorreoController;
use App\Http\Controllers\Admin\RematesController;
use App\Http\Controllers\Admin\ReportesController;
use App\Http\Controllers\Admin\SalaMartilleroController;
use App\Http\Controllers\Admin\UsuariosController;
use App\Http\Controllers\CuentaController;
use App\Http\Controllers\PublicoController;
use App\Http\Controllers\PujaController;
use App\Http\Controllers\RevisionController;
use App\Http\Controllers\SalaController;
use App\Http\Controllers\SuscripcionesController;
use App\Http\Controllers\TiempoRealController;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Http\Controllers\AuthenticatedSessionController;

/*
 * Rutas de la aplicación. La raíz es el listado de remates; /remates redirige a "/".
 * Las vistas del diseño (Bloque T) reciben datos reales desde los Bloques I a N.
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

// Bloque M: «Avísame» (remates nuevos o recordatorio de un remate) y baja desde el enlace del correo.
Route::post('/avisame', [SuscripcionesController::class, 'store'])->middleware('throttle:10,1')->name('suscripciones.store');
Route::get('/avisame/baja/{token}', [SuscripcionesController::class, 'baja'])->where('token', '[A-Za-z0-9]{40}')->name('suscripciones.baja');

// Bloque N: sitio público con datos reales. En local, ?sesion= fuerza la variante del visitante (arnés visual).
Route::get('/', [PublicoController::class, 'index'])->name('remates.index');
Route::permanentRedirect('/remates', '/');

// Índice de pantallas para revisar variantes: solo en el entorno local, nunca en el servidor.
if (app()->isLocal()) {
    Route::get('/revision', fn () => view('revision', ['grupos' => PantallasRevision::todas()]))->name('revision');
    // Entra como el primer usuario de un rol del seeder (pantallas protegidas y arnés visual).
    Route::get('/revision/entrar/{rol}', [RevisionController::class, 'entrar'])->name('revision.entrar');
}
Route::get('/remates/{remate:slug}', [PublicoController::class, 'show'])->name('remates.show');
Route::get('/remates/{remate:slug}/documentos/{documento}', [PublicoController::class, 'documento'])->whereNumber('documento')->name('remates.documento');
Route::get('/remates/{remate:slug}/calendario.ics', [PublicoController::class, 'calendario'])->name('remates.calendario');
// Bloque K: sala de puja conectada al motor. Solo postores con cuenta y garantía del remate aprobadas.
Route::get('/remates/{remate:slug}/sala', [SalaController::class, 'show'])
    ->middleware(['auth', 'rol:postor', 'verified', 'clave.vigente'])->name('sala.show');

/*
 * Bloque D: autenticación. Fortify registra /ingresar, /registro, /salir, /recuperar-clave, /restablecer-clave y
 * /verificar-correo (config/fortify.php). Aquí: el acceso separado de administración y el área de la cuenta.
 */
Route::middleware('guest')->group(function () {
    Route::get('/admin/ingresar', fn () => view('auth.login', ['proximo' => \App\Publico\Catalogo::destacado(), 'portal' => 'administracion']))
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
        // Bloque H: inscripción en un remate y comprobante de la garantía (sin pasarela de pago).
        Route::post('/mi-cuenta/remates/{remate:slug}/inscribirme', [CuentaController::class, 'inscribirme'])->name('cuenta.inscribirme');
        Route::post('/mi-cuenta/garantias/{garantia}/comprobante', [CuentaController::class, 'subirComprobante'])->name('cuenta.comprobante.store');
        Route::get('/mi-cuenta/garantias/{garantia}/comprobante', [CuentaController::class, 'comprobante'])->name('cuenta.comprobante');
    });
});

// Panel: administradores y martilleros. Sin sesión redirige a /admin/ingresar.
Route::prefix('admin')->name('admin.')->middleware(['rol:admin,martillero', 'clave.vigente'])->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/subastas', [RematesController::class, 'index'])->name('subastas');
    // Panel del martillero: administradores y el martillero asignado a ese remate (se revisa en el controlador).
    Route::get('/subastas/{remate}/en-vivo', [EnVivoController::class, 'show'])->name('remates.en-vivo');
    // Bloque O: reportes con datos reales y exportables (sin datos personales).
    Route::get('/reportes', [ReportesController::class, 'index'])->name('reportes');
    Route::get('/reportes/exportar/{hoja}.{formato}', [ReportesController::class, 'exportar'])
        ->whereIn('hoja', ['libro', 'desempeno', 'participacion', 'pujas', 'garantias'])->whereIn('formato', ['xlsx', 'csv'])->name('reportes.exportar');

    Route::middleware('rol:admin')->group(function () {
        // Bloques G y H: postores y garantías (datos personales: solo administradores).
        Route::get('/postores', [PostoresController::class, 'index'])->name('postores');
        Route::get('/postores/exportar', [PostoresController::class, 'exportar'])->name('postores.exportar');
        Route::post('/postores/{postor}/aprobar', [PostoresController::class, 'aprobarCuenta'])->name('postores.aprobar');
        Route::post('/postores/{postor}/rechazar', [PostoresController::class, 'rechazarCuenta'])->name('postores.rechazar');
        Route::post('/postores/{postor}/bloquear', [PostoresController::class, 'bloquear'])->name('postores.bloquear');
        Route::post('/postores/{postor}/desbloquear', [PostoresController::class, 'desbloquear'])->name('postores.desbloquear');
        Route::post('/garantias/{garantia}/aprobar', [PostoresController::class, 'aprobarGarantia'])->name('garantias.aprobar');
        Route::post('/garantias/{garantia}/rechazar', [PostoresController::class, 'rechazarGarantia'])->name('garantias.rechazar');
        Route::get('/garantias/{garantia}/comprobante', [PostoresController::class, 'comprobante'])->name('garantias.comprobante');

        // Bloque I: remates y lotes.
        Route::post('/subastas', [RematesController::class, 'store'])->name('remates.store');
        Route::get('/subastas/{remate}', [RematesController::class, 'show'])->name('remates.show');
        Route::put('/subastas/{remate}', [RematesController::class, 'update'])->name('remates.update');
        Route::post('/subastas/{remate}/publicar', [RematesController::class, 'publicar'])->name('remates.publicar');
        Route::post('/subastas/{remate}/cancelar', [RematesController::class, 'cancelar'])->name('remates.cancelar');
        Route::post('/subastas/{remate}/cerrar-ahora', [RematesController::class, 'cerrarAhora'])->name('remates.cerrar-ahora');
        Route::post('/subastas/{remate}/republicar', [RematesController::class, 'republicar'])->name('remates.republicar');
        Route::get('/subastas/{remate}/lotes/nuevo', [LotesController::class, 'create'])->name('lotes.create');
        Route::post('/subastas/{remate}/lotes', [LotesController::class, 'store'])->name('lotes.store');
        Route::get('/subastas/{remate}/lotes/{lote}', [LotesController::class, 'edit'])->name('lotes.edit');
        Route::put('/subastas/{remate}/lotes/{lote}', [LotesController::class, 'update'])->name('lotes.update');
        Route::post('/subastas/{remate}/lotes/{lote}/mover', [LotesController::class, 'mover'])->name('lotes.mover');
        Route::post('/subastas/{remate}/lotes/{lote}/imagenes',[LotesController::class, 'subirImagenes'])->name('lotes.imagenes.store');
        Route::post('/subastas/{remate}/lotes/{lote}/imagenes/{imagen}/portada', [LotesController::class, 'portada'])->name('lotes.imagenes.portada');
        Route::delete('/subastas/{remate}/lotes/{lote}/imagenes/{imagen}', [LotesController::class, 'borrarImagen'])->name('lotes.imagenes.destroy');
        Route::post('/subastas/{remate}/lotes/{lote}/visitas', [LotesController::class, 'agregarVisita'])->name('lotes.visitas.store');
        Route::delete('/subastas/{remate}/lotes/{lote}/visitas/{visita}', [LotesController::class, 'borrarVisita'])->whereNumber('visita')->name('lotes.visitas.destroy');
        Route::post('/subastas/{remate}/documentos', [DocumentosController::class, 'store'])->name('documentos.store');
        Route::get('/subastas/{remate}/documentos/{documento}', [DocumentosController::class, 'descargar'])->name('documentos.descargar');
        Route::delete('/subastas/{remate}/documentos/{documento}', [DocumentosController::class, 'destroy'])->name('documentos.destroy');

        // Bloque V: configuración autoadministrable.
        // Bloque V: una pantalla por tema, con submenú (17/09).
        Route::get('/configuracion', [ConfiguracionController::class, 'index'])->name('configuracion');
        Route::post('/configuracion/probar-correo', [ConfiguracionController::class, 'probarCorreo'])->name('configuracion.probar-correo');
        Route::post('/configuracion/probar-conexion', [ConfiguracionController::class, 'probarConexion'])->name('configuracion.probar-conexion');
        Route::post('/configuracion/actualizar-uf', [ConfiguracionController::class, 'actualizarUf'])->name('configuracion.actualizar-uf');
        // Editor de plantillas (va antes de {seccion} para que «plantillas/x» no se confunda con una sección).
        Route::get('/configuracion/plantillas/{clave}', [PlantillasCorreoController::class, 'edit'])->name('configuracion.plantilla');
        Route::put('/configuracion/plantillas/{clave}', [PlantillasCorreoController::class, 'update'])->name('configuracion.plantilla.update');
        // PUT: el formulario del editor ya viaja con method spoofing; así el botón de vista previa comparte sus campos.
        Route::put('/configuracion/plantillas/{clave}/vista-previa', [PlantillasCorreoController::class, 'previsualizar'])->name('configuracion.plantilla.previa');
        Route::delete('/configuracion/plantillas/{clave}', [PlantillasCorreoController::class, 'destroy'])->name('configuracion.plantilla.restaurar');
        Route::get('/configuracion/{seccion}', [ConfiguracionController::class, 'show'])->name('configuracion.seccion');
        Route::put('/configuracion/{seccion}', [ConfiguracionController::class, 'update'])->name('configuracion.update');

        Route::post('/usuarios/{usuario}/restablecer-clave', [UsuariosController::class, 'restablecerClave'])->name('usuarios.restablecer-clave');
        Route::post('/usuarios/{usuario}/desbloquear', [UsuariosController::class, 'desbloquear'])->name('usuarios.desbloquear');
        Route::delete('/usuarios/{usuario}/sesiones', [UsuariosController::class, 'cerrarSesiones'])->name('usuarios.cerrar-sesiones');
        Route::get('/postores/{postor}/documentos/{documento}', [UsuariosController::class, 'documento'])
            ->whereNumber('documento')->name('postores.documento');
    });
});
