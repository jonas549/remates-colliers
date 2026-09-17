<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Configuracion;
use App\Support\CorreoSaliente;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Mail\MailManager;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

/**
 * Administración → Configuración (Bloque V): todo valor de negocio editable sin deploy (CLAUDE.md §6), prueba del correo
 * saliente, actualización manual de la UF y el estado del servidor visto DESDE LA WEB (OPcache de la web puede diferir
 * del de la consola).
 */
class ConfiguracionController extends Controller
{
    public function index(): View
    {
        Configuracion::sembrarDefectos();
        $valores = [];
        foreach (Configuracion::DEFECTOS as $clave => $datos) {
            $valores[$clave] = $datos['tipo'] === 'secreto' ? filled(Configuracion::valor($clave)) : Configuracion::valor($clave);
        }

        return view('admin.configuracion', [
            'grupos' => collect(Configuracion::DEFECTOS)->groupBy('grupo', preserveKeys: true)
                ->sortBy(fn ($campos, $grupo) => array_search($grupo, array_keys(Configuracion::GRUPOS), true)),
            'valores' => $valores,
            'sistema' => $this->sistema(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $entrada = (array) $request->input('config', []);
        // Las casillas desmarcadas no viajan: se envían con un campo oculto en 0.
        $reglas = [];
        $nombres = [];
        foreach (Configuracion::DEFECTOS as $clave => $datos) {
            if (! empty($datos['solo_lectura'])) {
                continue;
            }
            $reglas["config.{$clave}"] = $this->reglas($datos);
            $nombres["config.{$clave}"] = mb_strtolower($datos['etiqueta']);
        }
        if (isset($entrada['pujas_rapidas'])) {
            $montos = collect(preg_split('/[;,\s]+/', (string) $entrada['pujas_rapidas'], -1, PREG_SPLIT_NO_EMPTY))
                ->map(fn ($m) => preg_replace('/[^\d]/', '', $m))->filter()->values()->all();
            $request->merge(['config' => ['pujas_rapidas' => $montos] + $entrada]);
            $reglas['config.pujas_rapidas.*'] = ['integer', 'min:1000'];
        }
        $validado = $request->validate($reglas, [], $nombres)['config'] ?? [];

        DB::transaction(function () use ($validado) {
            foreach ($validado as $clave => $valor) {
                if (array_key_exists($clave, Configuracion::DEFECTOS)) {
                    Configuracion::guardar($clave, $valor);
                }
            }
        });

        return back()->with('estado', 'Configuración guardada. Rige desde la próxima acción: no hace falta desplegar.');
    }

    public function probarCorreo(Request $request, MailManager $correo): RedirectResponse
    {
        $destino = $request->validate(['destino' => ['required', 'email']], [], ['destino' => 'correo de destino'])['destino'];

        // Se aplica lo recién guardado y se descartan los transportes ya creados en esta petición.
        CorreoSaliente::aplicar(config());
        $correo->forgetMailers();
        try {
            Mail::raw("Este es un correo de prueba de Remates Colliers.\n\nSi lo recibiste, el correo saliente está bien configurado.",
                fn ($m) => $m->to($destino)->subject('Prueba de correo · Remates Colliers'));
        } catch (Throwable $e) {
            report($e);

            return back()->with('error', 'No se pudo enviar el correo de prueba: ' . mb_substr($e->getMessage(), 0, 300));
        }

        $modo = config('mail.default');

        return back()->with('estado', $modo === 'log'
            ? "Correo de prueba registrado en storage/logs (modo «{$modo}»: no sale a Internet)."
            : "Correo de prueba enviado a {$destino} por «{$modo}». Revisa la bandeja de entrada y la de spam.");
    }

    public function actualizarUf(): RedirectResponse
    {
        $codigo = Artisan::call('colliers:actualizar-uf', ['--forzar' => true]);
        $salida = trim(Artisan::output());

        return back()->with($codigo === 0 ? 'estado' : 'error', $salida);
    }

    private function reglas(array $datos): array
    {
        $rango = array_filter(['min:' . ($datos['min'] ?? ''), 'max:' . ($datos['max'] ?? '')], fn ($r) => ! str_ends_with($r, ':'));

        return match ($datos['tipo']) {
            'entero' => ['required', 'integer', ...$rango],
            'porcentaje' => ['required', 'numeric', ...$rango],
            'decimal' => ['nullable', 'numeric', ...$rango],
            'texto' => ['nullable', 'string', 'max:255'],
            'texto_largo' => ['nullable', 'string', 'max:2000'],
            'correo' => ['nullable', 'email', 'max:255'],
            'url' => ['nullable', 'url:http,https', 'max:500'],
            'booleano' => ['required', 'boolean'],
            'opcion' => ['required', Rule::in(array_keys($datos['opciones']))],
            'lista_montos' => ['required', 'array', 'min:1', 'max:6'],
            'secreto' => ['nullable', 'string', 'max:255'],
            default => ['nullable'],
        };
    }

    /** Estado del servidor desde la web (el mismo PHP que atiende las pujas). */
    private function sistema(): array
    {
        CorreoSaliente::aplicar(config());
        $opcache = function_exists('opcache_get_status') ? @opcache_get_status(false) : false;
        $latido = config('colliers.latido_programador');
        $hace = file_exists($latido) ? time() - (int) filemtime($latido) : null;

        return [
            'OPcache (web)' => is_array($opcache) && ($opcache['opcache_enabled'] ?? false) ? 'Activo' : 'APAGADO: cada petición compila el framework (ver docs/RENDIMIENTO-SIN-OPCACHE.md)',
            'PHP' => PHP_VERSION . ' · ' . PHP_SAPI,
            'Entorno' => app()->environment() . (config('app.debug') ? ' · depuración ACTIVA' : ''),
            'Límite de memoria' => (string) ini_get('memory_limit'),
            'Tiempo máximo por petición' => ini_get('max_execution_time') . ' s',
            'Cachés de optimize' => implode(' · ', [
                'configuración ' . (app()->configurationIsCached() ? 'sí' : 'no'),
                'rutas ' . (app()->routesAreCached() ? 'sí' : 'no'),
                'eventos ' . (app()->eventsAreCached() ? 'sí' : 'no'),
            ]),
            'Cron (programador)' => $hace === null ? 'Sin latido: falta el cron de schedule:run' : "Último latido hace {$hace} s",
            'Cola de correos' => (Schema::hasTable('jobs') ? DB::table('jobs')->count() : 0) . ' pendientes · '
                . (Schema::hasTable('failed_jobs') ? DB::table('failed_jobs')->count() : 0) . ' fallidos',
            'Correo saliente' => (string) config('mail.default'),
        ];
    }
}
