<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Hora de recepción de cada petición, antes de cualquier otro trabajo (validez de pujas por recepción).
        // Después, solo en la puja: abrir la conexión a la base con reintento, antes que la sesión (la hora ya quedó sellada).
        $middleware->prepend([\App\Http\Middleware\HoraRecepcion::class, \App\Http\Middleware\ConexionPujaConReintento::class]);
        // Idioma por el prefijo de la URL (/en). Global, para que también los errores sin ruta salgan en ese idioma.
        $middleware->append(\App\Http\Middleware\EstablecerIdioma::class);
        // La cookie del conmutador solo dice «es» o «en»: se lee antes de la sesión, sin cifrar.
        $middleware->encryptCookies(except: [\App\Support\Idioma::COOKIE]);

        // Fuera de producción, noindex. El sandbox es de acceso libre: la clave de acceso se quitó el 21/09.
        $middleware->web(append: [\App\Http\Middleware\NoIndexar::class, \App\Http\Middleware\SesionTrasIngreso::class]);
        // Duración de la sesión desde el panel: tiene que aplicarse antes de que StartSession lea session.lifetime.
        // SubidaDemasiadoGrande va antes del CSRF: si PHP descartó el cuerpo por tamaño, no hay token que verificar.
        $middleware->web(prepend: [\App\Http\Middleware\SubidaDemasiadoGrande::class, \App\Http\Middleware\DuracionSesion::class]);
        // La marca de ingreso se revisa antes de `auth`, para que no redirija sin decir nada.
        $middleware->prependToPriorityList(\Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests::class, \App\Http\Middleware\SesionTrasIngreso::class);

        // Bloque D: rol y cambio de contraseña obligatorio.
        $middleware->alias([
            'rol' => \App\Http\Middleware\Rol::class,
            'clave.vigente' => \App\Http\Middleware\ClaveVigente::class,
        ]);
        // Sin sesión, el panel lleva al acceso de administración; el resto, al de postores.
        $middleware->redirectGuestsTo(fn (Request $request) => $request->is('admin', 'admin/*') ? route('admin.ingresar') : route('login'));
        $middleware->redirectUsersTo(fn (Request $request) => $request->user()?->esAdministracion() ? route('admin.dashboard') : route('cuenta.estado'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
        // Sesión vencida en una acción por fetch (puja, panel): mensaje que las pantallas muestran, en el idioma de la URL.
        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\HttpException $e, Request $request) {
            if ($e->getStatusCode() === 419 && $request->expectsJson()) {
                $mensaje = __('errores.419.json');

                return response()->json(['message' => $mensaje, 'mensaje' => $mensaje], 419);
            }
        });
    })->create();
