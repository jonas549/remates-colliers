<?php

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schedule;

/*
 * Programador de tareas. En el servidor hay UNA sola línea de cron que ejecuta `schedule:run` cada minuto;
 * toda tarea periódica nueva se agrega aquí, nunca como cron adicional (el hosting no tiene Supervisor).
 */

// Latido: permite a colliers:diagnostico confirmar que el cron está corriendo.
Schedule::call(function () {
    $archivo = config('colliers.latido_programador');
    File::ensureDirectoryExists(dirname($archivo));
    touch($archivo);
})->name('latido-programador')->everyMinute();

// Cola de trabajos (correos y demás) procesada por cron: termina cuando la cola queda vacía y nunca dura más
// de 50 s, para no superponerse con la ejecución del minuto siguiente.
Schedule::command('queue:work --stop-when-empty --max-time=50 --tries=3 --backoff=60')
    ->name('cola-por-minuto')
    ->everyMinute()
    ->withoutOverlapping(5);
