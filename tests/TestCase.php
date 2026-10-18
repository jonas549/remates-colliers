<?php

namespace Tests;

use App\Models\Configuracion;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\File;

abstract class TestCase extends BaseTestCase
{
    protected string $carpetaPresencia;

    protected function setUp(): void
    {
        parent::setUp();
        // La memoria corta de Configuracion::valor() es estática: cada prueba parte con base nueva.
        Configuracion::olvidar();
        // La presencia en la sala va a archivos: cada proceso de pruebas usa su carpeta, nunca el storage real.
        $this->carpetaPresencia = storage_path('framework/testing/presencia-' . getmypid());
        config([
            'colliers.presencia.carpeta' => $this->carpetaPresencia,
            'colliers.presencia.ajustes' => $this->carpetaPresencia . '/ajustes.json',
        ]);
    }

    protected function tearDown(): void
    {
        if (isset($this->carpetaPresencia)) {
            File::deleteDirectory($this->carpetaPresencia);
        }
        parent::tearDown();
    }
}
