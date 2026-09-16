<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * SOLO LOCAL (la ruta se registra únicamente con APP_ENV=local, igual que /revision): entra como el primer usuario
 * de un rol del seeder de desarrollo, para revisar pantallas protegidas y para el arnés de comparación visual.
 */
class RevisionController extends Controller
{
    public function entrar(Request $request, string $rol): RedirectResponse
    {
        abort_unless(app()->isLocal(), 404);

        $user = User::where('rol', $rol)->where('estado', User::ESTADO_ACTIVO)
            ->when($rol === User::ROL_POSTOR, fn ($q) => $q->whereNotNull('email_verified_at'))
            ->orderBy('id')->first();
        abort_if($user === null, 404, "No hay usuarios con rol {$rol}: corre php artisan migrate:fresh --seed");

        Auth::login($user);
        $request->session()->regenerate();

        $destino = (string) $request->query('a', '');
        $interno = str_starts_with($destino, '/') && ! str_starts_with($destino, '//');

        return redirect($interno ? $destino : ($user->esAdministracion() ? route('admin.dashboard') : route('cuenta.estado')));
    }
}
