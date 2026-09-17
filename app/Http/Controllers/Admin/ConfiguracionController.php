<?php

namespace App\Http\Controllers\Admin;

use App\Correo\Avisos;
use App\Correo\DiagnosticoSmtp;
use App\Correo\Plantillas;
use App\Http\Controllers\Controller;
use App\Models\Configuracion;
use App\Models\NotificacionLog;
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
    public function index(): RedirectResponse
    {
        return redirect()->route('admin.configuracion.seccion', array_key_first(Configuracion::SECCIONES));
    }

    public function show(string $seccion): View
    {
        abort_unless(isset(Configuracion::SECCIONES[$seccion]), 404);
        Configuracion::sembrarDefectos();
        $campos = Configuracion::camposDe($seccion);
        $valores = [];
        foreach ($campos as $clave => $datos) {
            $valores[$clave] = $datos['tipo'] === 'secreto' ? filled(Configuracion::valor($clave)) : Configuracion::valor($clave);
        }

        $vista = in_array($seccion, ['sistema', 'plantillas', 'notificaciones', 'correos'], true) ? $seccion : 'seccion';

        return view("admin.configuracion.{$vista}", ($seccion === 'correos' ? app(RegistroCorreosController::class)->datos(request()) : []) + [
            'seccion' => $seccion,
            'grupos' => collect($campos)->groupBy('grupo', preserveKeys: true)
                ->sortBy(fn ($c, $grupo) => array_search($grupo, Configuracion::SECCIONES[$seccion]['grupos'], true)),
            'valores' => $valores,
            'sistema' => $seccion === 'sistema' ? $this->sistema() : [],
            'envios' => $seccion === 'notificaciones' ? NotificacionLog::latest('id')->take(20)->get() : collect(),
        ]);
    }

    public function update(Request $request, string $seccion): RedirectResponse
    {
        abort_unless(isset(Configuracion::SECCIONES[$seccion]), 404);
        $entrada = (array) $request->input('config', []);
        // Las casillas desmarcadas no viajan: se envían con un campo oculto en 0.
        $reglas = [];
        $nombres = [];
        foreach (Configuracion::camposDe($seccion) as $clave => $datos) {
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
        $deLaSeccion = Configuracion::camposDe($seccion);

        // Interruptores de los avisos (pantalla Notificaciones): no son claves de DEFECTOS, se guardan aparte.
        $avisos = [];
        if ($seccion === 'notificaciones') {
            foreach ((array) $request->input('avisos', []) as $plantilla => $activo) {
                if (isset(Plantillas::CATALOGO[$plantilla]) && ! empty(Plantillas::CATALOGO[$plantilla]['activable'])) {
                    $avisos[$plantilla] = filter_var($activo, FILTER_VALIDATE_BOOL);
                }
            }
        }

        DB::transaction(function () use ($validado, $deLaSeccion, $avisos) {
            foreach ($validado as $clave => $valor) {
                // Solo las claves de esta pantalla: un formulario no puede tocar valores de otra sección.
                if (array_key_exists($clave, $deLaSeccion)) {
                    Configuracion::guardar($clave, $valor);
                }
            }
            foreach ($avisos as $plantilla => $activo) {
                Avisos::guardar($plantilla, $activo);
            }
        });

        return back()->with('estado', 'Configuración guardada. Rige desde la próxima acción: no hace falta desplegar.');
    }

    /** Comprueba el correo saliente SIN enviar nada (17/09): DNS, puerto, cifrado y credenciales, por separado. */
    public function probarConexion(DiagnosticoSmtp $diagnostico): RedirectResponse
    {
        CorreoSaliente::aplicar(config());
        $mailer = (string) config('mail.default');
        $volver = back();

        if ($mailer === 'log') {
            return $volver->with('error', 'El envío está en modo «No enviar: dejar en el registro»: no hay servidor al que conectarse. Cambia el modo a SMTP para probar la conexión.');
        }
        if ($mailer !== 'smtp') {
            return $volver->with('error', "El envío está en modo «{$mailer}», que no usa SMTP: no hay conexión que probar.");
        }

        $resultado = $diagnostico->probar($this->datosSmtp());
        $detalle = collect($resultado['pasos'])->map(fn (array $p) => ($p['ok'] ? '✓' : '✗') . " {$p['nombre']}: {$p['detalle']}")->join(' · ');

        return $volver->with($resultado['ok'] ? 'estado' : 'error', "{$resultado['mensaje']} ({$resultado['ms']} ms) — {$detalle}");
    }

    public function probarCorreo(Request $request, MailManager $correo): RedirectResponse
    {
        $destino = $request->validate(['destino' => ['required', 'email']], [], ['destino' => 'correo de destino'])['destino'];

        // Se aplica lo recién guardado y se descartan los transportes ya creados en esta petición.
        CorreoSaliente::aplicar(config());
        $correo->forgetMailers();
        $datos = $this->datosSmtp();
        $remitente = (string) config('mail.from.address');
        $aviso = config('mail.default') === 'smtp' ? DiagnosticoSmtp::avisoRemitente($remitente, $datos['usuario']) : null;

        try {
            $enviado = Mail::raw("Este es un correo de prueba de Remates Colliers.\n\nSi lo recibiste, el correo saliente llega a destino.",
                fn ($m) => $m->to($destino)->subject('Prueba de correo · Remates Colliers'));
        } catch (Throwable $e) {
            report($e);
            // Mismo diccionario que «Probar conexión»: nada de «error al enviar».
            $mensaje = app(DiagnosticoSmtp::class)->explicarError($e, $datos);
            $respuesta = method_exists($e, 'getDebug') ? DiagnosticoSmtp::respuestaFinal((string) $e->getDebug()) : null;
            $this->anotarPrueba($destino, $remitente, 'fallida', $respuesta, null, $e->getMessage());

            return back()->with('error', trim("El servidor rechazó el correo de prueba. {$mensaje}"
                . ($respuesta ? " Respuesta del servidor: «{$respuesta}»." : '')
                . ($aviso ? " {$aviso}" : '')));
        }

        $modo = config('mail.default');
        // Solo se puede afirmar lo que dijo el servidor: que ACEPTÓ el mensaje. La entrega depende de lo que pase después.
        $symfony = $enviado?->getSymfonySentMessage();
        $respuesta = DiagnosticoSmtp::respuestaFinal((string) $symfony?->getDebug());
        $id = $symfony?->getMessageId();
        // La prueba también queda en el registro de correos: es donde se mira después.
        $this->anotarPrueba($destino, $remitente, in_array($modo, ['log', 'array', 'null'], true) ? 'registrada' : 'aceptada', $respuesta, $id, null);

        if ($modo === 'log') {
            return back()->with('estado', "Correo de prueba registrado en el log (modo «{$modo}»): NO salió a Internet. En el servidor: tail -n 50 storage/logs/laravel-AAAA-MM-DD.log");
        }

        return back()->with('estado', trim("El servidor de salida ACEPTÓ el mensaje para {$destino}, enviado desde «{$remitente}» por «{$modo}»."
            . ($respuesta ? " Respuesta del servidor: «{$respuesta}»." : ' El transporte no devolvió una respuesta SMTP.')
            . ($id ? " Identificador del mensaje: {$id}." : '')
            . ' Que el servidor lo acepte no garantiza que llegue a la bandeja: puede rebotar después o quedar filtrado como spam.'
            . ' Revisa el buzón de destino, su carpeta de spam y «Registro de correos».'
            . ($aviso ? " Además: {$aviso}" : '')));
    }

    public function actualizarUf(): RedirectResponse
    {
        $codigo = Artisan::call('colliers:actualizar-uf', ['--forzar' => true]);
        $salida = trim(Artisan::output());

        return back()->with($codigo === 0 ? 'estado' : 'error', $salida);
    }

    /** El correo de prueba queda en la misma bitácora que los demás, con lo que respondió el transporte. */
    private function anotarPrueba(string $destino, string $remitente, string $estado, ?string $respuesta, ?string $id, ?string $error): void
    {
        try {
            NotificacionLog::create([
                'canal' => 'correo',
                'transporte' => (string) config('mail.default'),
                'tipo' => 'PruebaDeCorreo',
                'destinatario' => $destino,
                'remitente' => $remitente ?: null,
                'asunto' => 'Prueba de correo · Remates Colliers',
                'estado' => $estado,
                'respuesta' => $respuesta === null ? null : mb_substr($respuesta, 0, 500),
                'message_id' => $id,
                'error' => $error === null ? null : mb_substr($error, 0, 2000),
                'enviada_en' => $estado === 'aceptada' ? now('UTC') : null,
            ]);
        } catch (Throwable $e) {
            report($e);
        }
    }

    /** @return array{host: string, puerto: int, cifrado: string, usuario: ?string, clave: ?string} */
    private function datosSmtp(): array
    {
        $config = (array) config('mail.mailers.smtp');

        return [
            'host' => (string) ($config['host'] ?? ''),
            'puerto' => (int) ($config['port'] ?? 0),
            'cifrado' => ($config['scheme'] ?? 'smtp') === 'smtps' ? 'ssl' : (($config['auto_tls'] ?? true) ? 'tls' : 'ninguno'),
            'usuario' => $config['username'] ?? null,
            'clave' => $config['password'] ?? null,
        ];
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
