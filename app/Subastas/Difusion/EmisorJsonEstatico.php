<?php

namespace App\Subastas\Difusion;

use App\Models\Remate;
use App\Subastas\EstadoRemate;
use Illuminate\Support\Facades\File;
use RuntimeException;

/**
 * Publica el estado del remate como archivo JSON estático que LiteSpeed sirve sin ejecutar PHP.
 * Los navegadores lo consultan cada ~1 s.
 *
 * Orden correcto con escrituras concurrentes: el estado se CONSTRUYE Y ESCRIBE bajo un bloqueo de archivo por
 * remate, leyendo la base después del commit. Quien escribe último leyó la base último, así que el archivo
 * nunca retrocede. Escritura atómica: archivo temporal en la misma carpeta + rename.
 */
class EmisorJsonEstatico implements Emisor
{
    public function __construct(private readonly string $carpeta) {}

    public function publicarEstado(Remate $remate): void
    {
        File::ensureDirectoryExists($this->carpeta);
        $destino = $this->rutaDe($remate);

        $bloqueo = fopen($this->carpeta . DIRECTORY_SEPARATOR . '.bloqueo-' . $remate->slug, 'c');
        if ($bloqueo === false || ! flock($bloqueo, LOCK_EX)) {
            throw new RuntimeException("No se pudo bloquear la publicación de {$remate->slug}.");
        }

        try {
            $json = json_encode(EstadoRemate::construir($remate), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            $temporal = $destino . '.' . bin2hex(random_bytes(6)) . '.tmp';
            if (file_put_contents($temporal, $json) === false || ! rename($temporal, $destino)) {
                @unlink($temporal);
                throw new RuntimeException("No se pudo escribir el estado de {$remate->slug}.");
            }
        } finally {
            flock($bloqueo, LOCK_UN);
            fclose($bloqueo);
        }
    }

    public function rutaDe(Remate $remate): string
    {
        return $this->carpeta . DIRECTORY_SEPARATOR . $remate->slug . '.json';
    }
}
