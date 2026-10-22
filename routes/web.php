<?php

use App\Demo\PantallasRevision;
use App\Http\Controllers\Admin\ConfiguracionController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DocumentosController;
use App\Http\Controllers\Admin\EnVivoController;
use App\Http\Controllers\Admin\LotesController;
use App\Http\Controllers\Admin\PostoresController;
use App\Http\Controllers\Admin\PlantillasCorreoController;
use App\Http\Controllers\Admin\RegistroCorreosController;
use App\Http\Controllers\Admin\RematesController;
use App\Http\Controllers\Admin\ReportesController;
use App\Http\Controllers\Admin\SalaMartilleroController;
use App\Http\Controllers\Admin\UsuariosController;
use App\Http\Controllers\CuentaController;
use App\Http\Controllers\IdiomaController;
use App\Http\Controllers\IngresarComoController;
use App\Http\Controllers\PublicoController;
use App\Http\Controllers\PujaController;
use App\Http\Controllers\RevisionController;
use App\Http\Controllers\SalaController;
use App\Http\Controllers\SuscripcionesController;
use App\Http\Controllers\TiempoRealController;
use App\Support\Idioma;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Http\Controllers\AuthenticatedSessionController;

/*
 * Rutas de la aplicación. La raíz es el listado de remates; /remates redirige a "/".
 * Las vistas del diseño (Bloque T) reciben datos reales desde los Bloques I a N.
 */

/*
 * Informes de la CSP (QA 21/10, 2.2): el navegador avisa aquí lo que la política bloqueó y queda en el log de Laravel.
 * Sin sesión ni CSRF (el navegador no manda token); acotado por minuto y por tamaño.
 */
Route::post('/csp-informe', function (\Illuminate\Http\Request $request) {
    $informe = json_decode(mb_substr((string) $request->getContent(), 0, 4000), true);
    $datos = $informe['csp-report'] ?? $informe ?? [];
    \Illuminate\Support\Facades\Log::warning('CSP bloqueó un recurso', array_map(fn ($v) => is_string($v) ? mb_substr($v, 0, 300) : $v, array_intersect_key(
        is_array($datos) ? $datos : [], array_flip(['document-uri', 'violated-directive', 'effective-directive', 'blocked-uri', 'source-file', 'line-number']),
    )));

    return response()->noContent();
})->middleware('throttle:30,1')->name('csp.informe');

/*
 * Bloque J: motor de pujas y tiempo real. Los espectadores leen /tiempo-real/{remate}.json (estático);
 * estas rutas ejecutan PHP solo para pujar, sincronizar el reloj, reconectar y cerrar.
 */
Route::get('/hora', [TiempoRealController::class, 'hora'])->name('tiempo-real.hora');
Route::get('/remates/{remate:slug}/estado', [TiempoRealController::class, 'estado'])
    ->middleware('throttle:estado-remate')->name('tiempo-real.estado');
Route::post('/admin/remates/{remate:slug}/lotes/{lote}/cerrar', [SalaMartilleroController::class, 'cerrarLote'])
    ->whereNumber('lote')->middleware(['auth', 'clave.vigente'])->name('admin.sala.cerrar-lote');
Route::post('/admin/remates/{remate:slug}/mensaje', [SalaMartilleroController::class, 'mensaje'])
    ->middleware(['auth', 'clave.vigente'])->name('admin.sala.mensaje');

// Índice de pantallas para revisar variantes: solo en el entorno local, nunca en el servidor.
if (app()->isLocal()) {
    Route::get('/revision', fn () => view('revision', ['grupos' => PantallasRevision::todas()]))->name('revision');
    // Entra como el primer usuario de un rol del seeder (pantallas protegidas y arnés visual).
    Route::get('/revision/entrar/{rol}', [RevisionController::class, 'entrar'])->name('revision.entrar');
}

/*
 * Sitio público (20/10): se registra dos veces, en español sin prefijo y en inglés bajo /en con los nombres «en.x».
 * UrlLocalizada elige la del idioma actual: route('remates.show') en una página en inglés ya da /en/remates/….
 * El panel no se traduce: sus rutas van fuera de esta función.
 */
