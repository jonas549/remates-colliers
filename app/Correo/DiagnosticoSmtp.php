<?php

namespace App\Correo;

use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Throwable;

/**
 * «Probar conexión» del panel (17/09): comprueba el correo saliente SIN enviar nada y dice exactamente qué falló.
 *
 * Va por etapas, porque cada una falla distinto: el nombre del servidor, el puerto, el cifrado y las credenciales.
 * Un «error al enviar» no sirve para arreglar nada.
 */
class DiagnosticoSmtp
{
    private const ESPERA_SEGUNDOS = 8;

    /**
     * @param  array{host: string, puerto: int, cifrado: string, usuario: ?string, clave: ?string}  $datos
     * @return array{ok: bool, mensaje: string, pasos: list<array{nombre: string, ok: bool, detalle: string}>, ms: int}
     */
    public function probar(array $datos): array
    {
        $inicio = microtime(true);
        $pasos = [];
        // Por referencia: los pasos se van agregando durante la prueba (una función flecha los copiaría vacíos).
        $fin = function (bool $ok, string $mensaje) use (&$pasos, $inicio) {
            return ['ok' => $ok, 'mensaje' => $mensaje, 'pasos' => $pasos, 'ms' => (int) round((microtime(true) - $inicio) * 1000)];
        };

        $host = trim($datos['host']);
        $puerto = (int) $datos['puerto'];
        $cifrado = $datos['cifrado'];
        $usuario = $datos['usuario'] ?: null;

        if ($host === '') {
            return $fin(false, 'Falta el servidor SMTP: escríbelo y guarda antes de probar.');
        }

        // 1. Nombre del servidor.
        $ip = @gethostbyname($host);
        if ($ip === $host && ! filter_var($host, FILTER_VALIDATE_IP)) {
            $pasos[] = ['nombre' => 'Nombre del servidor', 'ok' => false, 'detalle' => 'sin respuesta de DNS'];

            return $fin(false, "No se pudo resolver el servidor «{$host}»: revisa que el nombre esté bien escrito.");
        }
        $pasos[] = ['nombre' => 'Nombre del servidor', 'ok' => true, 'detalle' => "{$host} → {$ip}"];

        // 2. Puerto abierto (sin cifrar todavía: así un puerto cerrado no se confunde con un problema de TLS).
        $error = 0;
        $mensajeError = '';
        $conexion = @stream_socket_client("tcp://{$host}:{$puerto}", $error, $mensajeError, self::ESPERA_SEGUNDOS);
        if ($conexion === false) {
            $pasos[] = ['nombre' => "Puerto {$puerto}", 'ok' => false, 'detalle' => trim($mensajeError) ?: "error {$error}"];
            $mensaje = str_contains(mb_strtolower($mensajeError), 'refus')
                ? "El servidor {$host} rechazó la conexión en el puerto {$puerto}: el puerto está cerrado o el servicio no está escuchando ahí."
                : "El servidor {$host} no respondió en el puerto {$puerto} (se esperó " . self::ESPERA_SEGUNDOS . ' s): puede estar bloqueado por el firewall del hosting.';

            return $fin(false, $mensaje);
        }
        fclose($conexion);
        $pasos[] = ['nombre' => "Puerto {$puerto}", 'ok' => true, 'detalle' => 'abierto'];

        // 3. Cifrado y 4. autenticación: las resuelve el transporte de Symfony al iniciar la sesión SMTP.
        $transporte = new EsmtpTransport($host, $puerto, $cifrado === 'ssl');
        $transporte->setUsername((string) ($usuario ?? ''));
        $transporte->setPassword((string) ($datos['clave'] ?? ''));
        $flujo = $transporte->getStream();
        if (method_exists($flujo, 'setTimeout')) {
            $flujo->setTimeout(self::ESPERA_SEGUNDOS);
        }
        if ($cifrado === 'ninguno') {
            $transporte->setAutoTls(false);
        }

        try {
            $transporte->start();
            $transporte->stop();
        } catch (TransportExceptionInterface|Throwable $e) {
            return $fin(false, $this->explicar($e, $host, $puerto, $cifrado, $usuario, $pasos));
        }

        $pasos[] = ['nombre' => 'Cifrado', 'ok' => true, 'detalle' => match ($cifrado) {
            'ssl' => 'SSL aceptado', 'ninguno' => 'sin cifrado, como está configurado', default => 'TLS (STARTTLS) aceptado',
        }];
        $pasos[] = ['nombre' => 'Autenticación', 'ok' => true, 'detalle' => $usuario === null ? 'el servidor no la exigió' : "aceptada como {$usuario}"];

        $como = $usuario === null ? 'sin autenticación' : "autenticado como {$usuario}";
        $nombreCifrado = match ($cifrado) { 'ssl' => 'SSL', 'ninguno' => 'sin cifrado', default => 'TLS' };

        return $fin(true, "Conectado a {$host}:{$puerto} con {$nombreCifrado} y {$como}.");
    }

