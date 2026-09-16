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
        $middleware->prepend(\App\Http\Middleware\HoraRecepcion::class);

        // Clave de acceso mientras el subdominio es un sandbox público (COLLIERS_ACCESO_CLAVE).
        $middleware->web(append: [\App\Http\Middleware\AccesoSandbox::class]);

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
    })->create();
