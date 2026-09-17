<?php

/*
 * Ayudante SOLO LOCAL de tools/comparar/sala-real.mjs: prepara y consulta el remate en vivo del seeder.
 *   php tools/comparar/sala-ayudante.php reiniciar      migrate:fresh --seed y borra los JSON publicados
 *   php tools/comparar/sala-ayudante.php estado         lote de «apoquindo» en JSON
 *   php tools/comparar/sala-ayudante.php cerrar-en N    cierra_en = ahora + N s y publica el estado
 *   php tools/comparar/sala-ayudante.php dos-lotes N    remate «dos-lotes»: lote 1 cierra en N s, lote 2 abre 3 s después
 *   php tools/comparar/sala-ayudante.php mensaje SLUG T mensaje del martillero en ese remate
 */

require __DIR__ . '/../../vendor/autoload.php';
$app = require __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Adjudicacion;
use App\Models\Garantia;
use App\Models\Lote;
use App\Models\Remate;
use App\Models\User;
use App\Subastas\Difusion\Emisor;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

if (! app()->isLocal()) {
    fwrite(STDERR, "Solo en APP_ENV=local.\n");
    exit(1);
}

$accion = $argv[1] ?? '';
$lote = fn () => Remate::where('slug', 'apoquindo')->firstOrFail()->lotes()->first();

switch ($accion) {
    case 'reiniciar':
        Artisan::call('migrate:fresh', ['--seed' => true, '--force' => true]);
        foreach (File::glob(public_path('tiempo-real') . '/*.{json,tmp}', GLOB_BRACE) as $archivo) {
            File::delete($archivo);
        }
        echo "ok\n";
        break;

    case 'estado':
        $l = $lote();
        echo json_encode([
            'estado' => $l->estado,
            'precio_actual' => $l->precio_actual,
            'total_pujas' => $l->total_pujas,
            'ganador' => $l->ganador?->email,
            'cierra_en_ms' => (int) $l->cierra_en->format('Uv'),
            'adjudicado_a' => Adjudicacion::where('lote_id', $l->id)->first()?->user?->email,
        ]) . "\n";
        break;

    case 'cerrar-en':
        $l = $lote();
        $l->cierra_en = CarbonImmutable::now('UTC')->addSeconds((int) ($argv[2] ?? 5));
        $l->save();
        app(Emisor::class)->publicarEstado($l->remate);
        echo "ok\n";
        break;

    case 'dos-lotes':
        $ahora = CarbonImmutable::now('UTC');
        $cierre = $ahora->addSeconds((int) ($argv[2] ?? 8));
        $remate = Remate::create(['folio' => 'R-DOS-LOTES', 'slug' => 'dos-lotes', 'titulo' => 'Remate de dos lotes', 'estado' => Remate::ESTADO_EN_CURSO]);
        foreach ([1 => [$ahora->subMinute(), $cierre], 2 => [$cierre->addSeconds(3), $cierre->addMinutes(5)]] as $orden => [$abre, $cierra]) {
            Lote::create([
                'remate_id' => $remate->id, 'orden' => $orden, 'titulo' => "Lote {$orden}", 'direccion' => "Dirección del lote {$orden}",
                'comuna' => 'Las Condes', 'region' => 'Región Metropolitana', 'precio_base' => 50000000 * $orden, 'abre_en' => $abre, 'cierra_en' => $cierra,
            ]);
        }
        foreach (['mpgonzalez@correo.test', 'contacto@andes.test'] as $correo) {
            Garantia::paraRemate($remate, User::where('email', $correo)->firstOrFail())->forceFill(['estado' => Garantia::ESTADO_APROBADA])->save();
        }
        app(Emisor::class)->publicarEstado($remate);
        echo "ok\n";
        break;

    case 'mensaje':
        $remate = Remate::where('slug', $argv[2] ?? '')->firstOrFail();
        $remate->update(['mensaje_martillero' => $argv[3] ?? '', 'mensaje_martillero_en' => CarbonImmutable::now('UTC')]);
        app(Emisor::class)->publicarEstado($remate);
        echo "ok\n";
        break;

    case 'pujar':
        // pujar SLUG CORREO MONTO: una puja real por el motor (lo que haría la sala), para ver al espectador actualizarse.
        $remate = Remate::where('slug', $argv[2] ?? '')->firstOrFail();
        $user = User::where('email', $argv[3] ?? '')->firstOrFail();
        $puja = app(App\Subastas\MotorPujas::class)->pujar($user, $remate->lotes()->first()->id, (int) ($argv[4] ?? 0), CarbonImmutable::now('UTC'));
        echo "ok {$puja->monto}\n";
        break;

    default:
        fwrite(STDERR, "Acción desconocida.\n");
        exit(1);
}
