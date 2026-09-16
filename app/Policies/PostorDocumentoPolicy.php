<?php

namespace App\Policies;

use App\Models\PostorDocumento;
use App\Models\User;

/** Documentos del registro (cédula, domicilio, poder): solo su dueño y la administración de Colliers. */
class PostorDocumentoPolicy
{
    public function descargar(User $user, PostorDocumento $documento): bool
    {
        if ($user->estado !== User::ESTADO_ACTIVO) {
            return false;
        }

        return $user->rol === User::ROL_ADMIN || $documento->postor()->where('user_id', $user->id)->exists();
    }
}
