<?php

namespace App\Providers;

use App\Listeners\RegistroDeAccesos;
use App\Subastas\Difusion\Emisor;
use App\Subastas\Difusion\EmisorJsonEstatico;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
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
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Event::subscribe(RegistroDeAccesos::class);

        RateLimiter::for('pujas', fn (Request $request) => Limit::perMinute(config('colliers.limites.pujas_por_minuto'))
            ->by('puja:' . ($request->user()?->id ?? $request->ip())));

        RateLimiter::for('estado-remate', fn (Request $request) => Limit::perMinute(config('colliers.limites.estado_por_minuto'))
            ->by('estado:' . $request->ip()));
    }
}
