@php
    use App\Demo\RematesDemo;

    $clp = fn ($n) => RematesDemo::clp($n);
    $uf = 39412.73;
    $enUf = fn ($n) => 'UF ' . number_format(round($n / $uf), 0, ',', '.');
    $vivo = $r['enVivo'];

    // Bloqueo según la sesión (demo hasta el Bloque D)
    $bloqueo = $vivo ? [
        'visitante' => ['tono' => 'info', 'titulo' => 'Solo puedes mirar este remate', 'texto' => 'Para pujar necesitas una cuenta y una garantía aprobada por Colliers. La transmisión y el historial son públicos.', 'cta' => 'Crear cuenta'],
        'registrado' => ['tono' => 'ambar', 'titulo' => 'Garantía no constituida', 'texto' => 'Tu cuenta está activa, pero no registras una garantía aprobada para este remate. Sin ella no puedes ingresar posturas.', 'cta' => 'Constituir la garantía'],
        'en-revision' => ['tono' => 'azul', 'titulo' => 'Garantía en revisión', 'texto' => 'Recibimos tu comprobante y lo estamos revisando de forma manual. Te avisaremos por correo apenas quede aprobada.', 'cta' => 'Ver estado de la garantía'],
        'aprobada' => ['tono' => 'verde', 'titulo' => 'Estás habilitado para pujar', 'texto' => 'Tu garantía fue aprobada para este remate. Entra a la sala de pujas para ingresar posturas en pesos.', 'cta' => 'Entrar a la sala de pujas'],
    ][$sesion] : [
        'visitante' => ['tono' => 'info', 'titulo' => 'Necesitas una cuenta para pujar', 'texto' => 'Puedes revisar todos los antecedentes sin registrarte. Para ingresar posturas debes crear una cuenta y constituir la garantía.', 'cta' => 'Crear cuenta'],
        'registrado' => ['tono' => 'ambar', 'titulo' => 'Falta constituir la garantía', 'texto' => 'Vale a la vista o transferencia por ' . $clp($r['garantia']) . ', a nombre de Colliers Chile. Debe estar aprobada antes del ' . ($r['limite'] ?? '') . '.', 'cta' => 'Constituir la garantía'],
        'en-revision' => ['tono' => 'azul', 'titulo' => 'Garantía en revisión', 'texto' => 'Recibimos tu comprobante y lo revisamos de forma manual dentro de 24 horas hábiles. Te avisaremos por correo.', 'cta' => 'Ver estado de la garantía'],
        'aprobada' => ['tono' => 'verde', 'titulo' => 'Estás habilitado para pujar', 'texto' => 'Tu garantía fue aprobada para este remate. Podrás ingresar posturas cuando comience la transmisión.', 'cta' => 'Recordarme al comenzar'],
    ][$sesion];
    $ctaHref = match ($sesion) {
        'visitante' => route('registro'),
        'aprobada' => route('sala.show', $r['id']),
        default => route('cuenta.estado'),
    };

    $etapa = ['visitante' => 0, 'registrado' => 1, 'en-revision' => 2, 'aprobada' => 3][$sesion];
    $pasos = [
        ['Crea tu cuenta', 'Con tu RUT y datos de contacto.'],
        ['Constituye la garantía', 'Vale a la vista o transferencia por ' . $clp($r['garantia']) . ', fuera de la plataforma.'],
        ['Espera la aprobación', 'Colliers revisa el comprobante de forma manual y marca tu inscripción como aprobada.'],
        ['Puja durante la transmisión', 'En pesos, con incrementos mínimos de ' . $clp($r['incremento']) . '. Cierra automáticamente al vencer el tiempo.'],
    ];

    $config = [
        'delta' => $vivo ? $r['deltaCierre'] : $r['deltaInicio'],
        'pujas' => $vivo ? $r['pujas'] : [],
        'mapa' => $r['mapa'],
    ];
    $iconoCalendario = '<svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="#25408f" stroke-width="1.5"><rect x="3" y="5" width="18" height="16"></rect><path d="M3 10h18M8 3v4M16 3v4"></path></svg>';
