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
