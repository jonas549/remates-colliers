<?php

namespace App\Remates;

use App\Models\AccessLog;
use App\Models\Adjudicacion;
use App\Models\Garantia;
use App\Models\Lote;
use App\Models\Postor;
use App\Models\Puja;
use App\Models\PujaIntento;
use App\Models\NotificacionLog;
use App\Models\Remate;
use App\Models\Suscripcion;
use App\Models\User;
use App\Subastas\Difusion\Emisor;
use App\Support\Rut;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Remate de prueba listo para usar: publicado, con lotes, con postores aprobados y garantía aprobada, y con la clave
 * común que se muestra UNA vez. Lo usan `colliers:remate-demo` (terminal) y el botón «Crear subasta de prueba» del
 * panel, para no llenar quince campos a mano cada vez que hay que probar algo.
 *
 * Queda marcado `es_demostracion`: no aparece en el sitio público, no entra en los reportes y no envía correos.
 * Nunca en producción.
 */
class RemateDePrueba
{
    public function __construct(private readonly Emisor $emisor) {}

    /**
     * @return array{remate: Remate, clave: string, postores: list<array{numero: int, email: string, rut: string}>}
     */
    public function crear(int $postores = 3, int $lotes = 3, int $inicioMinutos = 12, int $duracionMinutos = 8, int $pausaMinutos = 1, int $garantiaAntesMinutos = 4): array
    {
        if (app()->isProduction()) {
            throw new DomainException('No se crean remates de prueba en producción. En el sandbox usa APP_ENV=staging.');
        }
        if ($postores < 1 || $postores > 50 || $lotes < 1 || $lotes > 10 || $inicioMinutos < 1 || $duracionMinutos < 1 || $pausaMinutos < 0) {
            throw new DomainException('Opciones fuera de rango: postores 1–50, lotes 1–10, inicio ≥ 1 min, duración ≥ 1 min, pausa ≥ 0.');
        }

        $sufijo = CarbonImmutable::now('America/Santiago')->format('ymd-Hi') . '-' . Str::lower(Str::random(3));
        $clave = Str::password(16, symbols: false);
        $ahora = CarbonImmutable::now('UTC');
        $inicio = $ahora->addMinutes($inicioMinutos)->startOfMinute();

        [$remate, $credenciales] = DB::transaction(function () use ($sufijo, $clave, $postores, $lotes, $inicio, $duracionMinutos, $pausaMinutos, $garantiaAntesMinutos, $ahora) {
            $remate = Remate::create([
                // `remates.folio` es VARCHAR(20): «PR-» + 15 = 18. Con «PRUEBA-» eran 22 y MariaDB lo rechazaba (21/09).
                'folio' => 'PR-' . strtoupper($sufijo),
                'slug' => 'prueba-' . $sufijo,
                'titulo' => 'PRUEBA · no es un remate real',
                'descripcion' => 'Remate creado desde el panel para probar la plataforma. No aparece en el sitio público ni en los reportes.',
                'estado' => Remate::ESTADO_PUBLICADO,
                'inicio_en' => $inicio,
                // Unos minutos antes del inicio: alcanza a inscribirse alguien más a mano si hace falta.
                'cierre_garantias_en' => $inicio->subMinutes($garantiaAntesMinutos),
                'duracion_lote_segundos' => $duracionMinutos * 60,
                'pausa_entre_lotes_segundos' => $pausaMinutos * 60,
                'publicado_en' => $ahora,
            ]);
            $remate->forceFill(['es_demostracion' => true])->save();

            $direcciones = [
                ['Av. Apoquindo 4501, Depto. 1802', 'Las Condes', 'Departamento', 120000000],
                ['Los Militares 5620, Depto. 703', 'Las Condes', 'Departamento', 98000000],
                ['Manuel Montt 1740, Depto. 505', 'Providencia', 'Departamento', 84000000],
            ];
            for ($orden = 1; $orden <= $lotes; $orden++) {
                [$direccion, $comuna, $tipo, $base] = $direcciones[($orden - 1) % count($direcciones)];
                Lote::create([
                    'remate_id' => $remate->id,
                    'orden' => $orden,
                    'titulo' => "Lote de prueba {$orden}",
                    'direccion' => $direccion . ($orden > count($direcciones) ? " ({$orden})" : ''),
                    'comuna' => $comuna,
                    'region' => 'Región Metropolitana',
                    'tipo_propiedad' => $tipo,
                    'superficie_util' => 82,
                    'dormitorios' => 3,
                    'banos' => 2,
                    'estacionamientos' => 1,
                    'bodega' => true,
                    'ocupacion' => 'Desocupada',
                    'precio_base' => $base,
                ]);
            }
            $remate->programarLotes();

            $credenciales = [];
            for ($i = 1; $i <= $postores; $i++) {
                $email = "prueba{$i}-{$sufijo}@demo.colliers.test";
                $user = User::create(['name' => "Postor de prueba {$i}", 'email' => $email, 'password' => $clave, 'rol' => User::ROL_POSTOR, 'estado' => User::ESTADO_ACTIVO]);
                $user->forceFill(['email_verified_at' => $ahora])->save();
                $postor = Postor::create(['user_id' => $user->id, 'nombres' => 'Postor de prueba', 'apellidos' => (string) $i, 'rut' => $this->rutLibre()]);
                $postor->forceFill(['estado' => Postor::ESTADO_APROBADO, 'revisado_en' => $ahora])->save();
                Garantia::paraRemate($remate, $user)->forceFill(['estado' => Garantia::ESTADO_APROBADA, 'revisado_en' => $ahora])->save();
                $credenciales[] = ['numero' => $i, 'email' => $email, 'rut' => $postor->rut];
            }

            return [$remate, $credenciales];
        });

        $this->emisor->publicarEstado($remate);

        return ['remate' => $remate->fresh(['lotes']), 'clave' => $clave, 'postores' => $credenciales];
    }

