<?php

namespace App\Subastas;

/**
 * Presencia en la sala de pujas: quién tiene la sala abierta ahora.
 *
 * Sin websockets ni procesos persistentes (CLAUDE.md §4): el navegador del postor envía un latido cada ~15 s a
 * public/presencia.php, que NO arranca Laravel (docs/RENDIMIENTO-SIN-OPCACHE.md: cada petición a Laravel cuesta
 * ~350–570 ms de CPU sin OPcache, y 10 postores latiendo competirían con las pujas). Por eso esta clase no usa nada del
 * framework: la cargan con `require` el script suelto y, por el autoload, Laravel. Una sola implementación.
 *
 * - Quién está: un archivo por remate en storage (user_id → hora del servidor del último latido), bajo flock. No va a la
 *   base: un latido no abre conexiones (el hosting ya mostró `1040 Too many connections`, 21/09).
 * - Activo = latido en los últimos `ventanaMs`. Los vencidos se limpian cada vez que alguien lee la presencia.
 * - El JSON público del remate lleva SOLO el número (`presencia.conectados`): LiteSpeed lo entrega a cualquiera, así que
 *   ahí nunca van nombres. La lista con nombres la arma Laravel para el panel (EnVivoController::presencia).
 * - Orden de bloqueos, siempre el mismo para no trabarse: primero el del JSON público (`.bloqueo-{slug}`, el mismo que usa
 *   EmisorJsonEstatico), después el del archivo de presencia.
 * - El latido llega con un token firmado (HMAC) que Laravel entrega al abrir la sala: así el script no descifra la sesión.
 */
final class PresenciaSala
{
    public function __construct(
        private readonly string $carpeta,
        private readonly string $carpetaTiempoReal,
        private readonly int $ventanaMs = 45000,
    ) {}

    // ── Token del latido ─────────────────────────────────────────────────────────────────────────────────────

    /** @param array{r: int, s: string, u: int, e: int} $datos remate, slug, usuario y vencimiento (ms UTC) */
    public static function firmar(array $datos, string $clave): string
    {
        $cuerpo = self::b64(json_encode($datos, JSON_THROW_ON_ERROR));

        return $cuerpo . '.' . self::b64(hash_hmac('sha256', $cuerpo, $clave, true));
    }

    /** @return array{r: int, s: string, u: int, e: int}|null */
    public static function verificar(string $token, string $clave, int $ahoraMs): ?array
    {
        $partes = explode('.', $token);
        if (count($partes) !== 2 || $clave === '') {
            return null;
        }
        if (! hash_equals(self::b64(hash_hmac('sha256', $partes[0], $clave, true)), $partes[1])) {
            return null;
        }
        $datos = json_decode((string) base64_decode(strtr($partes[0], '-_', '+/'), true), true);
        if (! is_array($datos) || ! is_int($datos['r'] ?? null) || ! is_int($datos['u'] ?? null) || ! is_int($datos['e'] ?? null)
            || ! is_string($datos['s'] ?? null) || ! preg_match('/^[a-z0-9-]{1,120}$/', $datos['s']) || $datos['e'] < $ahoraMs) {
            return null;
        }

        return $datos;
    }

    // ── Latidos ──────────────────────────────────────────────────────────────────────────────────────────────

    /** Registra el latido y, si cambió el número de conectados, lo publica en el JSON. Devuelve los conectados. */
    public function latido(int $remateId, string $slug, int $userId, int $ahoraMs): int
    {
        $this->conArchivo($remateId, $ahoraMs, function (array $d) use ($slug, $userId, $ahoraMs) {
            $d['slug'] = $slug;
            $d['usuarios'][(string) $userId] = $ahoraMs;

            return $d;
        });

        return $this->publicarConteo($remateId, $slug, $ahoraMs);
    }

    /** El navegador avisa que cerró la sala (sendBeacon): baja del contador sin esperar a que venza. */
    public function salir(int $remateId, string $slug, int $userId, int $ahoraMs): int
    {
        $this->conArchivo($remateId, $ahoraMs, function (array $d) use ($userId) {
            unset($d['usuarios'][(string) $userId]);

            return $d;
        });

        return $this->publicarConteo($remateId, $slug, $ahoraMs);
    }

    /**
     * Conectados ahora (limpia los vencidos). Ordenados del latido más reciente al más antiguo.
     *
     * @return array<int, int> user_id => ms del último latido
     */
    public function activos(int $remateId, int $ahoraMs): array
    {
        // Leer no crea archivos: un remate sin nadie en la sala no deja rastro en storage.
        if (! is_file($this->archivo($remateId))) {
            return [];
        }
        $usuarios = [];
        $this->conArchivo($remateId, $ahoraMs, fn (array $d) => $d, $usuarios);
        arsort($usuarios);

        $activos = [];
        foreach ($usuarios as $id => $ms) {
            $activos[(int) $id] = (int) $ms;
        }

        return $activos;
    }

