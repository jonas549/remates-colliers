<?php

namespace App\Providers;

use App\Listeners\RegistroDeAccesos;
use App\Subastas\Difusion\Emisor;
use App\Subastas\Difusion\EmisorJsonEstatico;
use App\Support\CorreoSaliente;
use App\Support\UrlLocalizada;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Mail\MailManager;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Capa de difusión en tiempo real (Bloque J). Pusher sería otro `case`.
        $this->app->singleton(Emisor::class, fn () => match (config('colliers.tiempo_real.driver')) {
            'json' => new EmisorJsonEstatico(config('colliers.tiempo_real.carpeta')),
            default => throw new InvalidArgumentException('Driver de tiempo real desconocido: ' . config('colliers.tiempo_real.driver')),
        });

        // Sitio en inglés (20/10): el mismo generador de URL de Laravel, pero route() elige la ruta del idioma actual.
        // Los resolutores de sesión y de clave (URL firmadas) los agrega el extend() de RoutingServiceProvider.
        $this->app->singleton('url', function ($app) {
            $routes = $app['router']->getRoutes();
            $app->instance('routes', $routes);

            return new UrlLocalizada($routes, $app->rebinding('request', fn ($app, $request) => $app['url']->setRequest($request)), $app['config']['app.asset_url']);
        });

        // Correo saliente del panel (Bloque V): se aplica al crear el gestor de correo, solo cuando algo se envía.
        $this->app->resolving(MailManager::class, fn (MailManager $gestor, $app) => CorreoSaliente::aplicar($app['config']));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // QA 21/10, 2.1: con https forzado, todo enlace generado (también en correos desde la cola) sale en https.
        if (config('colliers.seguridad.forzar_https')) {
            URL::forceScheme('https');
        }

        Event::subscribe(RegistroDeAccesos::class);
        // Bloque M: correos por la cola y su bitácora.
        Event::subscribe(\App\Listeners\EnviarAvisos::class);
        Event::subscribe(\App\Listeners\RegistroDeNotificaciones::class);

        RateLimiter::for('pujas', fn (Request $request) => Limit::perMinute(config('colliers.limites.pujas_por_minuto'))
            ->by('puja:' . ($request->user()?->id ?? $request->ip())));

        RateLimiter::for('estado-remate', fn (Request $request) => Limit::perMinute(config('colliers.limites.estado_por_minuto'))
            ->by('estado:' . $request->ip()));

        RateLimiter::for('registro', fn (Request $request) => Limit::perHour(config('colliers.limites.registros_por_hora'))
            ->by('registro:' . $request->ip()));
    }
}
