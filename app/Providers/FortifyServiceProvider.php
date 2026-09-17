<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Actions\Fortify\UpdateUserPassword;
use App\Autenticacion\AutenticarUsuario;
use App\Correo\Plantillas;
use App\Http\Middleware\SesionTrasIngreso;
use App\Publico\Catalogo;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\LoginResponse;
use Laravel\Fortify\Contracts\RegisterResponse;
use Laravel\Fortify\Fortify;

/**
 * Autenticación (Bloque D) con Fortify y las pantallas del diseño. Las pantallas que el diseño no tiene reutilizan
 * el diseño del Login (decisión del 16/09).
 */
class FortifyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Después de ingresar: administración al panel, postores a su cuenta.
        $this->app->instance(LoginResponse::class, new class implements LoginResponse
        {
            public function toResponse($request)
            {
                $destino = $request->user()?->esAdministracion() ? route('admin.dashboard') : route('cuenta.estado');
                if ($request->wantsJson()) {
                    return response()->json(['two_factor' => false]);
                }
                $redirect = redirect()->intended($destino);

                // Marca para detectar una sesión que el navegador no conserva (SesionTrasIngreso).
                return $redirect->setTargetUrl(SesionTrasIngreso::marcar($redirect->getTargetUrl()));
            }
        });

        $this->app->instance(RegisterResponse::class, new class implements RegisterResponse
        {
            public function toResponse($request)
            {
                return $request->wantsJson() ? response()->json([], 201) : redirect()->to(SesionTrasIngreso::marcar(route('verification.notice')));
            }
        });
    }

    public function boot(): void
    {
        Fortify::createUsersUsing(CreateNewUser::class);
        Fortify::updateUserPasswordsUsing(UpdateUserPassword::class);
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        Fortify::authenticateUsing(fn (Request $request) => app(AutenticarUsuario::class)($request));

        Fortify::loginView(fn () => view('auth.login', ['proximo' => Catalogo::destacado(), 'portal' => 'postores']));
        Fortify::registerView(fn () => view('auth.registro'));
        Fortify::requestPasswordResetLinkView(fn () => view('auth.recuperar-clave', ['proximo' => Catalogo::destacado()]));
        Fortify::resetPasswordView(fn (Request $request) => view('auth.restablecer-clave', ['proximo' => Catalogo::destacado(), 'request' => $request]));
        Fortify::verifyEmailView(fn () => view('auth.verificar-correo', ['proximo' => Catalogo::destacado()]));
        Fortify::confirmPasswordView(fn () => view('auth.confirmar-clave', ['proximo' => Catalogo::destacado()]));

        // Protección por IP y usuario. El bloqueo de la CUENTA tras 5 intentos va en la tabla users (AutenticarUsuario).
        RateLimiter::for('login', function (Request $request) {
            $clave = Str::transliterate(Str::lower((string) $request->input(Fortify::username())) . '|' . $request->ip());

            return [Limit::perMinute(10)->by($clave), Limit::perMinute(30)->by('ip:' . $request->ip())];
        });

        VerifyEmail::toMailUsing(fn (User $user, string $url) => Plantillas::correo('bienvenida', $user->name, $url));

        ResetPassword::toMailUsing(fn (User $user, string $token) => Plantillas::correo(
            'restablecer_clave',
            $user->name,
            url(route('password.reset', ['token' => $token, 'email' => $user->getEmailForPasswordReset()], false)),
            ['minutos' => config('auth.passwords.users.expire')],
        ));
    }
}
