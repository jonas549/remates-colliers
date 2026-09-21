{{--
    Inicio del martillero (21/09). Pantalla sin diseño propio, armada con los componentes del panel: sus remates en
    vivo, los próximos y los últimos cerrados, cada uno con «Abrir panel en vivo» a un clic.
--}}
@php
    $grupos = [
        ['titulo' => 'En vivo ahora', 'filas' => $enVivo, 'vacio' => null],
        ['titulo' => 'Próximos', 'filas' => $proximos, 'vacio' => null],
        ['titulo' => 'Últimos cerrados', 'filas' => $cerrados, 'vacio' => null],
    ];
    $ninguno = $enVivo === [] && $proximos === [] && $cerrados === [];
@endphp
<x-layouts.admin seccion="dashboard" titulo="Tus remates">
    <div class="admin-encabezado">
        <div>
            <div class="admin-encabezado__kicker">MARTILLERO/A · {{ mb_strtoupper(auth()->user()->name) }}</div>
            <h1 class="admin-encabezado__titulo">Tus remates</h1>
        </div>
        <div class="admin-fecha">{{ $fecha }}</div>
    </div>

    <div class="admin-mis-remates" data-mis-remates>
        @if ($ninguno)
            <div class="admin-seccion">
                <p class="admin-seccion__nota">
                    Todavía no tienes remates asignados. La administración de Colliers te asigna cada remate desde su ficha;
                    cuando lo haga, aparecerá aquí con el acceso a su panel en vivo.
                </p>
            </div>
        @endif

        @foreach ($grupos as $grupo)
            @continue($grupo['filas'] === [])
            <section class="admin-seccion admin-mis-remates__grupo">
                <div class="admin-seccion__cabeza"><h2 class="admin-seccion__titulo">{{ $grupo['titulo'] }}</h2></div>
                @foreach ($grupo['filas'] as $r)
                    <article class="admin-mi-remate" data-mi-remate="{{ $r['vista'] }}">
                        <div class="admin-mi-remate__datos">
                            <div class="admin-mi-remate__cabeza">
                                <span class="badge-admin badge-admin--{{ $r['tono'] }}">{{ $r['estado'] }}</span>
                                <span class="admin-mi-remate__folio">{{ $r['folio'] }}</span>
                                @if ($r['prueba'])
                                    <span class="badge-admin badge-admin--cerrado">PRUEBA</span>
                                @endif
                            </div>
                            <div class="admin-mi-remate__titulo">{{ $r['direccion'] }}</div>
                            <div class="admin-mi-remate__cuando">{{ $r['cuando'] }}</div>
                        </div>
                        <div class="admin-mi-remate__acciones">
                            <a href="{{ $r['enVivo'] }}" class="{{ $r['vista'] === 'en_vivo' ? 'admin-boton' : 'admin-boton-borde' }}">Abrir panel en vivo</a>
                            <a href="{{ $r['ficha'] }}" class="admin-accion">Ver ficha</a>
                        </div>
                    </article>
                @endforeach
            </section>
        @endforeach
    </div>
</x-layouts.admin>
