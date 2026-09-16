@props(['pagina', 'numero' => '01', 'kicker', 'titulo', 'intro' => null, 'proximo', 'alpine' => null])
{{--
    Marco del diseño del Login (columna con formulario + foto del próximo remate). Lo usan el Login y, por decisión
    del 16/09, las pantallas de autenticación que el diseño no tiene: mismo diseño, cambian título, texto y campos.
--}}
<x-layouts.base :titulo="$pagina" clase-cuerpo="placeholder-claro">
    <div class="acceso">

        <div class="acceso__columna">
            <div class="acceso__cabecera">
                <a href="{{ route('remates.index') }}"><img src="{{ asset('img/colliers-logo.png') }}" alt="Colliers" class="acceso__logo"></a>
            </div>

            <div class="acceso__contenido" @if ($alpine) x-data="{{ $alpine }}" @endif>
                <div class="acceso__kicker">
                    <span class="acceso__kicker-num">{{ $numero }}</span>
                    <span class="acceso__kicker-linea"></span>
                    <span class="acceso__kicker-texto">{{ $kicker }}</span>
                </div>

                <h1 class="acceso__titulo">{{ $titulo }}</h1>
                @if ($intro)
                    <p class="acceso__intro">{{ $intro }}</p>
                @endif

                {{ $slot }}
            </div>
        </div>

        <div class="acceso__foto">
            <x-imagen-slot :src="asset('img/demo/' . $proximo['foto'])" />
            <div class="acceso__foto-velo"></div>
            <div class="acceso__foto-contenido">
                <div class="acceso__foto-chip">PRÓXIMO REMATE</div>
                <div>
                    <div class="acceso__foto-titulo">{{ $proximo['direccion'] }}</div>
                    <div class="acceso__foto-datos">
                        <div class="acceso__foto-dato">
                            <div class="acceso__foto-dato-etiqueta">PRECIO BASE</div>
                            <div class="acceso__foto-dato-valor">{{ \App\Demo\RematesDemo::clp($proximo['precio']) }}</div>
                        </div>
                        <div class="acceso__foto-dato">
                            <div class="acceso__foto-dato-etiqueta">SUPERFICIE</div>
                            <div class="acceso__foto-dato-valor">{{ $proximo['sup'] }} m²</div>
                        </div>
                        <div class="acceso__foto-dato">
                            <div class="acceso__foto-dato-etiqueta">REMATE</div>
                            <div class="acceso__foto-dato-valor">{{ $proximo['fechaCorta'] }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-layouts.base>