    public function conectados(int $remateId, int $ahoraMs): int
    {
        return count($this->activos($remateId, $ahoraMs));
    }

    /**
     * Escribe el número de conectados en el JSON público si cambió. Bajo el bloqueo del emisor y releyendo la presencia
     * adentro: si el emisor reescribe el JSON al mismo tiempo, el número final es el vigente. Sin JSON (remate borrado o
     * nunca publicado) no hace nada.
     */
    public function publicarConteo(int $remateId, string $slug, int $ahoraMs): int
    {
        $destino = $this->carpetaTiempoReal . DIRECTORY_SEPARATOR . $slug . '.json';
        if (! is_file($destino)) {
            return $this->conectados($remateId, $ahoraMs);
        }

        $bloqueo = @fopen($this->carpetaTiempoReal . DIRECTORY_SEPARATOR . '.bloqueo-' . $slug, 'c');
        if ($bloqueo === false || ! flock($bloqueo, LOCK_EX)) {
            return $this->conectados($remateId, $ahoraMs);
        }

        try {
            $n = $this->conectados($remateId, $ahoraMs);
            $estado = json_decode((string) @file_get_contents($destino), true);
            if (is_array($estado) && ($estado['presencia']['conectados'] ?? null) !== $n) {
                $estado['presencia'] = ['conectados' => $n];
                self::escribirAtomico($destino, json_encode($estado, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
            }

            return $n;
        } finally {
            flock($bloqueo, LOCK_UN);
            fclose($bloqueo);
        }
    }

    /**
     * Respaldo del cron (cada minuto): si todos cerraron la sala sin avisar, nadie más late y el número quedaría fijo.
     * Limpia los vencidos de cada remate con presencia, republica el número y borra los archivos vacíos.
     */
    public function limpiarTodos(int $ahoraMs): int
    {
        $remates = 0;
        foreach (glob($this->carpeta . DIRECTORY_SEPARATOR . '*.json') ?: [] as $archivo) {
            if (! preg_match('/(\d+)\.json$/', $archivo, $m)) {
                continue;
            }
            $remateId = (int) $m[1];
            $datos = json_decode((string) @file_get_contents($archivo), true);
            $slug = is_array($datos) && is_string($datos['slug'] ?? null) ? $datos['slug'] : null;
            $n = $slug === null ? $this->conectados($remateId, $ahoraMs) : $this->publicarConteo($remateId, $slug, $ahoraMs);
            if ($n === 0) {
                @unlink($archivo);
            }
            $remates++;
        }

        return $remates;
    }

    // ── Archivos ─────────────────────────────────────────────────────────────────────────────────────────────

    /** Lee, modifica y guarda el archivo de presencia del remate bajo flock, limpiando los vencidos. */
    private function conArchivo(int $remateId, int $ahoraMs, callable $cambio, ?array &$resultado = null): void
    {
        if (! is_dir($this->carpeta)) {
            @mkdir($this->carpeta, 0775, true);
        }
        $f = @fopen($this->archivo($remateId), 'c+');
        if ($f === false) {
            return;
        }

        try {
            if (! flock($f, LOCK_EX)) {
                return;
            }
            $leido = (string) stream_get_contents($f);
            $d = json_decode($leido, true);
            $d = is_array($d) ? $d : [];
            $d['usuarios'] = is_array($d['usuarios'] ?? null) ? $d['usuarios'] : [];

            $d = $cambio($d);
            $limite = $ahoraMs - $this->ventanaMs;
            $d['usuarios'] = array_filter($d['usuarios'], fn ($ms) => (int) $ms > $limite);
            $resultado = $d['usuarios'];

            // Solo se escribe si algo cambió (un latido nuevo, una salida o un vencido que se limpia).
            $nuevo = json_encode($d, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            if ($nuevo !== $leido) {
                ftruncate($f, 0);
                rewind($f);
                fwrite($f, $nuevo);
                fflush($f);
            }
        } finally {
            flock($f, LOCK_UN);
            fclose($f);
        }
    }

    /**
     * Temporal + rename en la misma carpeta, como EmisorJsonEstatico. En Windows (solo desarrollo) rename puede fallar si
     * otro proceso retiene el archivo: se reintenta y, si sigue retenido, se copia encima.
     */
    public static function escribirAtomico(string $destino, string $contenido): bool
    {
        $temporal = $destino . '.' . bin2hex(random_bytes(6)) . '.tmp';
        if (file_put_contents($temporal, $contenido) === false) {
            return false;
        }
        for ($intento = 1; $intento <= 5; $intento++) {
            if (@rename($temporal, $destino)) {
                return true;
            }
            if (PHP_OS_FAMILY !== 'Windows') {
                break;
            }
            usleep(10000);
        }
        $ok = PHP_OS_FAMILY === 'Windows' && @copy($temporal, $destino);
        @unlink($temporal);

        return $ok;
    }

    private function archivo(int $remateId): string
    {
        return $this->carpeta . DIRECTORY_SEPARATOR . $remateId . '.json';
    }

    private static function b64(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }
}
