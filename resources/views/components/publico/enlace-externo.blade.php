{{--
    Enlace a una página fuera de esta aplicación (sitio corporativo de Colliers, redes, enlaces legales), en pestaña nueva.
    Sin $href (la página no existe todavía) queda el texto con el mismo aspecto, sin enlace.
--}}
@props(['href' => null])
@if ($href)
<a {{ $attributes->merge(['href' => $href, 'target' => '_blank', 'rel' => 'noopener']) }}>{{ $slot }}</a>
@else
<a {{ $attributes }}>{{ $slot }}</a>
@endif
