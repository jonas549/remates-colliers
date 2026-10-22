@php
    use App\Support\Formato;
    use App\Support\Sitio;

    $clp = fn ($n) => Formato::clp($n);
    $enUf = fn ($n) => Sitio::enUf($n);
    $vivo = $r['enVivo'];
    $cerrado = $r['resultado'] !== null;
    $sesion = $visitante['sesion'];
    $logueado = $visitante['logueado'];
    $limite = $r['limite'] ?? __('remate.limite_defecto');

    // Bloqueo según la cuenta y la garantía de ESTE remate (App\Publico\EstadoVisitante).
    $bloqueo = $vivo ? [
        'visitante' => ['tono' => 'info', 'titulo' => __('remate.bloqueo.vivo.visitante.titulo'), 'texto' => __('remate.bloqueo.vivo.visitante.texto'), 'cta' => $logueado ? null : __('remate.bloqueo.cta.crear_cuenta')],
        'registrado' => ['tono' => 'ambar', 'titulo' => __('remate.bloqueo.vivo.registrado.titulo'), 'texto' => __('remate.bloqueo.vivo.registrado.texto'), 'cta' => __('remate.bloqueo.cta.ver_mi_cuenta')],
        'en-revision' => ['tono' => 'azul', 'titulo' => __('remate.bloqueo.vivo.en_revision.titulo'), 'texto' => __('remate.bloqueo.vivo.en_revision.texto'), 'cta' => __('remate.bloqueo.cta.ver_estado_garantia')],
        'aprobada' => ['tono' => 'verde', 'titulo' => __('remate.bloqueo.vivo.aprobada.titulo'), 'texto' => __('remate.bloqueo.vivo.aprobada.texto'), 'cta' => __('remate.bloqueo.cta.entrar_sala')],
    ][$sesion] : [
        'visitante' => ['tono' => 'info', 'titulo' => __('remate.bloqueo.proximo.visitante.titulo'), 'texto' => __('remate.bloqueo.proximo.visitante.texto'), 'cta' => $logueado ? null : __('remate.bloqueo.cta.crear_cuenta')],
        'registrado' => ['tono' => 'ambar', 'titulo' => __('remate.bloqueo.proximo.registrado.titulo'), 'texto' => __('remate.bloqueo.proximo.registrado.texto', ['monto' => $clp($r['garantia']), 'limite' => $limite]), 'cta' => __('remate.bloqueo.cta.constituir_garantia')],
        'en-revision' => ['tono' => 'azul', 'titulo' => __('remate.bloqueo.proximo.en_revision.titulo'), 'texto' => __('remate.bloqueo.proximo.en_revision.texto', ['horas' => Sitio::horasRevision()]), 'cta' => __('remate.bloqueo.cta.ver_estado_garantia')],
        'aprobada' => ['tono' => 'verde', 'titulo' => __('remate.bloqueo.proximo.aprobada.titulo'), 'texto' => __('remate.bloqueo.proximo.aprobada.texto'), 'cta' => __('remate.bloqueo.cta.recordarme')],
    ][$sesion];
    // «Constituir la garantía» inscribe al postor en el remate (Bloque H) si su cuenta está aprobada y el plazo sigue abierto.
    $inscribir = ! $vivo && ! $cerrado && $sesion === 'registrado' && $visitante['cuentaAprobada'] && $visitante['garantia'] === null && $r['garantiasAbiertas'];
    $ctaHref = match ($sesion) {
        'visitante' => route('register'),
        'aprobada' => $vivo ? route('sala.show', $r['id']) : null,
        default => route('cuenta.estado', ['remate' => $r['id']]),
    };

    $etapa = ['visitante' => 0, 'registrado' => 1, 'en-revision' => 2, 'aprobada' => 3][$sesion];
    $pasos = [
        [__('remate.pasos.cuenta.titulo'), __('remate.pasos.cuenta.texto')],
        [__('remate.pasos.garantia.titulo'), __('remate.pasos.garantia.texto', ['monto' => $clp($r['garantia'])])],
        [__('remate.pasos.aprobacion.titulo'), __('remate.pasos.aprobacion.texto')],
        [__('remate.pasos.puja.titulo'), __('remate.pasos.puja.texto', ['monto' => $clp($r['incremento'])])],
    ];

    $config = $r['tiempoReal'] + ['enVivo' => $vivo, 'mapa' => $r['mapa'], 'rutas' => ['avisame' => route('suscripciones.store')], 'remate' => $r['id']];
    $iconoCalendario = '<svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="#25408f" stroke-width="1.5"><rect x="3" y="5" width="18" height="16"></rect><path d="M3 10h18M8 3v4M16 3v4"></path></svg>';
    $correoVisita = 'mailto:' . Sitio::correo() . '?subject=' . rawurlencode(__('remate.correo.asunto_visita', ['direccion' => $r['direccion'], 'folio' => $r['folio']]));
