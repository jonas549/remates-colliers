<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Actions\Fortify\UpdateUserPassword;
use App\Autenticacion\AutenticarUsuario;
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

                return $request->wantsJson() ? response()->json(['two_factor' => false]) : redirect()->intended($destino);
            }
        });

        $this->app->instance(RegisterResponse::class, new class implements RegisterResponse
        {
            public function toResponse($request)
            {
                return $request->wantsJson() ? response()->json([], 201) : redirect()->route('verification.notice');
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

        VerifyEmail::toMailUsing(fn (User $user, string $url) => (new MailMessage)
            ->subject('Confirma tu correo · Remates Colliers')
            ->greeting('Hola ' . $user->name)
            ->line('Recibimos tu registro como postor. Confirma tu correo para que Colliers revise tus antecedentes.')
            ->action('Confirmar mi correo', $url)
            ->line('Si no te registraste en Remates Colliers, ignora este mensaje.')
            ->salutation('Colliers Chile'));

        ResetPassword::toMailUsing(fn (User $user, string $token) => (new MailMessage)
            ->subject('Restablecer tu contraseña · Remates Colliers')
            ->greeting('Hola ' . $user->name)
            ->line('Pediste restablecer la contraseña de tu cuenta.')
            ->action('Crear una contraseña nueva', url(route('password.reset', ['token' => $token, 'email' => $user->getEmailForPasswordReset()], false)))
            ->line('El enlace vence en ' . config('auth.passwords.users.expire') . ' minutos. Si no lo pediste, ignora este mensaje: tu contraseña no cambia.')
            ->salutation('Colliers Chile'));
    }
}
