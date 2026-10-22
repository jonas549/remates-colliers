<?php

namespace App\Console\Commands;

use App\Autenticacion\SegundoFactor;
use App\Models\AccessLog;
use App\Models\CodigoIngreso as Codigo;
use App\Models\Configuracion;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

/**
 * Salida de emergencia del segundo factor (22/10): si el correo del servidor no funciona, nadie de Administración puede
 * entrar al panel, ni siquiera para arreglar el correo. Quien tiene la terminal del servidor (un factor más fuerte que
 * el correo) genera aquí el código: la persona ingresa su clave en /admin/ingresar como siempre y en «Revisa tu correo»
 * escribe este código. Mismas reglas que el del correo (vigencia, un solo uso, intentos) y queda en access_logs.
 */
class CodigoIngreso extends Command
{
    protected $signature = 'colliers:codigo-ingreso {email}';

    protected $description = 'Genera un código de ingreso al panel cuando el correo no funciona (emergencia)';

    public function handle(): int
    {
        $user = User::where('email', mb_strtolower(trim($this->argument('email'))))->first();
        if ($user === null || ! SegundoFactor::exige($user)) {
            $this->error('No hay una cuenta activa con ese correo que pida código de ingreso.');

            return self::FAILURE;
        }

        $ahora = CarbonImmutable::now('UTC');
        $minutos = max(1, (int) Configuracion::valor('dos_pasos_minutos'));
        $codigo = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        Codigo::where('user_id', $user->id)->whereNull('usado_en')->whereNull('descartado_en')->update(['descartado_en' => $ahora->format('Y-m-d H:i:s')]);
        Codigo::create(['user_id' => $user->id, 'codigo_hash' => Hash::make($codigo), 'expira_en' => $ahora->addMinutes($minutos), 'user_agent' => 'terminal']);
        AccessLog::create(['user_id' => $user->id, 'email' => $user->email, 'evento' => 'segundo_factor_terminal', 'detalle' => ['minutos' => $minutos]]);

        $this->info("Código de ingreso para {$user->email}: {$codigo} (vence en {$minutos} minutos, sirve una vez).");
        $this->line('Ingresa la clave en /admin/ingresar y escribe este código en «Revisa tu correo».');

        return self::SUCCESS;
    }
}
