<?php

namespace App\Http\Middleware;

use App\Subastas\ConexionPuja;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware GLOBAL que abre la conexión a la base con reintento solo en `POST /remates/{remate}/lotes/{lote}/pujas` (y su versión /en).
 *
 * Es global y no de ruta a propósito: la primera consulta de una puja no es del motor sino de la sesión (y antes, de
 * DuracionSesion), que corren en el grupo `web` antes que cualquier middleware de la ruta. Abriendo la conexión aquí,
 * todo lo que sigue usa la misma. En el resto del sitio no se reintenta: durante una sobrecarga, cada página esperando
 * ocuparía un proceso y empeoraría la saturación.
 */
class ConexionPujaConReintento
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethod('POST') && preg_match('#^(en/)?remates/[^/]+/lotes/\d+/pujas$#', $request->path())) {
            app(ConexionPuja::class)->abrir();
        }

        return $next($request);
    }
}
