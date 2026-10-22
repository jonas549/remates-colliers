<?php

namespace App\Autenticacion;

use App\Models\AccessLog;
use App\Models\CodigoIngreso;
use App\Models\Configuracion;
use App\Models\DispositivoConfiable;
use App\Models\User;
use App\Notifications\CodigoIngresoAviso;
use App\Support\Sitio;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Throwable;

/**
 * Segundo factor por correo para el panel (22/10).
 *
 * Al validar usuario y clave de una cuenta que lo exige (Permisos::MAPA['dos_pasos']), no se abre sesión: se guarda en
 * la sesión «quién está ingresando» y se envía un código de 6 dígitos al correo de la cuenta. Sin el código correcto no
 * hay sesión. Si el correo no sale, la pantalla lo dice y no se deja entrar.
 *
 * - Código: 6 dígitos al azar, guardado como hash, vigencia corta, un solo uso, intentos limitados por código.
 * - Envíos: uno por minuto y un tope por hora y cuenta (ingresos y reenvíos), para que no sirva para llenar un buzón.
 * - «Recordar este dispositivo»: cookie con un token al azar (en la base, su hash), por unos días; se revoca desde
 *   «Sesiones activas» y al cambiar la contraseña o cerrar sus sesiones desde «06 Usuarios».
 * - Todo queda en `access_logs`; el correo, en `notificaciones_log` como cualquier otro.
 */
final class SegundoFactor
{
    public const SESION = 'segundo_factor';

    public const COOKIE = 'colliers_dispositivo';

    /** Segundos entre un envío y el siguiente. */
    public const ESPERA_REENVIO = 60;

    /** Cuánto dura el «ingreso a medias» (clave validada, falta el código) antes de pedir la clave otra vez. */
    public const MINUTOS_PENDIENTE = 30;

    /** Transportes que no envían nada a Internet: fuera de local y pruebas, un código por ahí nunca llega. */
    private const SIN_SALIDA = ['log', 'array', 'null'];

    public static function exige(User $user): bool
    {
        return Permisos::permite($user, 'dos_pasos');
    }

    /** Dispositivo recordado válido para esta cuenta en este navegador, o null. */
    public function dispositivoRecordado(Request $request, User $user): ?DispositivoConfiable
    {
        [$id, $token] = array_pad(explode('|', (string) $request->cookie(self::COOKIE), 2), 2, '');
        if (! ctype_digit($id) || $token === '') {
            return null;
        }
        $dispositivo = DispositivoConfiable::vigentes()->where('user_id', $user->id)->find((int) $id);
        if ($dispositivo === null || ! hash_equals($dispositivo->token_hash, hash('sha256', $token))) {
            return null;
        }
        $dispositivo->forceFill(['ultimo_uso_en' => CarbonImmutable::now('UTC')])->save();

        return $dispositivo;
    }

    /** Clave validada: deja el ingreso pendiente del código y envía el primero. Devuelve el error del envío, si lo hubo. */
    public function iniciar(Request $request, User $user, bool $recordarSesion): ?string
    {
        $request->session()->put(self::SESION, [
            'user_id' => $user->id,
            'remember' => $recordarSesion,
            'desde' => CarbonImmutable::now('UTC')->getTimestamp(),
        ]);

        // Volver a escribir la clave siempre manda un código nuevo (sin la espera del reenvío); el tope por hora sí cuenta.
        return $this->enviar($request, $user, respetarEspera: false);
    }

    /** Cuenta que validó su clave y espera el código, o null si no hay ingreso pendiente (o venció). */
    public function pendiente(Request $request): ?User
    {
        $datos = $request->session()->get(self::SESION);
        if (! is_array($datos) || CarbonImmutable::now('UTC')->getTimestamp() - (int) ($datos['desde'] ?? 0) > self::MINUTOS_PENDIENTE * 60) {
            return null;
        }
        $user = User::find($datos['user_id'] ?? null);

        return $user !== null && $user->estado === User::ESTADO_ACTIVO && self::exige($user) ? $user : null;
    }

    public function recordarSesion(Request $request): bool
    {
        return (bool) ($request->session()->get(self::SESION)['remember'] ?? false);
    }

    public function olvidarPendiente(Request $request): void
    {
        $request->session()->forget(self::SESION);
    }

    /** Envía un código nuevo (descarta los anteriores). Devuelve el mensaje de error para la pantalla, o null si salió. */
    public function enviar(Request $request, User $user, bool $respetarEspera = true): ?string
    {
        $ahora = CarbonImmutable::now('UTC');
        $ultimo = CodigoIngreso::where('user_id', $user->id)->latest('id')->first();
        if ($respetarEspera && $ultimo !== null && $ultimo->created_at->greaterThan($ahora->subSeconds(self::ESPERA_REENVIO))) {
            $segundos = self::ESPERA_REENVIO - $ahora->getTimestamp() + $ultimo->created_at->getTimestamp();

            return __('acceso.dos_pasos.espera', ['segundos' => max(1, $segundos)]);
        }
        $maximo = max(1, (int) Configuracion::valor('dos_pasos_envios_hora'));
        $enLaHora = CodigoIngreso::where('user_id', $user->id)->where('created_at', '>', $ahora->subHour()->format('Y-m-d H:i:s'))->count();
        if ($enLaHora >= $maximo) {
            $this->anotar($request, $user, 'segundo_factor_tope', ['envios' => $enLaHora]);

            return __('acceso.dos_pasos.tope', ['envios' => $maximo, 'correo' => Sitio::correo()]);
        }

        CodigoIngreso::where('user_id', $user->id)->whereNull('usado_en')->whereNull('descartado_en')
            ->update(['descartado_en' => $ahora->format('Y-m-d H:i:s')]);

        $minutos = max(1, (int) Configuracion::valor('dos_pasos_minutos'));
        $codigo = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $registro = CodigoIngreso::create([
            'user_id' => $user->id,
            'codigo_hash' => Hash::make($codigo),
            'expira_en' => $ahora->addMinutes($minutos),
            'ip' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 512),
        ]);

