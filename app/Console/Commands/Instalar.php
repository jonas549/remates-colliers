<?php

namespace App\Console\Commands;

use App\Models\Configuracion;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

/**
 * Deja la aplicación lista después de cada deploy. Idempotente: se puede ejecutar cualquier número de
 * veces sin duplicar nada. El script de deploy lo llama siempre, después de `migrate`.
 *
 * Contrato con el servidor: todo paso de instalación futuro (configuración por defecto del Bloque V,
 * plantillas de correo, catálogos) se agrega AQUÍ, nunca como paso manual en el servidor.
 */
class Instalar extends Command
{
    protected $signature = 'colliers:instalar';

    protected $description = 'Prepara la aplicación después de un deploy (idempotente)';

    public function handle(): int
    {
        $this->info('Remates Colliers · instalación');

        if (! Schema::hasTable('users') || ! Schema::hasColumn('users', 'rol')) {
            $this->error('Faltan migraciones. Ejecuta primero: php artisan migrate --force');

            return self::FAILURE;
        }

        $this->carpetas();
        $this->enlaceStorage();
        $this->primerAdministrador();
        $this->configuracionPorDefecto();

        $this->info('Instalación completa.');

        return self::SUCCESS;
    }

    private function carpetas(): void
    {
        foreach (['app/public', 'app/private', 'framework/cache/data', 'framework/sessions', 'framework/views', 'logs'] as $carpeta) {
            File::ensureDirectoryExists(storage_path($carpeta));
        }
        $this->line('  ✓ Carpetas de storage');
    }

    private function enlaceStorage(): void
    {
        $enlace = public_path('storage');
        if (is_link($enlace) || file_exists($enlace)) {
            $this->line('  ✓ Enlace public/storage ya existe');

            return;
        }
        try {
            $this->callSilently('storage:link');
            $this->line('  ✓ Enlace public/storage creado');
        } catch (\Throwable $e) {
            $this->warn('  ! No se pudo crear public/storage: ' . $e->getMessage());
        }
    }

    /** Siembra solo las claves que faltan: nunca pisa un valor que un administrador ya cambió. */
    private function configuracionPorDefecto(): void
    {
        if (! Schema::hasTable('configuraciones')) {
            $this->warn('  ! Falta la tabla configuraciones; ejecuta migrate');

            return;
        }

        $creadas = Configuracion::sembrarDefectos();
        $this->line($creadas ? "  ✓ Configuración por defecto: {$creadas} valor(es) nuevo(s)" : '  ✓ Configuración por defecto ya presente');
    }

    private function primerAdministrador(): void
    {
        if (User::where('rol', User::ROL_ADMIN)->exists()) {
            $this->line('  ✓ Ya existe un administrador');

            return;
        }

        $email = config('colliers.admin.email');
        $clave = config('colliers.admin.clave');
        if (! $email || ! $clave) {
            $this->warn('  ! No hay administrador y faltan COLLIERS_ADMIN_EMAIL / COLLIERS_ADMIN_CLAVE en el .env');

            return;
        }
        if (mb_strlen($clave) < 12) {
            $this->warn('  ! COLLIERS_ADMIN_CLAVE debe tener al menos 12 caracteres; no se creó el administrador');

            return;
        }

        User::create([
            'name' => config('colliers.admin.nombre'),
            'email' => $email,
            'password' => $clave,
            'rol' => User::ROL_ADMIN,
            'estado' => User::ESTADO_ACTIVO,
            'debe_cambiar_clave' => true,
        ]);
        $this->line("  ✓ Administrador creado: {$email} (deberá cambiar la clave al ingresar)");
    }
}