@endphp
<x-layouts.base :titulo="$r['direccion']" pagina-completa>
    <div class="detalle" x-data="detalleRemate(@js($config))">

        <x-publico.cabecera :sesion="$sesion" :z-index="1200">
            @if ($vivo)
                <div class="detalle-ticker detalle-ticker--vivo">
                    <span class="detalle-ticker__vivo"><span class="detalle-ticker__punto"></span>EN VIVO</span>
                    <span class="detalle-ticker__texto">{{ $r['direccion'] }} · cierra en <span x-text="tiempo"></span></span>
                    <span class="detalle-ticker__fin">Puja actual <span x-text="pujaActual"></span></span>
                </div>
            @else
                <div class="detalle-ticker">
                    <span class="detalle-ticker__chip">PRÓXIMO REMATE</span>
                    <span class="detalle-ticker__texto">{{ $r['direccion'] }} · comienza el <span>{{ $r['fecha'] }}</span></span>
                    <span class="detalle-ticker__fin">Garantías hasta el <span>{{ $r['limite'] }}</span></span>
                </div>
            @endif
        </x-publico.cabecera>

        <div class="detalle__contenido contenedor">
            <div class="detalle__migas">Inicio <span>/</span> <a href="{{ route('remates.index') }}">Remates</a> <span>/</span> {{ $r['direccion'] }}</div>

            <div class="detalle__titulo-fila">
                <div>
                    <div class="detalle__kicker">REMATE N.º <span>{{ $r['folio'] }}</span></div>
                    <h1 class="detalle__titulo">{{ $r['direccion'] }}</h1>
                    <div class="detalle__meta">{{ $r['meta'] }}</div>
                </div>
                <div class="detalle__acciones">
                    <a href="#" class="detalle__accion">Bases y condiciones</a>
                    <a href="#" class="detalle__accion detalle__accion--gris">Compartir</a>
                </div>
            </div>

            <div class="detalle__layout">

                <div>
                    @if ($vivo)
                        <div class="detalle__video">
                            <iframe src="https://www.youtube-nocookie.com/embed/{{ $r['video'] }}?rel=0" title="Transmisión en vivo del remate" allow="accelerometer; autoplay; clipboard-write; encrypted-media; picture-in-picture" allowfullscreen></iframe>
                        </div>
                        <div class="detalle__video-pie">
                            <span>
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#c8102e" stroke-width="1.5"><path d="M21.6 7.2a2.5 2.5 0 0 0-1.75-1.77C18.25 5 12 5 12 5s-6.25 0-7.85.43A2.5 2.5 0 0 0 2.4 7.2 26 26 0 0 0 2 12a26 26 0 0 0 .4 4.8 2.5 2.5 0 0 0 1.75 1.77C5.75 19 12 19 12 19s6.25 0 7.85-.43a2.5 2.5 0 0 0 1.75-1.77A26 26 0 0 0 22 12a26 26 0 0 0-.4-4.8Z"></path><path d="M10 15V9l5.2 3-5.2 3Z"></path></svg>
                                Transmisión del canal de Colliers Chile
                            </span>
                            <span>Martillero: <span>{{ $r['martillero'] }}</span></span>
                        </div>
                    @else
                        <div class="detalle__foto-principal">
                            <x-imagen-slot :src="asset('img/demo/' . $r['fotoPrincipal'])" alt="Foto principal de la propiedad" />
                        </div>
                    @endif

                    <div @class(['detalle__galeria', 'detalle__galeria--vivo' => $vivo])>
                        @foreach ($r['galeria'] as $i => $foto)
                            <div class="detalle__miniatura">
                                <x-imagen-slot :src="asset('img/demo/' . $foto)" />
                                @if ($loop->last)
                                    <div class="detalle__mas-fotos">Ver las {{ $r['totalFotos'] }} fotos</div>
                                @endif
                            </div>
                        @endforeach
                    </div>

                    @unless ($vivo)
                        <div class="detalle__aviso-stream">
                            <div class="detalle__aviso-stream-fila">
                                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#25408f" stroke-width="1.5"><path d="M21.6 7.2a2.5 2.5 0 0 0-1.75-1.77C18.25 5 12 5 12 5s-6.25 0-7.85.43A2.5 2.5 0 0 0 2.4 7.2 26 26 0 0 0 2 12a26 26 0 0 0 .4 4.8 2.5 2.5 0 0 0 1.75 1.77C5.75 19 12 19 12 19s6.25 0 7.85-.43a2.5 2.5 0 0 0 1.75-1.77A26 26 0 0 0 22 12a26 26 0 0 0-.4-4.8Z"></path><path d="M10 15V9l5.2 3-5.2 3Z"></path></svg>
                                <div>
                                    <div class="detalle__aviso-stream-titulo">La transmisión se habilita el <span>{{ $r['fecha'] }}</span></div>
                                    <div class="detalle__aviso-stream-texto">El remate se emite en vivo por el canal de YouTube de Colliers Chile. El reproductor aparecerá en esta misma página al comenzar, y el historial de pujas se abrirá junto con él.</div>
                                    <div class="detalle__aviso-stream-enlaces">
                                        <a href="#">Agregar a mi calendario</a>
                                        <a href="#">Ver el canal de Colliers</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endunless

                    <h2 class="detalle__h2">Descripción</h2>
                    <p class="detalle__descripcion">{{ $r['descripcion'] }}</p>

                    <h2 class="detalle__h2">Características generales</h2>
                    <div class="detalle__ficha">
                        @foreach ($r['ficha'] as $k => $v)
                            <div class="detalle__ficha-item">
                                <div class="detalle__ficha-k">{{ $k }}</div>
                                <div class="detalle__ficha-v">{{ $v }}</div>
                            </div>
                        @endforeach
                    </div>

                    <h2 class="detalle__h2">Información adicional</h2>
                    <div class="detalle__tabla-kv">
                        @foreach ($r['adicional'] as $k => $v)
                            <div class="detalle__kv"><span class="detalle__kv-k">{{ $k }}</span><span class="detalle__kv-v">{{ $v }}</span></div>
                        @endforeach
                    </div>

                    <h2 class="detalle__h2">Antecedentes del mandante</h2>
                    <div class="detalle__tabla-kv">
                        @foreach ($r['mandante'] as $k => $v)
                            <div class="detalle__kv"><span class="detalle__kv-k">{{ $k }}</span><span class="detalle__kv-v">{{ $v }}</span></div>
                        @endforeach
                    </div>

                    <h2 class="detalle__h2">Ubicación</h2>
                    <div class="detalle__mapa-caja">
                        <div class="detalle__mapa" x-ref="mapa"></div>
                        <div class="detalle__mapa-pie">
                            <span>{{ $r['mapa']['texto'] }}</span>
                            <a href="https://www.google.com/maps/search/?api=1&query={{ $r['mapa']['lat'] }},{{ $r['mapa']['lng'] }}" target="_blank" rel="noopener">Abrir en Google Maps</a>
                        </div>
                    </div>

                    <h2 class="detalle__h2">Condiciones del remate</h2>
                    <div class="detalle__condiciones">
                        <p>Las posturas se ingresan en pesos chilenos. El valor de la UF se muestra solo como referencia informativa y no determina el monto de las pujas ni de la garantía.</p>
                        <p>El remate cierra automáticamente al vencer el temporizador, sin extensiones por posturas de último minuto. Colliers puede cerrar el remate de forma anticipada.</p>
                        <p>{{ $r['garantiaCondicion'] }}</p>
                    </div>
                </div>

                <div class="detalle__lateral">
                    @if ($vivo)
                        <div class="detalle-caja">
                            <div class="detalle-caja__cabeza">{!! $iconoCalendario !!}<h2 class="detalle-caja__titulo">Horarios de visita</h2></div>
                            <div class="detalle-visitas">
                                @foreach ($r['visitas'] as [$fecha, $hora])
                                    <div class="detalle-visita"><span class="detalle-visita__fecha">{{ $fecha }}</span><span class="detalle-visita__hora">{{ $hora }}</span></div>
                                @endforeach
                                <a href="mailto:remates@colliers.cl?subject={{ rawurlencode('Visita a ' . $r['direccion'] . ' (' . $r['folio'] . ')') }}" class="detalle-visitas__boton">Coordinar visita</a>
                            </div>
                        </div>
                    @endif

                    <div class="detalle-caja">
                        @if ($vivo)
                            <div class="detalle-remate__cabeza detalle-remate__cabeza--vivo">
                                <span class="detalle-remate__cabeza-vivo"><span></span>REMATE EN CURSO</span>
                                <span class="detalle-remate__folio">{{ $r['folio'] }}</span>
                            </div>
                        @else
                            <div class="detalle-remate__cabeza">
                                <span class="detalle-remate__cabeza-etiqueta">COMIENZA EN</span>
                                <span class="detalle-remate__folio">{{ $r['folio'] }}</span>
                            </div>
                        @endif
                        <div class="detalle-remate__cuerpo">
                            @if ($vivo)
                                <div class="detalle-remate__etiqueta">PUJA ACTUAL</div>
                                <div class="detalle-remate__precio" x-text="pujaActual"></div>
                                <div class="detalle-remate__referencia"><span x-text="pujas.length"></span> pujas · última hace <span x-text="haceUltima"></span></div>
                                <div class="detalle-remate__datos">
                                    <div><div class="detalle-remate__dato-etiqueta">PRECIO BASE</div><div class="detalle-remate__dato-valor">{{ $clp($r['base']) }}</div></div>
                                    <div><div class="detalle-remate__dato-etiqueta">INCREMENTO MÍNIMO</div><div class="detalle-remate__dato-valor">{{ $clp($r['incremento']) }}</div></div>
                                    <div><div class="detalle-remate__dato-etiqueta">GARANTÍA</div><div class="detalle-remate__dato-valor">{{ $clp($r['garantia']) }}</div></div>
                                    <div><div class="detalle-remate__dato-etiqueta">REFERENCIA UF</div><div class="detalle-remate__dato-valor" x-text="pujaEnUf"></div></div>
                                </div>
                                <div class="detalle-remate__etiqueta detalle-remate__etiqueta--cierre">CIERRA EN</div>
                                <div class="detalle-remate__contador">
                                    @foreach (['h' => 'HORAS', 'm' => 'MIN', 's' => 'SEG'] as $p => $etiqueta)
                                        <div class="detalle-remate__caja"><div class="detalle-remate__digito" x-text="partes.{{ $p }}"></div><div class="detalle-remate__caja-etiqueta">{{ $etiqueta }}</div></div>
                                    @endforeach
                                </div>
                            @else
                                <div class="detalle-remate__contador">
                                    @foreach (['d' => 'DÍAS', 'h' => 'HORAS', 'm' => 'MIN', 's' => 'SEG'] as $p => $etiqueta)
                                        <div class="detalle-remate__caja"><div class="detalle-remate__digito" x-text="partes.{{ $p }}"></div><div class="detalle-remate__caja-etiqueta">{{ $etiqueta }}</div></div>
                                    @endforeach
                                </div>
                                <div class="detalle-remate__etiqueta">PRECIO BASE</div>
                                <div class="detalle-remate__precio">{{ $clp($r['base']) }}</div>
                                <div class="detalle-remate__referencia">Referencia: <span>{{ $enUf($r['base']) }}</span> · aún no hay pujas</div>
                                <div class="detalle-remate__datos">
                                    <div><div class="detalle-remate__dato-etiqueta">GARANTÍA</div><div class="detalle-remate__dato-valor">{{ $clp($r['garantia']) }}</div></div>
                                    <div><div class="detalle-remate__dato-etiqueta">INCREMENTO MÍNIMO</div><div class="detalle-remate__dato-valor">{{ $clp($r['incremento']) }}</div></div>
                                    <div><div class="detalle-remate__dato-etiqueta">INICIO</div><div class="detalle-remate__dato-valor">{{ $r['fecha'] }}</div></div>
                                    <div><div class="detalle-remate__dato-etiqueta">CIERRE DE GARANTÍAS</div><div class="detalle-remate__dato-valor">{{ $r['limite'] }}</div></div>
                                </div>
                            @endif

                            <div class="detalle-bloqueo detalle-bloqueo--{{ $bloqueo['tono'] }}">
                                @if ($vivo)
                                    <div class="detalle-bloqueo__fila">
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="4" y="10" width="16" height="10"></rect><path d="M8 10V7a4 4 0 0 1 8 0v3"></path></svg>
                                        <div>
                                            <div class="detalle-bloqueo__titulo">{{ $bloqueo['titulo'] }}</div>
                                            <div class="detalle-bloqueo__texto">{{ $bloqueo['texto'] }}</div>
                                        </div>
                                    </div>
                                @else
                                    <div class="detalle-bloqueo__titulo">{{ $bloqueo['titulo'] }}</div>
                                    <div class="detalle-bloqueo__texto">{{ $bloqueo['texto'] }}</div>
                                @endif
                            </div>

                            <a href="{{ $ctaHref }}" class="detalle-remate__cta">{{ $bloqueo['cta'] }}</a>
                            @if ($vivo)
                                @if ($sesion !== 'visitante')
                                    <a href="{{ route('sala.show', $r['id']) }}" class="detalle-remate__secundario">Ya tengo garantía aprobada: entrar a la sala</a>
                                @endif
                                <div class="detalle-remate__enlaces">
                                    <a href="{{ $sesion === 'visitante' ? route('registro') : route('cuenta.estado') }}">Cómo constituir la garantía</a>
                                    <span>|</span>
                                    <a href="#">Contactar al ejecutivo</a>
                                </div>
                            @else
                                <a href="#" class="detalle-remate__secundario">Avísame antes de que comience</a>
                            @endif
                        </div>
                    </div>

                    @if ($vivo)
                        <div class="detalle-caja">
                            <div class="detalle-caja__cabeza detalle-caja__cabeza--separada">
                                <h2 class="detalle-caja__titulo">Historial de pujas</h2>
                                <span class="detalle-historial__vivo">Actualiza solo</span>
                            </div>
                            <div class="detalle-historial">
                                <template x-for="(p, i) in historial" :key="i">
                                    <div class="historial-puja" :class="{ 'es-primera': i === 0 }">
                                        <div>
                                            <div class="historial-puja__monto" x-text="p.monto"></div>
                                            <div class="historial-puja__postor" x-text="p.postor"></div>
                                        </div>
                                        <div class="historial-puja__derecha">
                                            <div class="historial-puja__hora" x-text="p.hora"></div>
                                            <div class="historial-puja__hace" x-text="p.hace"></div>
                                        </div>
                                    </div>
                                </template>
                            </div>
                            <div class="detalle-historial__nota">Los postores se identifican con un número asignado por el sistema.</div>
                        </div>
                    @else
                        <div class="detalle-caja">
                            <div class="detalle-caja__cabeza"><h2 class="detalle-caja__titulo">Pasos para participar</h2></div>
                            <div class="detalle-pasos">
                                @foreach ($pasos as $i => [$titulo, $texto])
                                    <div class="detalle-paso">
                                        <div @class(['detalle-paso__num', 'detalle-paso__num--hecho' => $i < $etapa, 'detalle-paso__num--actual' => $i === $etapa])>{{ $i < $etapa ? '✓' : $i + 1 }}</div>
                                        <div>
                                            <div @class(['detalle-paso__titulo', 'detalle-paso__titulo--alcanzado' => $i <= $etapa, 'detalle-paso__titulo--actual' => $i === $etapa])>{{ $titulo }}</div>
                                            <div class="detalle-paso__texto">{{ $texto }}</div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        <div class="detalle-caja">
                            <div class="detalle-caja__cabeza">{!! $iconoCalendario !!}<h2 class="detalle-caja__titulo">Horarios de visita</h2></div>
                            <div class="detalle-visitas">
                                @foreach ($r['visitas'] as [$fecha, $hora])
                                    <div class="detalle-visita"><span class="detalle-visita__fecha">{{ $fecha }}</span><span class="detalle-visita__hora">{{ $hora }}</span></div>
                                @endforeach
                                <a href="mailto:remates@colliers.cl?subject={{ rawurlencode('Visita a ' . $r['direccion'] . ' (' . $r['folio'] . ')') }}" class="detalle-visitas__boton">Coordinar visita</a>
                            </div>
                        </div>
                    @endif

                    <div class="detalle-caja">
                        <div class="detalle-caja__cabeza">
                            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="#25408f" stroke-width="1.5"><path d="M4 4h6l2 3h8v13H4Z"></path></svg>
                            <h2 class="detalle-caja__titulo">Documentos disponibles</h2>
                        </div>
                        <div class="detalle-docs">
                            @foreach ($r['documentos'] as [$nombre, $peso])
                                <a href="#" class="detalle-doc"><span class="detalle-doc__nombre">{{ $nombre }}</span><span class="detalle-doc__peso">{{ $peso }}</span></a>
                            @endforeach
                            <div class="detalle-docs__nota">Los antecedentes legales completos se entregan a los postores con garantía aprobada.</div>
                        </div>
                    </div>

                    <div class="detalle-caja detalle-ayuda">
                        <h2 class="detalle-caja__titulo">¿Necesitas ayuda?</h2>
                        <p>El equipo de remates resuelve dudas sobre la propiedad, la garantía y el procedimiento.</p>
                        <div class="detalle-ayuda__enlaces">
                            <a href="tel:+56227603535">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#25408f" stroke-width="1.5"><path d="M5 4h4l2 5-2.5 1.5a12 12 0 0 0 5 5L15 13l5 2v4a1 1 0 0 1-1.1 1A17 17 0 0 1 4 5.1 1 1 0 0 1 5 4Z"></path></svg>
                                +56 2 2760 3535
                            </a>
                            <a href="mailto:remates@colliers.cl">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#25408f" stroke-width="1.5"><rect x="3" y="5" width="18" height="14"></rect><path d="m3 6 9 7 9-7"></path></svg>
                                remates@colliers.cl
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="recomendados">
            <div class="recomendados__interior contenedor">
                <div class="recomendados__cabeza">
                    <h2 class="recomendados__titulo">Otros remates que podrían interesarte</h2>
                    <a href="{{ route('remates.index') }}" class="recomendados__todos">Ver todos los remates</a>
                </div>
                <div class="recomendados__grilla">
                    @foreach ($r['recomendados'] as $rec)
                        <div class="recomendado">
                            <div class="recomendado__foto">
                                <x-imagen-slot :src="asset('img/demo/prop-' . $rec['id'] . '.jpg')" />
                                <div class="tarjeta__capa">
                                    <span @class(['badge-remate', 'recomendado__badge', 'badge-remate--vivo' => $rec['vivo'] && ! $vivo, 'badge-remate--proximo' => ! $rec['vivo'] || $vivo])>{{ $rec['estado'] }}</span>
                                    <div class="tarjeta__precio">
                                        <div class="tarjeta__precio-etiqueta">PRECIO BASE</div>
                                        <div class="tarjeta__precio-valor">{{ $clp($rec['precio']) }}</div>
                                    </div>
                                </div>
                            </div>
                            <div class="recomendado__cuerpo">
                                <h3 class="recomendado__titulo"><a href="{{ route('remates.show', $rec['id']) }}">{{ $rec['direccion'] }}</a></h3>
                                <div class="recomendado__linea">{{ $rec['ubicacion'] }}</div>
                                <div class="recomendado__linea">{{ $rec['tipoSup'] }}</div>
                                <div class="recomendado__datos">
                                    <div><div class="tarjeta__dato-etiqueta">REMATE</div><div class="recomendado__dato-valor">{{ $rec['fecha'] }}</div></div>
                                    <div><div class="tarjeta__dato-etiqueta">GARANTÍA</div><div class="recomendado__dato-valor">{{ $clp($rec['garantia']) }}</div></div>
                                </div>
                                <a href="{{ route('remates.show', $rec['id']) }}" class="recomendado__ver">Ver remate</a>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <x-publico.pie :separado="$vivo" />
    </div>
</x-layouts.base>
