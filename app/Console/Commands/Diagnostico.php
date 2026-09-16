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
        foreach (['pdo_mysql', 'mbstring', 'openssl', 'intl', 'gd', 'fileinfo', 'bcmath', 'ctype', 'tokenizer', 'xml', 'dom', 'curl', 'zip'] as $ext) {
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
