<?php

/*
 * Ayudante SOLO LOCAL de tools/comparar/sala-real.mjs: prepara y consulta el remate en vivo del seeder.
 *   php tools/comparar/sala-ayudante.php reiniciar      migrate:fresh --seed y borra los JSON publicados
 *   php tools/comparar/sala-ayudante.php estado         lote de «apoquindo» en JSON
 *   php tools/comparar/sala-ayudante.php cerrar-en N    cierra_en = ahora + N s y publica el estado
 */

require __DIR__ . '/../../vendor/autoload.php';
$app = require __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Adjudicacion;
use App\Models\Remate;
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

    default:
        fwrite(STDERR, "Acción desconocida.\n");
        exit(1);
}