    /** Traduce el error de un envío real con el mismo diccionario que la prueba de conexión. */
    public function explicarError(Throwable $e, array $datos): string
    {
        $pasos = [];

        return $this->explicar($e, (string) ($datos['host'] ?? ''), (int) ($datos['puerto'] ?? 0), (string) ($datos['cifrado'] ?? 'tls'), $datos['usuario'] ?: null, $pasos);
    }

    /** Traduce el error del servidor SMTP a algo accionable. */
    private function explicar(Throwable $e, string $host, int $puerto, string $cifrado, ?string $usuario, array &$pasos): string
    {
        $texto = $e->getMessage();
        $bajo = mb_strtolower($texto);
        $corto = mb_substr(trim(preg_replace('/\s+/', ' ', $texto)), 0, 200);

        $autenticacion = str_contains($bajo, 'authentic') || str_contains($bajo, '535') || str_contains($bajo, '534')
            || str_contains($bajo, 'username and password') || str_contains($bajo, 'credentials');
        $cifradoMal = str_contains($bajo, 'ssl') || str_contains($bajo, 'tls') || str_contains($bajo, 'crypto') || str_contains($bajo, 'certificate');

        if ($autenticacion) {
            $pasos[] = ['nombre' => 'Cifrado', 'ok' => true, 'detalle' => 'aceptado'];
            $pasos[] = ['nombre' => 'Autenticación', 'ok' => false, 'detalle' => $corto];

            return $usuario === null
                ? "El servidor {$host} exige autenticación y no hay usuario configurado. Completa usuario y contraseña. ({$corto})"
                : "Autenticación rechazada para «{$usuario}»: usuario o contraseña incorrectos. ({$corto})";
        }

        if ($cifradoMal) {
            $pasos[] = ['nombre' => 'Cifrado', 'ok' => false, 'detalle' => $corto];
            $sugerencia = $cifrado === 'ssl'
                ? 'Si el puerto es 587, el cifrado suele ser TLS (STARTTLS), no SSL.'
                : 'Si el puerto es 465, el cifrado suele ser SSL, no TLS (STARTTLS).';

            return "El servidor {$host}:{$puerto} no aceptó el cifrado configurado. {$sugerencia} ({$corto})";
        }

        if (str_contains($bajo, 'timed out') || str_contains($bajo, 'timeout')) {
            $pasos[] = ['nombre' => 'Sesión SMTP', 'ok' => false, 'detalle' => $corto];

            return "El servidor {$host}:{$puerto} aceptó la conexión pero no completó la sesión SMTP (tiempo agotado). Suele pasar al usar SSL en un puerto de STARTTLS o al revés.";
        }

        $pasos[] = ['nombre' => 'Sesión SMTP', 'ok' => false, 'detalle' => $corto];

        return "El servidor {$host}:{$puerto} rechazó la conexión SMTP: {$corto}";
    }
}