        $transporte = (string) config('mail.default');
        if (in_array($transporte, self::SIN_SALIDA, true) && ! config('colliers.seguridad.codigo_por_log')) {
            // El correo «saldría» a un archivo del servidor: decir que lo enviamos sería falso.
            $registro->forceFill(['descartado_en' => $ahora])->save();
            $this->anotar($request, $user, 'segundo_factor_envio_fallido', ['motivo' => 'transporte_sin_salida', 'transporte' => $transporte]);

            return __('acceso.dos_pasos.sin_correo', ['correo' => Sitio::correo()]);
        }

        try {
            // Síncrono, no por la cola del cron: quien ingresa está esperando el código, y si falla hay que decirlo ahora.
            $user->notifyNow(new CodigoIngresoAviso($user, $codigo, $minutos));
        } catch (Throwable $e) {
            report($e);
            $registro->forceFill(['descartado_en' => $ahora])->save();
            $this->anotar($request, $user, 'segundo_factor_envio_fallido', ['motivo' => mb_substr($e->getMessage(), 0, 300)]);

            return __('acceso.dos_pasos.envio_fallido', ['correo' => Sitio::correo()]);
        }

        $this->anotar($request, $user, 'segundo_factor_enviado', ['minutos' => $minutos]);

        return null;
    }

    /** Revisa el código del ingreso pendiente. Devuelve el error para la pantalla, o null si es correcto (y lo gasta). */
    public function verificar(Request $request, User $user, string $codigo): ?string
    {
        $ahora = CarbonImmutable::now('UTC');
        $vigente = CodigoIngreso::where('user_id', $user->id)->whereNull('usado_en')->whereNull('descartado_en')->latest('id')->first();
        if ($vigente === null || $vigente->expira_en->lessThanOrEqualTo($ahora)) {
            $this->anotar($request, $user, 'segundo_factor_fallido', ['motivo' => 'vencido']);

            return __('acceso.dos_pasos.vencido');
        }

        $maximo = max(1, (int) Configuracion::valor('dos_pasos_intentos'));
        if (! preg_match('/^\d{6}$/', $codigo) || ! Hash::check($codigo, $vigente->codigo_hash)) {
            // Incremento en la base: dos intentos simultáneos no se pisan la cuenta.
            CodigoIngreso::whereKey($vigente->id)->increment('intentos');
            $intentos = (int) CodigoIngreso::whereKey($vigente->id)->value('intentos');
            if ($intentos >= $maximo) {
                $vigente->forceFill(['descartado_en' => $ahora])->save();
                $this->anotar($request, $user, 'segundo_factor_agotado', ['intentos' => $intentos]);

                return __('acceso.dos_pasos.agotado');
            }
            $this->anotar($request, $user, 'segundo_factor_fallido', ['motivo' => 'incorrecto', 'intentos' => $intentos]);
            $quedan = $maximo - $intentos;

            return trans_choice('acceso.dos_pasos.incorrecto', $quedan, ['quedan' => $quedan]);
        }

        // Un solo uso: solo lo gasta quien lo marca primero.
        $gastado = CodigoIngreso::whereKey($vigente->id)->whereNull('usado_en')->update(['usado_en' => $ahora->format('Y-m-d H:i:s')]);
        if ($gastado === 0) {
            return __('acceso.dos_pasos.vencido');
        }
        $this->anotar($request, $user, 'segundo_factor_correcto');

        return null;
    }

    /** Recuerda este navegador por los días configurados (0 = la opción no se ofrece). */
    public function recordarDispositivo(Request $request, User $user): void
    {
        $dias = self::diasRecordar();
        if ($dias < 1) {
            return;
        }
        $token = Str::random(48);
        $dispositivo = DispositivoConfiable::create([
            'user_id' => $user->id,
            'token_hash' => hash('sha256', $token),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 512),
            'ip' => $request->ip(),
            'expira_en' => CarbonImmutable::now('UTC')->addDays($dias),
        ]);
        Cookie::queue(Cookie::make(self::COOKIE, $dispositivo->id . '|' . $token, $dias * 24 * 60, httpOnly: true, sameSite: 'lax'));
        $this->anotar($request, $user, 'dispositivo_recordado', ['dispositivo' => $dispositivo->id, 'dias' => $dias]);
    }

    public static function diasRecordar(): int
    {
        return max(0, (int) Configuracion::valor('dos_pasos_recordar_dias'));
    }

    /** Revoca todos los dispositivos recordados de la cuenta. Devuelve cuántos. */
    public static function olvidarDispositivos(User $user): int
    {
        return DispositivoConfiable::vigentes()->where('user_id', $user->id)->update(['revocado_en' => CarbonImmutable::now('UTC')->format('Y-m-d H:i:s')]);
    }

    /** «c*****@colliers.cl»: a dónde se envió, sin mostrar el correo completo antes de tener sesión. */
    public static function correoEnmascarado(User $user): string
    {
        [$nombre, $dominio] = array_pad(explode('@', (string) $user->email, 2), 2, '');

        return mb_substr($nombre, 0, 1) . str_repeat('•', max(3, min(8, mb_strlen($nombre) - 1))) . '@' . $dominio;
    }

    private function anotar(Request $request, User $user, string $evento, array $detalle = []): void
    {
        AccessLog::create([
            'user_id' => $user->id,
            'email' => $user->email,
            'evento' => $evento,
            'ip' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 512),
            'detalle' => $detalle + ['portal' => 'administracion'],
        ]);
    }
}