$publicas = function (string $prefijo) {
    Route::post('/remates/{remate:slug}/lotes/{lote}/pujas', [PujaController::class, 'store'])
        ->whereNumber('lote')->middleware(['auth', 'clave.vigente', 'throttle:pujas'])->name('pujas.store');

    // Bloque M: «Avísame» (remates nuevos o recordatorio de un remate) y baja desde el enlace del correo.
    Route::post('/avisame', [SuscripcionesController::class, 'store'])->middleware('throttle:10,1')->name('suscripciones.store');
    Route::get('/avisame/baja/{token}', [SuscripcionesController::class, 'baja'])->where('token', '[A-Za-z0-9]{40}')->name('suscripciones.baja');

    // Bloque N: sitio público con datos reales. En local, ?sesion= fuerza la variante del visitante (arnés visual).
    Route::get('/', [PublicoController::class, 'index'])->name('remates.index');
    Route::permanentRedirect('/remates', $prefijo === '' ? '/' : '/' . $prefijo)->name('remates.antiguo');

    Route::get('/remates/{remate:slug}', [PublicoController::class, 'show'])->name('remates.show');
    Route::get('/remates/{remate:slug}/documentos/{documento}', [PublicoController::class, 'documento'])->whereNumber('documento')->name('remates.documento');
    Route::get('/remates/{remate:slug}/calendario.ics', [PublicoController::class, 'calendario'])->name('remates.calendario');
    // Bloque K: sala de puja conectada al motor. Solo postores con cuenta y garantía del remate aprobadas.
    Route::get('/remates/{remate:slug}/sala', [SalaController::class, 'show'])
        ->middleware(['auth', 'rol:postor', 'verified', 'clave.vigente'])->name('sala.show');

    // Bloque D: Fortify registra /ingresar, /registro, /salir, /recuperar-clave, /restablecer-clave y /verificar-correo
    // (config/fortify.php). Se cargan aquí, y no desde Fortify, para tener también su versión en inglés.
    Route::group(['namespace' => 'Laravel\Fortify\Http\Controllers'], fn () => require base_path('vendor/laravel/fortify/routes/routes.php'));

    // «Ingresar como este postor» (postores de prueba): el acceso con el correo ya escrito, aunque haya otra sesión abierta.
    Route::get('/ingresar/como', [IngresarComoController::class, 'show'])->name('ingresar.como');
    Route::post('/ingresar/como', [IngresarComoController::class, 'cambiar'])->middleware('auth')->name('ingresar.como.cambiar');

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
};
$publicas('');
Route::prefix(Idioma::PREFIJO)->name(Idioma::PREFIJO . '.')->group(fn () => $publicas(Idioma::PREFIJO));

// Conmutador EN / ES del encabezado: guarda la elección y vuelve a la misma página en el otro idioma.
Route::get('/idioma/{idioma}', IdiomaController::class)->whereIn('idioma', Idioma::TODOS)->name('idioma.cambiar');

// Acceso separado de administración (solo en español, como el panel).
Route::middleware('guest')->group(function () {
    Route::get('/admin/ingresar', fn () => view('auth.login', ['proximo' => \App\Publico\Catalogo::destacado(), 'portal' => 'administracion']))
        ->name('admin.ingresar');
    Route::post('/admin/ingresar', [AuthenticatedSessionController::class, 'store'])
        ->middleware('throttle:login')->name('admin.ingresar.store');
});

