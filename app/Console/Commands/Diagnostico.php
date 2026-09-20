<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Revisa que el servidor cumpla todo lo que la aplicación necesita. Se ejecuta al conectar el servidor
 * y cada vez que algo falle. Código de salida 0 si no hay errores críticos.
 */
class Diagnostico extends Command
{
    protected $signature = 'colliers:diagnostico';

    protected $description = 'Revisa requisitos del servidor, base de datos, permisos, cron y configuración';

    private int $errores = 0;

    private int $avisos = 0;

    public function handle(): int
    {
        $this->info('Remates Colliers · diagnóstico');

        $this->seccion('PHP');
        $this->revisar(version_compare(PHP_VERSION, '8.3.0', '>='), 'PHP ' . PHP_VERSION . ' (mínimo 8.3)');
        foreach (['pdo_mysql', 'mbstring', 'openssl', 'intl', 'gd', 'fileinfo', 'bcmath', 'ctype', 'tokenizer', 'xml', 'dom', 'curl', 'zip', 'iconv', 'simplexml', 'xmlreader', 'xmlwriter', 'zlib'] as $ext) { // las cinco últimas: exportaciones XLSX (Bloque O)
            $this->revisar(extension_loaded($ext), "Extensión {$ext}");
        }

        $this->seccion('Entorno');
        $this->revisar(filled(config('app.key')), 'APP_KEY definida (respaldarla: el RUT se cifra con ella)');
        // En un servidor (production o staging, como el sandbox) se exigen las mismas condiciones.
        $produccion = ! app()->environment(['local', 'testing']);
        $this->revisar(true, 'APP_ENV=' . app()->environment());
        $this->revisar(! ($produccion && config('app.debug')), 'APP_DEBUG=false en el servidor', critico: $produccion);
        $this->revisar(config('app.timezone') === 'UTC', 'Zona horaria de la aplicación en UTC');
        $this->revisar(config('app.locale') === 'es', 'Idioma español (APP_LOCALE=es)');
        $this->revisar(str_starts_with((string) config('app.url'), 'https://') || ! $produccion, 'APP_URL con https', critico: false);
        $this->revisar(filled(config('colliers.acceso.clave')) || ! $produccion, 'Clave de acceso al sandbox (COLLIERS_ACCESO_CLAVE)', critico: false);

        $this->seccion('Base de datos');
        try {
            DB::connection()->getPdo();
            $version = DB::connection()->getServerVersion();
            $this->revisar(true, 'Conexión a ' . config('database.default') . " ({$version})");
            Artisan::call('migrate:status', ['--pending' => true]);
            $pendientes = substr_count(Artisan::output(), 'Pending');
            $this->revisar($pendientes === 0, $pendientes === 0 ? 'Migraciones al día' : "{$pendientes} migraciones pendientes (el cron de deploy las aplica)", critico: false);
            foreach (['users', 'sessions', 'cache', 'jobs'] as $tabla) {
                $this->revisar(Schema::hasTable($tabla), "Tabla {$tabla}");
            }
            if (Schema::hasColumn('users', 'rol')) {
                $this->revisar(User::where('rol', User::ROL_ADMIN)->exists(), 'Existe un administrador (colliers:instalar lo crea)', critico: false);
            }
        } catch (\Throwable $e) {
            $this->revisar(false, 'Conexión a la base de datos: ' . $e->getMessage());
        }

        $this->seccion('Archivos');
        foreach (['storage/app', 'storage/framework/cache', 'storage/framework/sessions', 'storage/framework/views', 'storage/logs', 'bootstrap/cache'] as $ruta) {
            $this->revisar(is_dir(base_path($ruta)) && is_writable(base_path($ruta)), "Escritura en {$ruta}");
        }
        $this->revisar(is_link(public_path('storage')) || file_exists(public_path('storage')), 'Enlace public/storage (colliers:instalar lo crea)', critico: false);
        $this->revisar(file_exists(public_path('build/manifest.json')), 'Assets compilados (public/build/manifest.json)');
        $this->revisar(file_exists(base_path('vendor/autoload.php')), 'Dependencias de Composer instaladas');
        // Sin OPcache cada archivo cargado se compila en cada petición: las de desarrollo (phpunit, var-dumper…)
        // agregan ~200 KB por petición. Se instalan sin ellas con `composer install --no-dev`.
        $this->revisar(! $produccion || ! class_exists(\PHPUnit\Framework\TestCase::class),
            'Sin dependencias de desarrollo en el servidor (composer install --no-dev)', critico: false);
        // Sin OPcache las cachés de `php artisan optimize` evitan leer .env, registrar rutas y descubrir eventos.
        $this->revisar(! $produccion || app()->configurationIsCached(), 'Configuración en caché (php artisan optimize)', critico: false);
        $this->revisar(! $produccion || app()->routesAreCached(), 'Rutas en caché (php artisan optimize)', critico: false);
        $this->revisar(! $produccion || app()->eventsAreCached(), 'Eventos en caché (php artisan optimize)', critico: false);
        // Límites de subida: el registro admite 4 documentos de 5 MB. Si post_max_size es menor que la suma, PHP
        // descarta el cuerpo entero de la petición y el formulario vuelve VACÍO, sin errores que mostrar.
        $bytes = static function (string $valor): int {
            $valor = trim($valor);
            $unidad = strtolower(substr($valor, -1));
            $numero = (float) $valor;

            return (int) match ($unidad) {
                'g' => $numero * 1024 ** 3,
                'm' => $numero * 1024 ** 2,
                'k' => $numero * 1024,
                default => $numero,
            };
        };
        $necesarios = (\App\Actions\Fortify\CreateNewUser::MAX_DOCUMENTO_KB * 1024)
            * count(\App\Actions\Fortify\CreateNewUser::DOCUMENTOS) + 512 * 1024;
        $post = $bytes((string) ini_get('post_max_size'));
        $subida = $bytes((string) ini_get('upload_max_filesize'));
        $mb = fn (int $b) => round($b / 1024 ** 2, 1) . ' MB';
        $this->revisar($post === 0 || $post >= $necesarios,
            "post_max_size ({$mb($post)}) alcanza para el registro completo (" . $mb($necesarios) . ')', critico: false);
        $this->revisar($subida === 0 || $subida >= \App\Actions\Fortify\CreateNewUser::MAX_DOCUMENTO_KB * 1024,
            "upload_max_filesize ({$mb($subida)}) admite un documento de " . (\App\Actions\Fortify\CreateNewUser::MAX_DOCUMENTO_KB / 1024) . ' MB', critico: false);
        $this->revisar($post === 0 || $subida === 0 || $post >= $subida,
            'post_max_size no es menor que upload_max_filesize', critico: false);
        // El panel acepta documentos de 20 MB y fotos de 15 MB: con un límite menor el envío no llega nunca.
        $this->revisar($subida === 0 || $subida >= 20 * 1024 ** 2,
            "upload_max_filesize ({$mb($subida)}) admite los documentos de 20 MB del panel", critico: false);

        $this->revisar(true, 'OPcache en consola: ' . (function_exists('opcache_get_status') && ini_get('opcache.enable_cli') ? 'activo' : 'apagado')
            . ' (el de la web se ve en Administración → Configuración → Sistema)');

        $this->seccion('Cron y colas');
        $latido = config('colliers.latido_programador');
        $hace = file_exists($latido) ? time() - (int) filemtime($latido) : null;
        $this->revisar($hace !== null && $hace < 180, $hace === null
            ? 'Programador de tareas: sin latido (falta el cron de schedule:run)'
            : "Programador de tareas: último latido hace {$hace} s", critico: false);
        $this->revisar(config('queue.default') === 'database', 'Cola en base de datos (QUEUE_CONNECTION=database)');

        $this->newLine();
        if ($this->errores) {
            $this->error("{$this->errores} error(es) crítico(s), {$this->avisos} aviso(s).");

            return self::FAILURE;
        }
        $this->info("Sin errores críticos. {$this->avisos} aviso(s).");

        return self::SUCCESS;
    }

    private function seccion(string $titulo): void
    {
        $this->newLine();
        $this->line("<comment>{$titulo}</comment>");
    }

    private function revisar(bool $ok, string $texto, bool $critico = true): void
    {
        if ($ok) {
            $this->line("  <info>✓</info> {$texto}");

            return;
        }
        if ($critico) {
            $this->errores++;
            $this->line("  <error>✗</error> {$texto}");

            return;
        }
        $this->avisos++;
        $this->line("  <comment>!</comment> {$texto}");
    }
}
