<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Código de ingreso al panel (segundo factor, 22/10). Se envía con notifyNow(), fuera de la cola: quien ingresa está
 * esperando y, si el correo falla, la pantalla tiene que decirlo en ese momento. El asunto no lleva el código: la
 * bitácora de correos guarda el asunto.
 */
class CodigoIngresoAviso extends AvisoColliers
{
    public function __construct(public User $user, public string $codigo, public int $minutos) {}

    public function plantilla(): string
    {
        return 'codigo_ingreso';
    }

    public function notificable(): ?Model
    {
        return $this->user;
    }

    protected function datos(?object $destinatario = null): array
    {
        return ['codigo' => $this->codigo, 'minutos' => $this->minutos];
    }

    /** La plantilla se edita en el panel: si alguien quitó {{ codigo }}, el código va igual al final. */
    protected function pie(\Illuminate\Notifications\Messages\MailMessage $correo, object $destinatario): \Illuminate\Notifications\Messages\MailMessage
    {
        $texto = implode("\n", array_map('strval', [...$correo->introLines, ...$correo->outroLines]));

        return str_contains($texto, $this->codigo) ? $correo : $correo->line("Código: {$this->codigo}");
    }
}
