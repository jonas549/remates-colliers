<?php

namespace App\Support;

use Illuminate\Routing\UrlGenerator;

/**
 * Generador de URL que respeta el idioma actual: en una página en inglés, route('x') da la ruta «en.x» si existe.
 * Así las vistas, las redirecciones y Fortify no tienen que saber del idioma. Una ruta sin versión en inglés (el panel)
 * sigue dando la española.
 */
class UrlLocalizada extends UrlGenerator
{
    public function route($name, $parameters = [], $absolute = true)
    {
        if (is_string($name) && Idioma::esIngles() && ! str_starts_with($name, Idioma::PREFIJO . '.')
            && $this->routes->hasNamedRoute(Idioma::PREFIJO . '.' . $name)) {
            $name = Idioma::PREFIJO . '.' . $name;
        }

        return parent::route($name, $parameters, $absolute);
    }
}