    /** Borra un remate de prueba con todo lo suyo: sirve para no dejar basura después de probar. */
    public function borrar(Remate $remate): void
    {
        if (! $remate->es_demostracion) {
            throw new DomainException('Solo se borran remates de prueba.');
        }

        $usuarios = $remate->garantias()->pluck('user_id')->all();

        DB::transaction(function () use ($remate, $usuarios) {
            foreach ($remate->lotes as $lote) {
                Adjudicacion::where('lote_id', $lote->id)->delete();
                // Las pujas de un remate REAL no se borran nunca (el modelo lo impide). Aquí se borran por consulta,
                // sin eventos de modelo, y solo porque este remate es de prueba: lo garantiza el es_demostracion de arriba.
                Puja::where('lote_id', $lote->id)->delete();
                PujaIntento::where('lote_id', $lote->id)->delete();
                $lote->imagenes()->delete();
                $lote->visitas()->delete();
            }
            $remate->documentos()->delete();
            $remate->garantias()->delete();
            $remate->lotes()->delete();
            $remate->delete();

            // Los postores de prueba solo existen para este remate: se borran con su rastro (la bitácora de accesos y
            // la de correos apuntan a `users` con restrict, y la sesión abierta también).
            foreach (User::whereIn('id', $usuarios)->where('email', 'like', '%@demo.colliers.test')->get() as $user) {
                $user->postor?->documentos()->delete();
                $user->postor?->delete();
                AccessLog::where('user_id', $user->id)->delete();
                NotificacionLog::where('user_id', $user->id)->delete();
                Suscripcion::where('email', $user->email)->delete();
                DB::table('sessions')->where('user_id', $user->id)->delete();
                $user->delete();
            }
        });

        // El JSON público del remate deja de tener sentido cuando el remate ya no existe.
        try {
            $archivo = rtrim((string) config('colliers.tiempo_real.carpeta'), '/\\') . DIRECTORY_SEPARATOR . $remate->slug . '.json';
            if (is_file($archivo)) {
                @unlink($archivo);
            }
        } catch (Throwable $e) {
            report($e);
        }
    }

    /** RUT válido que no existe todavía (rango alto para no chocar con RUT reales de prueba). */
    private function rutLibre(): string
    {
        do {
            $cuerpo = (string) random_int(90000000, 99999999);
            $rut = $cuerpo . Rut::digitoVerificador($cuerpo);
        } while (Postor::porRut($rut)->exists());

        return $rut;
    }
}
