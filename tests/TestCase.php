<?php

namespace Tests;

use App\Models\Configuracion;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // La memoria corta de Configuracion::valor() es estática: cada prueba parte con base nueva.
        Configuracion::olvidar();
    }
}