// Panel: administración, martilleros y editores. Sin sesión redirige a /admin/ingresar.
// Cada grupo pide un permiso de App\Autenticacion\Permisos (22/10): ahí se ajusta qué puede cada rol.
Route::prefix('admin')->name('admin.')->middleware(['permiso:panel', 'clave.vigente'])->group(function () {
    // El editor no tiene dashboard: el controlador lo lleva a «Subastas».
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::middleware('permiso:remates.ver')->group(function () {
        Route::get('/subastas', [RematesController::class, 'index'])->name('subastas');
        // Ficha: administración y edición la editan; el martillero del remate la ve en modo lectura (se revisa en el controlador).
        Route::get('/subastas/{remate}', [RematesController::class, 'show'])->name('remates.show');
    });
    Route::middleware('permiso:en_vivo')->group(function () {
        // Panel del martillero: administradores y el martillero asignado a ese remate (se revisa en el controlador).
        Route::get('/subastas/{remate}/en-vivo', [EnVivoController::class, 'show'])->name('remates.en-vivo');
        // Quién está en la sala, con nombre (solo el panel; el JSON público lleva solo el número).
        Route::get('/subastas/{remate}/presencia', [EnVivoController::class, 'presencia'])->name('remates.presencia');
    });
    // Bloque O: reportes con datos reales y exportables (sin datos personales).
    Route::middleware('permiso:reportes')->group(function () {
        Route::get('/reportes', [ReportesController::class, 'index'])->name('reportes');
        Route::get('/reportes/exportar/{hoja}.{formato}', [ReportesController::class, 'exportar'])
            ->whereIn('hoja', ['libro', 'desempeno', 'participacion', 'pujas', 'garantias'])->whereIn('formato', ['xlsx', 'csv'])->name('reportes.exportar');
    });

    // Bloque I: remates y lotes (administración y edición).
    Route::middleware('permiso:remates.editar')->group(function () {
        Route::post('/subastas', [RematesController::class, 'store'])->name('remates.store');
        Route::put('/subastas/{remate}', [RematesController::class, 'update'])->name('remates.update');
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
    });
    Route::middleware('permiso:remates.publicar')->group(function () {
        Route::post('/subastas/{remate}/publicar', [RematesController::class, 'publicar'])->name('remates.publicar');
        Route::post('/subastas/{remate}/despublicar', [RematesController::class, 'despublicar'])->name('remates.despublicar');
    });
    Route::middleware('permiso:remates.cerrar')->group(function () {
        Route::post('/subastas/{remate}/cancelar', [RematesController::class, 'cancelar'])->name('remates.cancelar');
        Route::post('/subastas/{remate}/cerrar-ahora', [RematesController::class, 'cerrarAhora'])->name('remates.cerrar-ahora');
    });
    // Subasta de prueba lista para usar (fuera de producción): el servicio se niega si APP_ENV=production.
    Route::middleware('permiso:remates.prueba')->group(function () {
        Route::post('/subastas/prueba', [RematesController::class, 'crearPrueba'])->name('remates.crear-prueba');
        Route::delete('/subastas/{remate}/prueba', [RematesController::class, 'borrarPrueba'])->name('remates.borrar-prueba');
        Route::post('/subastas/{remate}/prueba/clave', [RematesController::class, 'nuevaClavePrueba'])->name('remates.prueba-clave');
    });

    // Bloque V: configuración. Cada sección pide su permiso en el controlador (Sitio también es del editor).
    Route::get('/configuracion', [ConfiguracionController::class, 'index'])->name('configuracion');
    Route::post('/configuracion/actualizar-uf', [ConfiguracionController::class, 'actualizarUf'])->name('configuracion.actualizar-uf');

    Route::middleware('permiso:postores')->group(function () {
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
        Route::get('/postores/{postor}/documentos/{documento}', [UsuariosController::class, 'documento'])
            ->whereNumber('documento')->name('postores.documento');
    });

    Route::middleware('permiso:configuracion')->group(function () {
        // Bloque V: una pantalla por tema, con submenú (17/09).
        Route::post('/configuracion/probar-correo', [ConfiguracionController::class, 'probarCorreo'])->name('configuracion.probar-correo');
        Route::post('/configuracion/probar-conexion', [ConfiguracionController::class, 'probarConexion'])->name('configuracion.probar-conexion');
        // Editor de plantillas (va antes de {seccion} para que «plantillas/x» no se confunda con una sección).
        Route::get('/configuracion/plantillas/{clave}', [PlantillasCorreoController::class, 'edit'])->name('configuracion.plantilla');
        Route::put('/configuracion/plantillas/{clave}', [PlantillasCorreoController::class, 'update'])->name('configuracion.plantilla.update');
        // PUT: el formulario del editor ya viaja con method spoofing; así el botón de vista previa comparte sus campos.
        Route::put('/configuracion/plantillas/{clave}/vista-previa', [PlantillasCorreoController::class, 'previsualizar'])->name('configuracion.plantilla.previa');
        Route::delete('/configuracion/plantillas/{clave}', [PlantillasCorreoController::class, 'destroy'])->name('configuracion.plantilla.restaurar');
        Route::get('/configuracion/correos/exportar', [RegistroCorreosController::class, 'exportar'])->name('configuracion.correos.exportar');
    });
    // Después de las rutas fijas de configuración, para que «plantillas/x» o «correos/exportar» no se lean como sección.
    Route::get('/configuracion/{seccion}', [ConfiguracionController::class, 'show'])->name('configuracion.seccion');
    Route::put('/configuracion/{seccion}', [ConfiguracionController::class, 'update'])->name('configuracion.update');

    Route::middleware('permiso:usuarios')->group(function () {
        // Bloque E/F: usuarios internos (administración, martilleros y editores). Los postores se administran en «03 Postores».
        Route::get('/usuarios', [UsuariosController::class, 'index'])->name('usuarios');
        Route::post('/usuarios', [UsuariosController::class, 'store'])->name('usuarios.store');
        Route::put('/usuarios/{usuario}', [UsuariosController::class, 'update'])->name('usuarios.update');
        Route::post('/usuarios/{usuario}/estado', [UsuariosController::class, 'estado'])->name('usuarios.estado');
        Route::post('/usuarios/{usuario}/restablecer-clave', [UsuariosController::class, 'restablecerClave'])->name('usuarios.restablecer-clave');
        Route::post('/usuarios/{usuario}/desbloquear', [UsuariosController::class, 'desbloquear'])->name('usuarios.desbloquear');
        Route::delete('/usuarios/{usuario}/sesiones', [UsuariosController::class, 'cerrarSesiones'])->name('usuarios.cerrar-sesiones');
    });
});
