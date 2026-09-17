<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Fotos subidas desde el panel: se guardan en el disco público reducidas a un máximo de 1920 px por lado, en JPEG.
 * Las fotos originales de cámara (6000 px) tumbaban el servidor local y pesan demasiado para el hosting compartido.
 *
 * GD descomprime la foto entera en memoria (~5 bytes por píxel): una de 24 MP necesita ~120 MB. Antes de abrirla se
 * calcula lo necesario y se pide más memoria si el límite lo permite; si no alcanza, se rechaza con un mensaje en vez
 * de dejar que PHP muera a mitad de la petición.
 */
class Imagenes
{
    public const LADO_MAXIMO = 1920;

    private const BYTES_POR_PIXEL = 5.5;

    /** Devuelve la ruta relativa al disco público. @throws RuntimeException si la foto no cabe en la memoria disponible. */
    public static function guardar(UploadedFile $archivo, string $carpeta): string
    {
        $info = @getimagesize($archivo->getRealPath());
        if ($info === false) {
            throw new RuntimeException('No pudimos leer «' . $archivo->getClientOriginalName() . '» como imagen.');
        }
        [$ancho, $alto] = $info;
        $escala = min(1, self::LADO_MAXIMO / max($ancho, $alto, 1));
        [$anchoFinal, $altoFinal] = [(int) round($ancho * $escala), (int) round($alto * $escala)];

        $necesario = (int) (($ancho * $alto + $anchoFinal * $altoFinal) * self::BYTES_POR_PIXEL) + $archivo->getSize() + 8 * 1024 * 1024;
        if (! self::asegurarMemoria($necesario)) {
            throw new RuntimeException(sprintf('La foto «%s» es demasiado grande (%d × %d px) para procesarla en este servidor. Redúcela a unos 4000 px de lado y vuelve a subirla.',
                $archivo->getClientOriginalName(), $ancho, $alto));
        }

        $origen = @imagecreatefromstring((string) file_get_contents($archivo->getRealPath()));
        if ($origen === false) {
            throw new RuntimeException('No pudimos leer «' . $archivo->getClientOriginalName() . '» como imagen.');
        }
        $destino = imagecreatetruecolor($anchoFinal, $altoFinal);
        imagefill($destino, 0, 0, imagecolorallocate($destino, 255, 255, 255));
        imagecopyresampled($destino, $origen, 0, 0, 0, 0, $anchoFinal, $altoFinal, $ancho, $alto);
        imagedestroy($origen);

        ob_start();
        imagejpeg($destino, null, 82);
        imagedestroy($destino);
        $ruta = trim($carpeta, '/') . '/' . Str::uuid() . '.jpg';
        Storage::disk('public')->put($ruta, (string) ob_get_clean());

        return $ruta;
    }

    private static function asegurarMemoria(int $bytes): bool
    {
        $limite = self::bytes((string) ini_get('memory_limit'));
        if ($limite < 0) {
            return true; // sin límite
        }
        $requerido = memory_get_usage(true) + $bytes;
        if ($requerido <= $limite) {
            return true;
        }
        // El hosting puede permitir subir el límite para esta petición; si no, ini_set devuelve false.
        return @ini_set('memory_limit', (string) (int) ceil($requerido / 1048576 + 16) . 'M') !== false
            && self::bytes((string) ini_get('memory_limit')) >= $requerido;
    }

    private static function bytes(string $valor): int
    {
        $valor = trim($valor);
        if ($valor === '' || $valor === '-1') {
            return -1;
        }
        $numero = (int) $valor;

        return match (strtolower(substr($valor, -1))) {
            'g' => $numero * 1024 ** 3,
            'm' => $numero * 1024 ** 2,
            'k' => $numero * 1024,
            default => $numero,
        };
    }
}