@endphp
<x-layouts.base :titulo="$r['direccion']" :descripcion="$r['seo']['descripcion'] ?? null" :imagen="$r['seo']['imagen'] ?? null" pagina-completa>
    <div class="detalle" x-data="detalleRemate(@js($config))">

        <x-publico.cabecera :sesion="$sesion" :visitante="$visitante" :z-index="1200">
            @if ($vivo)
                <div class="detalle-ticker detalle-ticker--vivo">
                    <span class="detalle-ticker__vivo" x-show="!cerrado"><span class="detalle-ticker__punto"></span>{{ __('remate.ticker.en_vivo') }}</span>
                    <span class="detalle-ticker__vivo" x-show="cerrado" x-cloak x-text="retirado ? @js(__('remate.ticker.no_disponible')) : (resultado ? @js(__('remate.ticker.cerrado')) : @js(__('remate.ticker.cerrando')))">{{ __('remate.ticker.cerrado') }}</span>
                    <span class="detalle-ticker__texto">{{ $r['direccion'] }} · <span x-show="!cerrado">{{ __('remate.ticker.cierra_en') }} <span x-text="tiempo"></span></span><span x-show="cerrado" x-cloak x-text="retirado ? @js(__('remate.ticker.retirado')) : (adjudicando ? @js(__('remate.ticker.adjudicando')) : @js(__('remate.ticker.lote_cerro')))"></span></span>
                    <span class="detalle-ticker__fin">{{ __('remate.ticker.puja_actual') }} <span x-text="pujaActual"></span></span>
                </div>
            @else
                <div class="detalle-ticker">
                    <span class="detalle-ticker__chip">{{ $cerrado ? __('remate.ticker.remate_cerrado') : __('remate.ticker.proximo_remate') }}</span>
                    <span class="detalle-ticker__texto">{{ $r['direccion'] }} · {{ $cerrado ? $r['resultado'] : __('remate.ticker.comienza_el') }} @unless ($cerrado)<span>{{ $r['fecha'] }}</span>@endunless</span>
                    @if (! $cerrado && $r['limite'])<span class="detalle-ticker__fin">{{ __('remate.ticker.garantias_hasta') }} <span>{{ $r['limite'] }}</span></span>@endif
                </div>
            @endif
        </x-publico.cabecera>

        <div class="detalle__contenido contenedor">
            <div class="detalle__migas">{{ __('remate.migas.inicio') }} <span>/</span> <a href="{{ route('remates.index') }}">{{ __('remate.migas.remates') }}</a> <span>/</span> {{ $r['direccion'] }}</div>

            <div class="detalle__titulo-fila">
                <div>
                    <div class="detalle__kicker">{{ __('remate.cabeza.folio') }} <span>{{ $r['folio'] }}</span></div>
                    <h1 class="detalle__titulo">{{ $r['direccion'] }}</h1>
                    <div class="detalle__meta">{{ $r['meta'] }}</div>
                </div>
                <div class="detalle__acciones">
                    <a href="{{ $r['bases'] ?? '#condiciones' }}" class="detalle__accion">{{ __('remate.cabeza.bases') }}</a>
                    {{-- Siempre visible mientras el remate no cierra (22/10); antes vivía dentro de «Horarios de visita». --}}
                    @unless ($cerrado)<a href="{{ $correoVisita }}" class="detalle__accion">{{ __('remate.visitas.coordinar') }}</a>@endunless
                    <a href="{{ url()->current() }}" class="detalle__accion detalle__accion--gris" @click.prevent="compartir()" x-text="compartido || @js(__('remate.cabeza.compartir'))">{{ __('remate.cabeza.compartir') }}</a>
                </div>
            </div>

            <div class="detalle__layout">

                <div>
                    @if ($vivo)
                        <div class="detalle__video">
                            @if ($r['video'])
                                <iframe src="https://www.youtube-nocookie.com/embed/{{ $r['video'] }}?rel=0" title="{{ __('remate.video.titulo') }}" allow="accelerometer; autoplay; clipboard-write; encrypted-media; picture-in-picture" allowfullscreen></iframe>
                            @else
                                <x-imagen-slot :src="$r['fotoPrincipal']" :alt="__('remate.video.foto_principal')" />
                            @endif
                        </div>
                        <div class="detalle__video-pie">
                            <span>
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#c8102e" stroke-width="1.5"><path d="M21.6 7.2a2.5 2.5 0 0 0-1.75-1.77C18.25 5 12 5 12 5s-6.25 0-7.85.43A2.5 2.5 0 0 0 2.4 7.2 26 26 0 0 0 2 12a26 26 0 0 0 .4 4.8 2.5 2.5 0 0 0 1.75 1.77C5.75 19 12 19 12 19s6.25 0 7.85-.43a2.5 2.5 0 0 0 1.75-1.77A26 26 0 0 0 22 12a26 26 0 0 0-.4-4.8Z"></path><path d="M10 15V9l5.2 3-5.2 3Z"></path></svg>
                                {{ __('remate.video.canal') }}
                            </span>
                            <span>{{ __('remate.video.martillero') }} <span>{{ $r['martillero'] }}</span></span>
                        </div>
                    @else
                        <div class="detalle__foto-principal">
                            <x-imagen-slot :src="$r['fotoPrincipal']" :alt="__('remate.video.foto_principal')" />
                        </div>
                    @endif

                    <div @class(['detalle__galeria', 'detalle__galeria--vivo' => $vivo])>
                        @foreach ($r['galeria'] as $i => $foto)
                            <div class="detalle__miniatura">
                                <x-imagen-slot :src="$foto" />
                                @if ($loop->last)
                                    <div class="detalle__mas-fotos">{{ __('remate.video.ver_fotos', ['total' => $r['totalFotos']]) }}</div>
                                @endif
                            </div>
                        @endforeach
                    </div>

                    @unless ($vivo || $cerrado)
                        <div class="detalle__aviso-stream">
                            <div class="detalle__aviso-stream-fila">
                                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#25408f" stroke-width="1.5"><path d="M21.6 7.2a2.5 2.5 0 0 0-1.75-1.77C18.25 5 12 5 12 5s-6.25 0-7.85.43A2.5 2.5 0 0 0 2.4 7.2 26 26 0 0 0 2 12a26 26 0 0 0 .4 4.8 2.5 2.5 0 0 0 1.75 1.77C5.75 19 12 19 12 19s6.25 0 7.85-.43a2.5 2.5 0 0 0 1.75-1.77A26 26 0 0 0 22 12a26 26 0 0 0-.4-4.8Z"></path><path d="M10 15V9l5.2 3-5.2 3Z"></path></svg>
                                <div>
                                    <div class="detalle__aviso-stream-titulo">{{ __('remate.transmision.se_habilita') }} <span>{{ $r['fecha'] }}</span></div>
                                    <div class="detalle__aviso-stream-texto">{{ __('remate.transmision.texto') }}</div>
                                    <div class="detalle__aviso-stream-enlaces">
                                        <a href="{{ route('remates.calendario', $r['id']) }}">{{ __('remate.transmision.calendario') }}</a>
                                        @if ($r['canal'])<a href="{{ $r['canal'] }}" target="_blank" rel="noopener">{{ __('remate.transmision.ver_canal') }}</a>@endif
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endunless

                    @if ($r['lotes'])
                        {{-- Varios lotes (sin diseño): lista simple; la ficha es la del lote que se remata ahora o el próximo. --}}
                        <h2 class="detalle__h2">{{ __('remate.secciones.lotes') }}</h2>
                        <div class="detalle__tabla-kv">
                            @foreach ($r['lotes'] as $l)
                                <div class="detalle__kv"><span class="detalle__kv-k">{{ $l['actual'] ? '▸ ' : '' }}{{ __('remate.secciones.lote', ['orden' => $l['orden'], 'direccion' => $l['direccion']]) }}</span><span class="detalle__kv-v">{{ $clp($l['base']) }} · {{ $l['horario'] }}</span></div>
                            @endforeach
                        </div>
                    @endif

                    @if ($r['descripcion'])
                        <h2 class="detalle__h2">{{ __('remate.secciones.descripcion') }}</h2>
                        <p class="detalle__descripcion">{{ $r['descripcion'] }}</p>
                    @endif

                    <h2 class="detalle__h2">{{ __('remate.secciones.caracteristicas') }}</h2>
                    <div class="detalle__ficha">
                        @foreach ($r['ficha'] as $k => $v)
                            <div class="detalle__ficha-item">
                                <div class="detalle__ficha-k">{{ $k }}</div>
                                <div class="detalle__ficha-v">{{ $v }}</div>
                            </div>
                        @endforeach
                    </div>

                    @if ($r['adicional'])
                        <h2 class="detalle__h2">{{ __('remate.secciones.adicional') }}</h2>
                        <div class="detalle__tabla-kv">
                            @foreach ($r['adicional'] as $k => $v)
                                <div class="detalle__kv"><span class="detalle__kv-k">{{ $k }}</span><span class="detalle__kv-v">{{ $v }}</span></div>
                            @endforeach
                        </div>
                    @endif

                    @if ($r['mandante'])
                        <h2 class="detalle__h2">{{ __('remate.secciones.mandante') }}</h2>
                        <div class="detalle__tabla-kv">
                            @foreach ($r['mandante'] as $k => $v)
                                <div class="detalle__kv"><span class="detalle__kv-k">{{ $k }}</span><span class="detalle__kv-v">{{ $v }}</span></div>
                            @endforeach
                        </div>
                    @endif

                    @if ($r['mapa'])
                        <h2 class="detalle__h2">{{ __('remate.secciones.ubicacion') }}</h2>
                        <div class="detalle__mapa-caja">
                            <div class="detalle__mapa" x-ref="mapa"></div>
                            <div class="detalle__mapa-pie">
                                <span>{{ $r['mapa']['texto'] }}</span>
                                <a href="https://www.google.com/maps/search/?api=1&query={{ $r['mapa']['lat'] }},{{ $r['mapa']['lng'] }}" target="_blank" rel="noopener">{{ __('remate.secciones.google_maps') }}</a>
                            </div>
                        </div>
                    @endif

                    <h2 class="detalle__h2" id="condiciones">{{ __('remate.secciones.condiciones') }}</h2>
                    <div class="detalle__condiciones">
                        <p>{{ __('remate.secciones.condicion_pesos') }}</p>
                        <p>{{ __('remate.secciones.condicion_cierre') }}</p>
                        <p>{{ $r['garantiaCondicion'] }}</p>
                    </div>
                </div>

                <div class="detalle__lateral">
                    @if ($vivo)
                        {{-- Sin horarios cargados no hay bloque (22/10): «Coordinar visita» va junto a «Bases y condiciones». --}}
                        @if ($r['visitas'])
                        <div class="detalle-caja">
                            <div class="detalle-caja__cabeza">{!! $iconoCalendario !!}<h2 class="detalle-caja__titulo">{{ __('remate.visitas.titulo') }}</h2></div>
                            <div class="detalle-visitas">
                                @foreach ($r['visitas'] as [$fecha, $hora])
                                    <div class="detalle-visita"><span class="detalle-visita__fecha">{{ $fecha }}</span><span class="detalle-visita__hora">{{ $hora }}</span></div>
                                @endforeach
                            </div>
                        </div>
                        @endif
                    @endif

                    <div class="detalle-caja">
                        @if ($vivo)
                            <div class="detalle-remate__cabeza detalle-remate__cabeza--vivo">
                                {{-- Al llegar la hora de cierre el estado cambia solo, con el reloj del servidor. --}}
                                <span class="detalle-remate__cabeza-vivo" x-show="!cerrado"><span></span>{{ __('remate.caja.en_curso') }}</span>
                                <span class="detalle-remate__cabeza-vivo detalle-remate__cabeza-vivo--cerrado" x-show="cerrado" x-cloak x-text="textoCierre">{{ __('remate.js.cerrado_adjudicando') }}</span>
                                <span class="detalle-remate__folio">{{ $r['folio'] }}</span>
                            </div>
                        @else
                            <div class="detalle-remate__cabeza">
                                <span class="detalle-remate__cabeza-etiqueta">{{ $cerrado ? __('remate.caja.remate_cerrado') : __('remate.caja.comienza_en') }}</span>
                                <span class="detalle-remate__folio">{{ $r['folio'] }}</span>
                            </div>
                        @endif
                        <div class="detalle-remate__cuerpo">
                            @if ($vivo)
                                <div class="detalle-remate__etiqueta">{{ __('remate.caja.puja_actual') }}</div>
                                <div class="detalle-remate__precio" x-text="pujaActual"></div>
                                <div class="detalle-remate__referencia">{!! __('remate.caja.pujas_ultima', ['total' => '<span x-text="total"></span>', 'hace' => '<span x-text="haceUltima"></span>']) !!}</div>
                                <div class="detalle-remate__datos">
                                    <div><div class="detalle-remate__dato-etiqueta">{{ __('remate.caja.precio_base') }}</div><div class="detalle-remate__dato-valor">{{ $clp($r['base']) }}</div></div>
                                    <div><div class="detalle-remate__dato-etiqueta">{{ __('remate.caja.incremento_minimo') }}</div><div class="detalle-remate__dato-valor">{{ $clp($r['incremento']) }}</div></div>
                                    <div><div class="detalle-remate__dato-etiqueta">{{ __('remate.caja.garantia') }}</div><div class="detalle-remate__dato-valor">{{ $clp($r['garantia']) }}</div></div>
                                    <div><div class="detalle-remate__dato-etiqueta">{{ __('remate.caja.referencia_uf') }}</div><div class="detalle-remate__dato-valor" x-text="pujaEnUf"></div></div>
                                </div>
                                <div class="detalle-remate__etiqueta detalle-remate__etiqueta--cierre" x-text="cerrado ? @js(__('remate.caja.cerrado')) : @js(__('remate.caja.cierra_en'))">{{ __('remate.caja.cierra_en') }}</div>
                                <div class="detalle-remate__contador" x-show="!cerrado">
                                    @foreach (['h' => __('remate.contador.horas'), 'm' => __('remate.contador.min'), 's' => __('remate.contador.seg')] as $p => $etiqueta)
                                        <div class="detalle-remate__caja"><div class="detalle-remate__digito" x-text="partes.{{ $p }}"></div><div class="detalle-remate__caja-etiqueta">{{ $etiqueta }}</div></div>
                                    @endforeach
                                </div>
                                <div class="detalle-remate__cierre-aviso" x-show="cerrado" x-cloak x-text="retirado ? @js(__('remate.caja.retirado')) : (adjudicando ? @js(__('remate.caja.adjudicando')) : @js(__('remate.caja.cerrado_aviso')))"></div>
                            @elseif ($cerrado)
                                <div class="detalle-remate__etiqueta">{{ __('remate.caja.resultado') }}</div>
                                <div class="detalle-remate__precio">{{ $r['resultado'] }}</div>
                                <div class="detalle-remate__referencia">{{ __('remate.caja.precio_base_monto', ['monto' => $clp($r['base'])]) }}</div>
                            @else
                                <div class="detalle-remate__contador">
                                    @foreach (['d' => __('remate.contador.dias'), 'h' => __('remate.contador.horas'), 'm' => __('remate.contador.min'), 's' => __('remate.contador.seg')] as $p => $etiqueta)
                                        <div class="detalle-remate__caja"><div class="detalle-remate__digito" x-text="partes.{{ $p }}"></div><div class="detalle-remate__caja-etiqueta">{{ $etiqueta }}</div></div>
                                    @endforeach
                                </div>
                                <div class="detalle-remate__etiqueta">{{ __('remate.caja.precio_base') }}</div>
                                <div class="detalle-remate__precio">{{ $clp($r['base']) }}</div>
                                <div class="detalle-remate__referencia">@if ($enUf($r['base'])){{ __('remate.caja.referencia') }} <span>{{ $enUf($r['base']) }}</span> · @endif {{ __('remate.caja.sin_pujas_aun') }}</div>
                                <div class="detalle-remate__datos">
                                    <div><div class="detalle-remate__dato-etiqueta">{{ __('remate.caja.garantia') }}</div><div class="detalle-remate__dato-valor">{{ $clp($r['garantia']) }}</div></div>
                                    <div><div class="detalle-remate__dato-etiqueta">{{ __('remate.caja.incremento_minimo') }}</div><div class="detalle-remate__dato-valor">{{ $clp($r['incremento']) }}</div></div>
                                    <div><div class="detalle-remate__dato-etiqueta">{{ __('remate.caja.inicio') }}</div><div class="detalle-remate__dato-valor">{{ $r['fecha'] }}</div></div>
                                    <div><div class="detalle-remate__dato-etiqueta">{{ __('remate.caja.cierre_garantias') }}</div><div class="detalle-remate__dato-valor">{{ $r['limite'] ?? __('remate.caja.hasta_inicio') }}</div></div>
                                </div>
                            @endif

                            @unless ($cerrado)
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

                            @if ($inscribir)
                                <form method="POST" action="{{ route('cuenta.inscribirme', $r['id']) }}" class="formulario-en-linea">
                                    @csrf
                                    <button type="submit" class="detalle-remate__cta">{{ $bloqueo['cta'] }}</button>
                                </form>
                            @elseif ($bloqueo['cta'] && $ctaHref)
                                {{-- Cerrado el lote, la sala ya no acepta pujas: el enlace desaparece al segundo. --}}
                                <a href="{{ $ctaHref }}" class="detalle-remate__cta" @if ($vivo && $sesion === 'aprobada') x-show="!cerrado" @endif>{{ $bloqueo['cta'] }}</a>
                                @if ($vivo && $sesion === 'aprobada')
                                    <div class="detalle-bloqueo__texto" x-show="cerrado" x-cloak x-text="adjudicando ? @js(__('remate.caja.lote_adjudicando')) : @js(__('remate.caja.lote_cerro'))"></div>
                                @endif
                            @elseif ($bloqueo['cta'])
                                <button type="button" class="detalle-remate__cta" @click="avisame(@js(auth()->user()?->email))" :disabled="avisoEnviando" x-text="avisoMensaje ? @js(__('remate.caja.te_avisaremos')) : @js($bloqueo['cta'])">{{ $bloqueo['cta'] }}</button>
                            @endif
                            @if ($vivo)
                                @if ($sesion !== 'visitante' && $sesion !== 'aprobada')
                                    <a href="{{ route('cuenta.estado', ['remate' => $r['id']]) }}" class="detalle-remate__secundario">{{ __('remate.caja.estado_garantia') }}</a>
                                @endif
                                <div class="detalle-remate__enlaces">
                                    <a href="{{ $sesion === 'visitante' && ! $logueado ? route('register') : route('cuenta.estado') }}">{{ __('remate.caja.como_garantia') }}</a>
                                    <span>|</span>
                                    <a href="mailto:{{ Sitio::correo() }}?subject={{ rawurlencode(__('remate.correo.asunto_consulta', ['folio' => $r['folio']])) }}">{{ __('remate.caja.contactar') }}</a>
                                </div>
                            @else
                                {{-- «Avísame antes de que comience» (Bloque M): con sesión, un clic; sin sesión, pide el correo. --}}
                                <template x-if="!avisoAbierto && !avisoMensaje">
                                    <a href="#" class="detalle-remate__secundario" @click.prevent="{{ $logueado ? 'avisame(' . e(json_encode(auth()->user()?->email)) . ')' : 'avisoAbierto = true' }}">{{ __('remate.caja.avisame') }}</a>
                                </template>
                                <template x-if="avisoAbierto && !avisoMensaje">
                                    <form class="detalle-avisame" @submit.prevent="avisame($event.target.email.value)">
                                        <input type="email" name="email" required placeholder="{{ __('remate.caja.correo_ejemplo') }}" aria-label="{{ __('remate.caja.correo_aviso') }}">
                                        <button type="submit" :disabled="avisoEnviando">{{ __('remate.caja.avisarme') }}</button>
                                    </form>
                                </template>
                                <p class="detalle-avisame__mensaje" x-show="avisoMensaje" x-text="avisoMensaje" role="status"></p>
                            @endif
                            @endunless
                        </div>
                    </div>

                    @if ($vivo)
                        <div class="detalle-caja">
                            <div class="detalle-caja__cabeza detalle-caja__cabeza--separada">
                                <h2 class="detalle-caja__titulo">{{ __('remate.historial.titulo') }}</h2>
                                <span class="detalle-historial__vivo">{{ __('remate.historial.actualiza') }}</span>
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
                            <div class="detalle-historial__nota">{{ __('remate.historial.nota') }}</div>
                        </div>
                    @else
                        @unless ($cerrado)
                        <div class="detalle-caja">
                            <div class="detalle-caja__cabeza"><h2 class="detalle-caja__titulo">{{ __('remate.pasos.titulo') }}</h2></div>
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
                        @endunless

                        {{-- Sin horarios cargados no hay bloque (22/10): «Coordinar visita» va junto a «Bases y condiciones». --}}
                        @if ($r['visitas'])
                        <div class="detalle-caja">
                            <div class="detalle-caja__cabeza">{!! $iconoCalendario !!}<h2 class="detalle-caja__titulo">{{ __('remate.visitas.titulo') }}</h2></div>
                            <div class="detalle-visitas">
                                @foreach ($r['visitas'] as [$fecha, $hora])
                                    <div class="detalle-visita"><span class="detalle-visita__fecha">{{ $fecha }}</span><span class="detalle-visita__hora">{{ $hora }}</span></div>
                                @endforeach
                            </div>
                        </div>
                        @endif
                    @endif

                    <div class="detalle-caja">
                        <div class="detalle-caja__cabeza">
                            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="#25408f" stroke-width="1.5"><path d="M4 4h6l2 3h8v13H4Z"></path></svg>
                            <h2 class="detalle-caja__titulo">{{ __('remate.documentos.titulo') }}</h2>
                        </div>
                        <div class="detalle-docs">
                            @forelse ($r['documentos'] as [$nombre, $peso, $url])
                                @if ($url)
                                    <a href="{{ $url }}" class="detalle-doc"><span class="detalle-doc__nombre">{{ $nombre }}{{ __('remate.documentos.idioma') }}</span><span class="detalle-doc__peso">{{ $peso }}</span></a>
                                @else
                                    <div class="detalle-doc detalle-doc--bloqueado"><span class="detalle-doc__nombre">{{ $nombre }}{{ __('remate.documentos.idioma') }}</span><span class="detalle-doc__peso">{{ __('remate.documentos.con_garantia') }}</span></div>
                                @endif
                            @empty
                                <div class="detalle-doc detalle-doc--bloqueado"><span class="detalle-doc__nombre">{{ __('remate.documentos.en_preparacion') }}</span><span class="detalle-doc__peso">{{ __('remate.documentos.pidelos') }}</span></div>
                            @endforelse
                            <div class="detalle-docs__nota">{{ __('remate.documentos.nota') }}</div>
                            {{-- Solo en inglés: qué versión rige (texto en revisión con el cliente; vacío en español). --}}
                            @if (__('remate.documentos.version_que_rige') !== '')
                                <div class="detalle-docs__nota">{{ __('remate.documentos.version_que_rige') }}</div>
                            @endif
                        </div>
                    </div>

                    <div class="detalle-caja detalle-ayuda">
                        <h2 class="detalle-caja__titulo">{{ __('remate.ayuda.titulo') }}</h2>
                        <p>{{ __('remate.ayuda.texto') }}</p>
                        <div class="detalle-ayuda__enlaces">
                            <a href="{{ \App\Support\Sitio::telefonoEnlace() }}">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#25408f" stroke-width="1.5"><path d="M5 4h4l2 5-2.5 1.5a12 12 0 0 0 5 5L15 13l5 2v4a1 1 0 0 1-1.1 1A17 17 0 0 1 4 5.1 1 1 0 0 1 5 4Z"></path></svg>
                                {{ \App\Support\Sitio::telefono() }}
                            </a>
                            <a href="mailto:{{ \App\Support\Sitio::correo() }}">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#25408f" stroke-width="1.5"><rect x="3" y="5" width="18" height="14"></rect><path d="m3 6 9 7 9-7"></path></svg>
                                {{ \App\Support\Sitio::correo() }}
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        @if ($r['recomendados'])
        <div class="recomendados">
            <div class="recomendados__interior contenedor">
                <div class="recomendados__cabeza">
                    <h2 class="recomendados__titulo">{{ __('remate.recomendados.titulo') }}</h2>
                    <a href="{{ route('remates.index') }}" class="recomendados__todos">{{ __('remate.recomendados.ver_todos') }}</a>
                </div>
                <div class="recomendados__grilla">
                    @foreach ($r['recomendados'] as $rec)
                        <div class="recomendado">
                            <div class="recomendado__foto">
                                <x-imagen-slot :src="$rec['foto']" />
                                <div class="tarjeta__capa">
                                    <span @class(['badge-remate', 'recomendado__badge', 'badge-remate--vivo' => $rec['vivo'] && ! $vivo, 'badge-remate--proximo' => ! $rec['vivo'] || $vivo])>{{ $rec['estado'] }}</span>
                                    <div class="tarjeta__precio">
                                        <div class="tarjeta__precio-etiqueta">{{ __('remate.recomendados.precio_base') }}</div>
                                        <div class="tarjeta__precio-valor">{{ $clp($rec['precio']) }}</div>
                                    </div>
                                </div>
                            </div>
                            <div class="recomendado__cuerpo">
                                <h3 class="recomendado__titulo"><a href="{{ route('remates.show', $rec['id']) }}">{{ $rec['direccion'] }}</a></h3>
                                <div class="recomendado__linea">{{ $rec['ubicacion'] }}</div>
                                <div class="recomendado__linea">{{ $rec['tipoSup'] }}</div>
                                <div class="recomendado__datos">
                                    <div><div class="tarjeta__dato-etiqueta">{{ __('remate.recomendados.remate') }}</div><div class="recomendado__dato-valor">{{ $rec['fecha'] }}</div></div>
                                    <div><div class="tarjeta__dato-etiqueta">{{ __('remate.recomendados.garantia') }}</div><div class="recomendado__dato-valor">{{ $clp($rec['garantia']) }}</div></div>
                                </div>
                                <a href="{{ route('remates.show', $rec['id']) }}" class="recomendado__ver">{{ __('remate.recomendados.ver') }}</a>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
        @endif

        <x-publico.pie :separado="$vivo" />
    </div>
</x-layouts.base>
