@props([
    'titulo' => null,
    'paginaCompleta' => false,
    'claseCuerpo' => '',
    // SEO y vista previa al compartir (QA 21/10, 3.4). Sin valores, los del sitio (sitio.seo) y el logo.
    'descripcion' => null,
    'imagen' => null,
])
@php
    $tituloCompleto = ($titulo ? $titulo . ' · ' : '') . __('sitio.titulo_sufijo');
    $descripcionSeo = \Illuminate\Support\Str::limit(trim(preg_replace('/\s+/', ' ', $descripcion ?: __('sitio.seo.descripcion'))), 200);
@endphp
<!DOCTYPE html>
<html lang="{{ \App\Support\Idioma::actual() }}" @class(['pagina-completa' => $paginaCompleta])>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $tituloCompleto }}</title>
    @unless (request()->is('admin', 'admin/*'))
        <meta name="description" content="{{ $descripcionSeo }}">
        <meta property="og:type" content="website">
        <meta property="og:site_name" content="{{ __('sitio.titulo_sufijo') }}">
        <meta property="og:locale" content="{{ \App\Support\Idioma::esIngles() ? 'en_US' : 'es_CL' }}">
        <meta property="og:title" content="{{ $tituloCompleto }}">
        <meta property="og:description" content="{{ $descripcionSeo }}">
        <meta property="og:url" content="{{ url()->current() }}">
        @if ($imagen)
            <meta property="og:image" content="{{ $imagen }}">
            <meta name="twitter:card" content="summary_large_image">
        @else
            {{-- public/img/compartir.(jpg|png): reemplazar el archivo basta, las medidas salen de él. --}}
            @php($compartir = \App\Support\Sitio::imagenCompartir())
            <meta property="og:image" content="{{ $compartir['url'] }}">
            @if ($compartir['ancho'])
                <meta property="og:image:width" content="{{ $compartir['ancho'] }}">
                <meta property="og:image:height" content="{{ $compartir['alto'] }}">
            @endif
            <meta property="og:image:alt" content="{{ __('sitio.seo.imagen_alt') }}">
            <meta name="twitter:card" content="{{ $compartir['ancho'] >= 1000 ? 'summary_large_image' : 'summary' }}">
        @endif
    @endunless
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
