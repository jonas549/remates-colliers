<?php

namespace App\Http\Middleware;

use App\Actions\Fortify\CreateNewUser;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\File\UploadedFile as SymfonyUploadedFile;
use Symfony\Component\HttpFoundation\Response;

/**
 * El registro pedía volver a adjuntar los tres documentos cada vez que la validación fallaba por cualquier campo
 * (el navegador nunca puede prellenar un `<input type=file>`). Este middleware los conserva entre intentos:
 *
 * - Al volver con errores, guarda en el disco privado los archivos que sí estaban bien y los anota en la sesión.
 * - En el intento siguiente, los que el postor no vuelva a adjuntar se inyectan en la petición antes de validar,
 *   así que la validación y `CreateNewUser` trabajan igual que si los hubiera subido de nuevo.
 * - Al registrarse (o al entrar de nuevo al formulario sin errores) se borran.
 *
 * Los archivos quedan en `registro-temporal/` del disco privado, fuera de `public`, con nombre aleatorio.
 * `colliers:limpiar-temporales` borra los abandonados (programador, cada día).
 */
class DocumentosDelRegistro
{
    public const SESION = 'registro_documentos';

    public const SESION_CARPETA = 'registro_documentos_carpeta';

    public const CARPETA = 'registro-temporal';

    /** Horas que se conserva un adjunto sin terminar el registro. */
    public const HORAS = 24;

    public function handle(Request $request, Closure $next): Response
    {
        // Va en la lista de Fortify, que atiende también el ingreso y las claves: aquí solo interesa el registro.
        $registro = trim((string) config('fortify.paths.register', '/registro'), '/');
        if (! $request->is($registro, \App\Support\Idioma::PREFIJO . '/' . $registro)) {
            return $next($request);
        }

        $guardados = $this->guardados($request);

        if ($request->isMethod('POST')) {
            $this->inyectar($request, $guardados);
        }

        // Los errores que ya venían de antes no cuentan: interesa si ESTA petición dejó unos nuevos.
        $erroresPrevios = $request->session()->get('errors');
        $respuesta = $next($request);

        if (! $request->isMethod('POST')) {
            return $respuesta;
        }

        // Falló la validación: se conserva lo válido para el intento siguiente. Entró: ya no hacen falta.
        if ($this->fallo($request, $erroresPrevios)) {
            $this->conservar($request);
        } else {
            $this->olvidar($request);
        }

        return $respuesta;
    }

    /** Hay errores nuevos (otra instancia del contenedor de errores) = la validación rechazó este intento. */
    private function fallo(Request $request, mixed $erroresPrevios): bool
    {
        $errores = $request->session()->get('errors');

        return $errores !== null && $errores !== $erroresPrevios && $errores->getBag('default')->isNotEmpty();
    }

    /** @return array<string, array{ruta: string, nombre: string, mime: string}> */
    public static function guardados(Request $request): array
    {
        $guardados = $request->session()->get(self::SESION, []);

        return is_array($guardados) ? array_filter($guardados, fn ($d) => is_array($d) && Storage::disk('local')->exists($d['ruta'] ?? '')) : [];
    }

    /** Los documentos que no vienen en esta petición se reponen desde lo guardado. */
    private function inyectar(Request $request, array $guardados): void
    {
        // Se lee del contenedor de archivos y no con file(): file() deja en caché los archivos convertidos, y lo que se
        // agregue después no llegaría a la validación.
        $archivos = $request->files->get('documentos', []);
        $archivos = is_array($archivos) ? $archivos : [];

        foreach ($guardados as $tipo => $datos) {
            if (($archivos[$tipo] ?? null) instanceof SymfonyUploadedFile) {
                continue;
            }
            $completa = Storage::disk('local')->path($datos['ruta']);
            if (! is_file($completa)) {
                continue;
            }
            // El último argumento marca el archivo como «de prueba»: no viene de la subida de PHP, pero es nuestro.
            $archivos[$tipo] = new UploadedFile($completa, $datos['nombre'], $datos['mime'], null, true);
        }

        if ($archivos !== []) {
            $request->files->set('documentos', $archivos);
            // Por si algo ya los había convertido: se descarta esa caché para que la validación vea los repuestos.
            (fn () => $this->convertedFiles = null)->call($request);
        }
    }

    /** Guarda los adjuntos que pasaron tipo y tamaño; uno rechazado no se conserva (habría que volver a rechazarlo). */
    private function conservar(Request $request): void
    {
        $errores = $request->session()->get('errors')?->getBag('default');
        $guardados = self::guardados($request);
        $token = $request->session()->get(self::SESION_CARPETA) ?: Str::random(24);
        $request->session()->put(self::SESION_CARPETA, $token);
        $carpeta = self::CARPETA . '/' . $token;

        foreach (CreateNewUser::DOCUMENTOS as $tipo) {
            $archivo = $request->file("documentos.{$tipo}");
            if (! $archivo instanceof UploadedFile || ! $archivo->isValid() || $errores?->has("documentos.{$tipo}")) {
                continue;
            }
            // Ya guardado en un intento anterior y repuesto por inyectar(): no se vuelve a copiar.
            if (($guardados[$tipo]['nombre'] ?? null) === $archivo->getClientOriginalName() && isset($guardados[$tipo]['ruta'])) {
                continue;
            }
            if (isset($guardados[$tipo]['ruta'])) {
                Storage::disk('local')->delete($guardados[$tipo]['ruta']);
            }
            $ruta = $archivo->storeAs($carpeta, $tipo . '-' . bin2hex(random_bytes(6)) . '.' . strtolower($archivo->getClientOriginalExtension() ?: 'dat'), 'local');
            $guardados[$tipo] = [
                'ruta' => $ruta,
                'nombre' => mb_substr($archivo->getClientOriginalName(), 0, 255),
                'mime' => $archivo->getMimeType() ?: 'application/octet-stream',
            ];
        }

        $request->session()->put(self::SESION, $guardados);
    }

    private function olvidar(Request $request): void
    {
        foreach (self::guardados($request) as $datos) {
            Storage::disk('local')->delete($datos['ruta']);
        }
        if ($token = $request->session()->get(self::SESION_CARPETA)) {
            Storage::disk('local')->deleteDirectory(self::CARPETA . '/' . $token);
        }
        $request->session()->forget([self::SESION, self::SESION_CARPETA]);
    }
}
