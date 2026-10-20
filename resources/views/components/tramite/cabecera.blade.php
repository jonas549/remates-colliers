@props(['etiqueta', 'ancho' => null])
<div {{ $attributes->merge(['class' => 'tramite-cabecera']) }} @if ($ancho) style="--tramite-ancho: {{ $ancho }}px" @endif>
    <div class="tramite-cabecera__interior">
        <a href="{{ route('remates.index') }}"><img src="{{ asset('img/colliers-logo.png') }}" alt="Colliers" class="tramite-cabecera__logo"></a>
        <span class="tramite-cabecera__separador"></span>
        <div class="tramite-cabecera__etiqueta">{{ $etiqueta }}</div>
        <div class="tramite-cabecera__acciones">
            <x-publico.idioma class="pub-cabecera__idioma--siempre" />
            {{ $slot }}
        </div>
    </div>
</div>
