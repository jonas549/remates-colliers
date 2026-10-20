@props([
    'titulo' => null,
    'paginaCompleta' => false,
    'claseCuerpo' => '',
])
<!DOCTYPE html>
<html lang="{{ \App\Support\Idioma::actual() }}" @class(['pagina-completa' => $paginaCompleta])>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $titulo ? $titulo . ' · ' : '' }}{{ __('sitio.titulo_sufijo') }}</title>
    {{-- Ícono del sitio de Colliers (activosespeciales.colliers.cl). Este layout es el único <head>: el panel lo envuelve. --}}
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('apple-touch-icon.png') }}">
    <link rel="manifest" href="{{ asset('site.webmanifest') }}">
    <meta name="theme-color" content="#25408f">
    {{-- Sitio en inglés (20/10): la misma página en el otro idioma; el español es la versión por defecto. --}}
    @foreach (\App\Support\Idioma::alternativas() as $codigo => $href)
        <link rel="alternate" hreflang="{{ $codigo }}" href="{{ $href }}">
    @endforeach
    @if (\App\Support\Idioma::alternativas())
        <link rel="alternate" hreflang="x-default" href="{{ \App\Support\Idioma::alternativas()['es'] }}">
    @endif
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('cabeza')
</head>
<body class="{{ $claseCuerpo }}">
    <div class="sc-host">
        {{ $slot }}
    </div>
    @unless (request()->is('admin', 'admin/*'))
        <script type="application/json" id="textos-js">@json((object) \App\Support\Idioma::textosJs())</script>
    @endunless
    @stack('scripts')
</body>
</html>
