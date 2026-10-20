{{--
    Conmutador EN / ES del diseño (cabecera pública), reutilizado en las cabeceras de ingreso, registro, mi cuenta y la
    sala (20/10) para que nadie quede atrapado en un idioma. El activo va resaltado (span); el otro es el enlace a la
    misma página en ese idioma.
--}}
<span {{ $attributes->merge(['class' => 'pub-cabecera__idioma']) }}>@if (\App\Support\Idioma::esIngles())<span>EN</span> / <a href="{{ \App\Support\Idioma::urlConmutador('es') }}" hreflang="es" lang="es">ES</a>@else<a href="{{ \App\Support\Idioma::urlConmutador('en') }}" hreflang="en" lang="en">EN</a> / <span>ES</span>@endif</span>
