<?php

namespace App\Support;

use App\Models\Configuracion;
use Illuminate\Contracts\Config\Repository;
use Throwable;

/**
 * Aplica el correo saliente configurado en el panel (Bloque V) sobre la configuración de Laravel. Se llama al crear el
 * gestor de correo (AppServiceProvider), no en cada petición: una puja no consulta nada de esto.
 * Modo «env» deja lo del .env tal cual; «smtp» usa los datos del panel; «log» no envía (queda en storage/logs).
 */
class CorreoSaliente
{
    public static function aplicar(Repository $config): void
    {
        try {
            $modo = Configuracion::valor('correo_modo');
            $remitente = Configuracion::valor('correo_remitente');
            $nombre = Configuracion::valor('correo_remitente_nombre');
        } catch (Throwable) {
            return; // sin base (instalación): se queda el .env
        }

        if ($modo === 'log') {
            $config->set('mail.default', 'log');
        } elseif ($modo === 'smtp' && filled(Configuracion::valor('smtp_host'))) {
            $cifrado = Configuracion::valor('smtp_cifrado');
            $config->set('mail.default', 'smtp');
            $config->set('mail.mailers.smtp', [
                'transport' => 'smtp',
                'scheme' => $cifrado === 'ssl' ? 'smtps' : 'smtp',
                'host' => Configuracion::valor('smtp_host'),
                'port' => (int) Configuracion::valor('smtp_puerto'),
                'username' => Configuracion::valor('smtp_usuario') ?: null,
                'password' => Configuracion::valor('smtp_clave'),
                'timeout' => 20,
                'auto_tls' => $cifrado !== 'ninguno',
                'local_domain' => parse_url((string) config('app.url'), PHP_URL_HOST),
            ]);
        }

        if (filled($remitente)) {
            $config->set('mail.from.address', $remitente);
        }
        if (filled($nombre)) {
            $config->set('mail.from.name', $nombre);
        }
    }
}
