@props([
    'titulo' => null,
    'paginaCompleta' => false,
    'claseCuerpo' => '',
])
<!DOCTYPE html>
<html lang="es" @class(['pagina-completa' => $paginaCompleta])>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $titulo ? $titulo . ' · ' : '' }}Remates Colliers</title>
    {{-- Ícono del sitio de Colliers (activosespeciales.colliers.cl). Este layout es el único <head>: el panel lo envuelve. --}}
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('apple-touch-icon.png') }}">
    <link rel="manifest" href="{{ asset('site.webmanifest') }}">
    <meta name="theme-color" content="#25408f">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('cabeza')
</head>
<body class="{{ $claseCuerpo }}">
    <div class="sc-host">
        {{ $slot }}
    </div>
    @stack('scripts')
</body>
</html>
