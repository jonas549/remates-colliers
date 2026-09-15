{{-- Índice de revisión del Bloque T. No es parte del diseño: página interna para Jonas. --}}
<x-layouts.base titulo="Revisión del Bloque T">
    <div class="revision">
        <div class="revision__cabecera">
            <img src="{{ asset('img/colliers-logo.png') }}" alt="Colliers" class="revision__logo">
            <div>
                <div class="revision__kicker">BLOQUE T · REVISIÓN LOCAL</div>
                <h1 class="revision__titulo">Pantallas del traspaso a Blade</h1>
            </div>
        </div>

        <p class="revision__intro">Escritorio (1120–1440px) verificado 1:1 contra el prototipo. Tablet y móvil adaptados. Para ver el responsive: F12 → Ctrl+Shift+M.</p>

        @foreach ($grupos as $grupo)
            <h2 class="revision__grupo">{{ $grupo['grupo'] }}</h2>
            <div class="revision__lista">
                @foreach ($grupo['pantallas'] as $p)
                    <div class="revision__fila">
                        <div class="revision__nombre">
                            @if ($p['estado'] === 'terminada')
                                <a href="{{ url($p['ruta']) }}">{{ $p['nombre'] }}</a>
                            @else
                                <span class="revision__nombre--pendiente">{{ $p['nombre'] }}</span>
                            @endif
                            @isset($p['nota'])<div class="revision__nota">{{ $p['nota'] }}</div>@endisset
                            @isset($p['variantes'])
                                <div class="revision__variantes">
                                    @foreach ($p['variantes'] as $etiqueta => $ruta)
                                        <a href="{{ url($ruta) }}">{{ $etiqueta }}</a>
                                    @endforeach
                                </div>
                            @endisset
                        </div>
                        <span class="revision__estado revision__estado--{{ $p['estado'] }}">{{ strtoupper($p['estado']) }}</span>
                    </div>
                @endforeach
            </div>
        @endforeach
    </div>
</x-layouts.base>
