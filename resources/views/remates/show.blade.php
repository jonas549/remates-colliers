@php
    use App\Support\Formato;
    use App\Support\Sitio;

    $clp = fn ($n) => Formato::clp($n);
    $enUf = fn ($n) => Sitio::enUf($n);
    $vivo = $r['enVivo'];
    $cerrado = $r['resultado'] !== null;
    $sesion = $visitante['sesion'];
    $logueado = $visitante['logueado'];
    $limite = $r['limite'] ?? 'el inicio del remate';

    // Bloqueo según la cuenta y la garantía de ESTE remate (App\Publico\EstadoVisitante).
    $bloqueo = $vivo ? [
        'visitante' => ['tono' => 'info', 'titulo' => 'Solo puedes mirar este remate', 'texto' => 'Para pujar necesitas una cuenta y una garantía aprobada por Colliers. La transmisión y el historial son públicos.', 'cta' => $logueado ? null : 'Crear cuenta'],
        'registrado' => ['tono' => 'ambar', 'titulo' => 'Garantía no constituida', 'texto' => 'Tu cuenta está activa, pero no registras una garantía aprobada para este remate. Sin ella no puedes ingresar posturas.', 'cta' => 'Ver mi cuenta'],
        'en-revision' => ['tono' => 'azul', 'titulo' => 'Garantía en revisión', 'texto' => 'Recibimos tu comprobante y lo estamos revisando de forma manual. Te avisaremos por correo apenas quede aprobada.', 'cta' => 'Ver estado de la garantía'],
        'aprobada' => ['tono' => 'verde', 'titulo' => 'Estás habilitado para pujar', 'texto' => 'Tu garantía fue aprobada para este remate. Entra a la sala de pujas para ingresar posturas en pesos.', 'cta' => 'Entrar a la sala de pujas'],
    ][$sesion] : [
        'visitante' => ['tono' => 'info', 'titulo' => 'Necesitas una cuenta para pujar', 'texto' => 'Puedes revisar todos los antecedentes sin registrarte. Para ingresar posturas debes crear una cuenta y constituir la garantía.', 'cta' => $logueado ? null : 'Crear cuenta'],
        'registrado' => ['tono' => 'ambar', 'titulo' => 'Falta constituir la garantía', 'texto' => 'Vale a la vista o transferencia por ' . $clp($r['garantia']) . ', fuera de la plataforma. Debe estar aprobada antes del ' . $limite . '.', 'cta' => 'Constituir la garantía'],
        'en-revision' => ['tono' => 'azul', 'titulo' => 'Garantía en revisión', 'texto' => 'Recibimos tu comprobante y lo revisamos de forma manual dentro de ' . Sitio::horasRevision() . ' horas hábiles. Te avisaremos por correo.', 'cta' => 'Ver estado de la garantía'],
        'aprobada' => ['tono' => 'verde', 'titulo' => 'Estás habilitado para pujar', 'texto' => 'Tu garantía fue aprobada para este remate. Podrás ingresar posturas cuando comience la transmisión.', 'cta' => 'Recordarme al comenzar'],
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
        ['Crea tu cuenta', 'Con tu RUT y datos de contacto.'],
        ['Constituye la garantía', 'Vale a la vista o transferencia por ' . $clp($r['garantia']) . ', fuera de la plataforma.'],
        ['Espera la aprobación', 'Colliers revisa el comprobante de forma manual y marca tu inscripción como aprobada.'],
        ['Puja durante la transmisión', 'En pesos, con incrementos mínimos de ' . $clp($r['incremento']) . '. Cierra automáticamente al vencer el tiempo.'],
    ];

    $config = $r['tiempoReal'] + ['enVivo' => $vivo, 'mapa' => $r['mapa'], 'rutas' => ['avisame' => route('suscripciones.store')], 'remate' => $r['id']];
    $iconoCalendario = '<svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="#25408f" stroke-width="1.5"><rect x="3" y="5" width="18" height="16"></rect><path d="M3 10h18M8 3v4M16 3v4"></path></svg>';
    $correoVisita = 'mailto:' . Sitio::correo() . '?subject=' . rawurlencode('Visita a ' . $r['direccion'] . ' (' . $r['folio'] . ')');
@endphp
<x-layouts.base :titulo="$r['direccion']" pagina-completa>
    <div class="detalle" x-data="detalleRemate(@js($config))">

        <x-publico.cabecera :sesion="$sesion" :visitante="$visitante" :z-index="1200">
            @if ($vivo)
                <div class="detalle-ticker detalle-ticker--vivo">
                    <span class="detalle-ticker__vivo" x-show="!cerrado"><span class="detalle-ticker__punto"></span>EN VIVO</span>
                    <span class="detalle-ticker__vivo" x-show="cerrado" x-cloak x-text="resultado ? 'CERRADO' : 'CERRANDO'">CERRADO</span>
                    <span class="detalle-ticker__texto">{{ $r['direccion'] }} · <span x-show="!cerrado">cierra en <span x-text="tiempo"></span></span><span x-show="cerrado" x-cloak x-text="adjudicando ? 'el lote cerró: se está adjudicando' : 'el lote cerró'"></span></span>
                    <span class="detalle-ticker__fin">Puja actual <span x-text="pujaActual"></span></span>
                </div>
            @else
                <div class="detalle-ticker">
                    <span class="detalle-ticker__chip">{{ $cerrado ? 'REMATE CERRADO' : 'PRÓXIMO REMATE' }}</span>
                    <span class="detalle-ticker__texto">{{ $r['direccion'] }} · {{ $cerrado ? $r['resultado'] : 'comienza el' }} @unless ($cerrado)<span>{{ $r['fecha'] }}</span>@endunless</span>
                    @if (! $cerrado && $r['limite'])<span class="detalle-ticker__fin">Garantías hasta el <span>{{ $r['limite'] }}</span></span>@endif
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
                    <a href="{{ $r['bases'] ?? '#condiciones' }}" class="detalle__accion">Bases y condiciones</a>
                    <a href="{{ url()->current() }}" class="detalle__accion detalle__accion--gris" @click.prevent="compartir()" x-text="compartido || 'Compartir'">Compartir</a>
                </div>
            </div>

            <div class="detalle__layout">

                <div>
                    @if ($vivo)
                        <div class="detalle__video">
                            @if ($r['video'])
                                <iframe src="https://www.youtube-nocookie.com/embed/{{ $r['video'] }}?rel=0" title="Transmisión en vivo del remate" allow="accelerometer; autoplay; clipboard-write; encrypted-media; picture-in-picture" allowfullscreen></iframe>
                            @else
                                <x-imagen-slot :src="$r['fotoPrincipal']" alt="Foto principal de la propiedad" />
                            @endif
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
                            <x-imagen-slot :src="$r['fotoPrincipal']" alt="Foto principal de la propiedad" />
                        </div>
                    @endif

                    <div @class(['detalle__galeria', 'detalle__galeria--vivo' => $vivo])>
                        @foreach ($r['galeria'] as $i => $foto)
                            <div class="detalle__miniatura">
                                <x-imagen-slot :src="$foto" />
                                @if ($loop->last)
                                    <div class="detalle__mas-fotos">Ver las {{ $r['totalFotos'] }} fotos</div>
                                @endif
                            </div>
                        @endforeach
                    </div>

                    @unless ($vivo || $cerrado)
                        <div class="detalle__aviso-stream">
                            <div class="detalle__aviso-stream-fila">
                                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#25408f" stroke-width="1.5"><path d="M21.6 7.2a2.5 2.5 0 0 0-1.75-1.77C18.25 5 12 5 12 5s-6.25 0-7.85.43A2.5 2.5 0 0 0 2.4 7.2 26 26 0 0 0 2 12a26 26 0 0 0 .4 4.8 2.5 2.5 0 0 0 1.75 1.77C5.75 19 12 19 12 19s6.25 0 7.85-.43a2.5 2.5 0 0 0 1.75-1.77A26 26 0 0 0 22 12a26 26 0 0 0-.4-4.8Z"></path><path d="M10 15V9l5.2 3-5.2 3Z"></path></svg>
                                <div>
                                    <div class="detalle__aviso-stream-titulo">La transmisión se habilita el <span>{{ $r['fecha'] }}</span></div>
                                    <div class="detalle__aviso-stream-texto">El remate se emite en vivo por el canal de YouTube de Colliers Chile. El reproductor aparecerá en esta misma página al comenzar, y el historial de pujas se abrirá junto con él.</div>
                                    <div class="detalle__aviso-stream-enlaces">
                                        <a href="{{ route('remates.calendario', $r['id']) }}">Agregar a mi calendario</a>
                                        @if ($r['canal'])<a href="{{ $r['canal'] }}" target="_blank" rel="noopener">Ver el canal de Colliers</a>@endif
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endunless

                    @if ($r['lotes'])
                        {{-- Varios lotes (sin diseño): lista simple; la ficha es la del lote que se remata ahora o el próximo. --}}
                        <h2 class="detalle__h2">Lotes de este remate</h2>
                        <div class="detalle__tabla-kv">
                            @foreach ($r['lotes'] as $l)
                                <div class="detalle__kv"><span class="detalle__kv-k">{{ $l['actual'] ? '▸ ' : '' }}Lote {{ $l['orden'] }} · {{ $l['direccion'] }}</span><span class="detalle__kv-v">{{ $clp($l['base']) }} · {{ $l['horario'] }}</span></div>
                            @endforeach
                        </div>
                    @endif

                    @if ($r['descripcion'])
                        <h2 class="detalle__h2">Descripción</h2>
                        <p class="detalle__descripcion">{{ $r['descripcion'] }}</p>
                    @endif

                    <h2 class="detalle__h2">Características generales</h2>
                    <div class="detalle__ficha">
                        @foreach ($r['ficha'] as $k => $v)
                            <div class="detalle__ficha-item">
                                <div class="detalle__ficha-k">{{ $k }}</div>
                                <div class="detalle__ficha-v">{{ $v }}</div>
                            </div>
                        @endforeach
                    </div>

                    @if ($r['adicional'])
                        <h2 class="detalle__h2">Información adicional</h2>
                        <div class="detalle__tabla-kv">
                            @foreach ($r['adicional'] as $k => $v)
                                <div class="detalle__kv"><span class="detalle__kv-k">{{ $k }}</span><span class="detalle__kv-v">{{ $v }}</span></div>
                            @endforeach
                        </div>
                    @endif

                    @if ($r['mandante'])
                        <h2 class="detalle__h2">Antecedentes del mandante</h2>
                        <div class="detalle__tabla-kv">
                            @foreach ($r['mandante'] as $k => $v)
                                <div class="detalle__kv"><span class="detalle__kv-k">{{ $k }}</span><span class="detalle__kv-v">{{ $v }}</span></div>
                            @endforeach
                        </div>
                    @endif

                    @if ($r['mapa'])
                        <h2 class="detalle__h2">Ubicación</h2>
                        <div class="detalle__mapa-caja">
                            <div class="detalle__mapa" x-ref="mapa"></div>
                            <div class="detalle__mapa-pie">
                                <span>{{ $r['mapa']['texto'] }}</span>
                                <a href="https://www.google.com/maps/search/?api=1&query={{ $r['mapa']['lat'] }},{{ $r['mapa']['lng'] }}" target="_blank" rel="noopener">Abrir en Google Maps</a>
                            </div>
                        </div>
                    @endif

                    <h2 class="detalle__h2" id="condiciones">Condiciones del remate</h2>
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
                                @forelse ($r['visitas'] as [$fecha, $hora])
                                    <div class="detalle-visita"><span class="detalle-visita__fecha">{{ $fecha }}</span><span class="detalle-visita__hora">{{ $hora }}</span></div>
                                @empty
                                    <div class="detalle-visita"><span class="detalle-visita__fecha">Sin horarios publicados</span><span class="detalle-visita__hora">Coordínala con Colliers</span></div>
                                @endforelse
                                <a href="{{ $correoVisita }}" class="detalle-visitas__boton">Coordinar visita</a>
                            </div>
                        </div>
                    @endif

                    <div class="detalle-caja">
                        @if ($vivo)
                            <div class="detalle-remate__cabeza detalle-remate__cabeza--vivo">
                                {{-- Al llegar la hora de cierre el estado cambia solo, con el reloj del servidor. --}}
                                <span class="detalle-remate__cabeza-vivo" x-show="!cerrado"><span></span>REMATE EN CURSO</span>
                                <span class="detalle-remate__cabeza-vivo detalle-remate__cabeza-vivo--cerrado" x-show="cerrado" x-cloak x-text="textoCierre">CERRADO · ADJUDICANDO</span>
                                <span class="detalle-remate__folio">{{ $r['folio'] }}</span>
                            </div>
                        @else
                            <div class="detalle-remate__cabeza">
                                <span class="detalle-remate__cabeza-etiqueta">{{ $cerrado ? 'REMATE CERRADO' : 'COMIENZA EN' }}</span>
                                <span class="detalle-remate__folio">{{ $r['folio'] }}</span>
                            </div>
                        @endif
                        <div class="detalle-remate__cuerpo">
                            @if ($vivo)
                                <div class="detalle-remate__etiqueta">PUJA ACTUAL</div>
                                <div class="detalle-remate__precio" x-text="pujaActual"></div>
                                <div class="detalle-remate__referencia"><span x-text="total"></span> pujas · última hace <span x-text="haceUltima"></span></div>
                                <div class="detalle-remate__datos">
                                    <div><div class="detalle-remate__dato-etiqueta">PRECIO BASE</div><div class="detalle-remate__dato-valor">{{ $clp($r['base']) }}</div></div>
                                    <div><div class="detalle-remate__dato-etiqueta">INCREMENTO MÍNIMO</div><div class="detalle-remate__dato-valor">{{ $clp($r['incremento']) }}</div></div>
                                    <div><div class="detalle-remate__dato-etiqueta">GARANTÍA</div><div class="detalle-remate__dato-valor">{{ $clp($r['garantia']) }}</div></div>
                                    <div><div class="detalle-remate__dato-etiqueta">REFERENCIA UF</div><div class="detalle-remate__dato-valor" x-text="pujaEnUf"></div></div>
                                </div>
                                <div class="detalle-remate__etiqueta detalle-remate__etiqueta--cierre" x-text="cerrado ? 'CERRADO' : 'CIERRA EN'">CIERRA EN</div>
                                <div class="detalle-remate__contador" x-show="!cerrado">
                                    @foreach (['h' => 'HORAS', 'm' => 'MIN', 's' => 'SEG'] as $p => $etiqueta)
                                        <div class="detalle-remate__caja"><div class="detalle-remate__digito" x-text="partes.{{ $p }}"></div><div class="detalle-remate__caja-etiqueta">{{ $etiqueta }}</div></div>
                                    @endforeach
                                </div>
                                <div class="detalle-remate__cierre-aviso" x-show="cerrado" x-cloak x-text="adjudicando ? 'Adjudicando…' : 'Remate cerrado'"></div>
                            @elseif ($cerrado)
                                <div class="detalle-remate__etiqueta">RESULTADO</div>
                                <div class="detalle-remate__precio">{{ $r['resultado'] }}</div>
                                <div class="detalle-remate__referencia">Precio base {{ $clp($r['base']) }}</div>
                            @else
                                <div class="detalle-remate__contador">
                                    @foreach (['d' => 'DÍAS', 'h' => 'HORAS', 'm' => 'MIN', 's' => 'SEG'] as $p => $etiqueta)
                                        <div class="detalle-remate__caja"><div class="detalle-remate__digito" x-text="partes.{{ $p }}"></div><div class="detalle-remate__caja-etiqueta">{{ $etiqueta }}</div></div>
                                    @endforeach
                                </div>
                                <div class="detalle-remate__etiqueta">PRECIO BASE</div>
                                <div class="detalle-remate__precio">{{ $clp($r['base']) }}</div>
                                <div class="detalle-remate__referencia">@if ($enUf($r['base']))Referencia: <span>{{ $enUf($r['base']) }}</span> · @endif aún no hay pujas</div>
                                <div class="detalle-remate__datos">
                                    <div><div class="detalle-remate__dato-etiqueta">GARANTÍA</div><div class="detalle-remate__dato-valor">{{ $clp($r['garantia']) }}</div></div>
                                    <div><div class="detalle-remate__dato-etiqueta">INCREMENTO MÍNIMO</div><div class="detalle-remate__dato-valor">{{ $clp($r['incremento']) }}</div></div>
                                    <div><div class="detalle-remate__dato-etiqueta">INICIO</div><div class="detalle-remate__dato-valor">{{ $r['fecha'] }}</div></div>
                                    <div><div class="detalle-remate__dato-etiqueta">CIERRE DE GARANTÍAS</div><div class="detalle-remate__dato-valor">{{ $r['limite'] ?? 'Hasta el inicio' }}</div></div>
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
                                    <div class="detalle-bloqueo__texto" x-show="cerrado" x-cloak x-text="adjudicando ? 'El lote cerró: se está adjudicando.' : 'El lote cerró.'"></div>
                                @endif
                            @elseif ($bloqueo['cta'])
                                <button type="button" class="detalle-remate__cta" @click="avisame(@js(auth()->user()?->email))" :disabled="avisoEnviando" x-text="avisoMensaje ? 'Te avisaremos' : @js($bloqueo['cta'])">{{ $bloqueo['cta'] }}</button>
                            @endif
                            @if ($vivo)
                                @if ($sesion !== 'visitante' && $sesion !== 'aprobada')
                                    <a href="{{ route('cuenta.estado', ['remate' => $r['id']]) }}" class="detalle-remate__secundario">Ver el estado de mi garantía</a>
                                @endif
                                <div class="detalle-remate__enlaces">
                                    <a href="{{ $sesion === 'visitante' && ! $logueado ? route('register') : route('cuenta.estado') }}">Cómo constituir la garantía</a>
                                    <span>|</span>
                                    <a href="mailto:{{ Sitio::correo() }}?subject={{ rawurlencode('Consulta por el remate ' . $r['folio']) }}">Contactar al ejecutivo</a>
                                </div>
                            @else
                                {{-- «Avísame antes de que comience» (Bloque M): con sesión, un clic; sin sesión, pide el correo. --}}
                                <template x-if="!avisoAbierto && !avisoMensaje">
                                    <a href="#" class="detalle-remate__secundario" @click.prevent="{{ $logueado ? 'avisame(' . e(json_encode(auth()->user()?->email)) . ')' : 'avisoAbierto = true' }}">Avísame antes de que comience</a>
                                </template>
                                <template x-if="avisoAbierto && !avisoMensaje">
                                    <form class="detalle-avisame" @submit.prevent="avisame($event.target.email.value)">
                                        <input type="email" name="email" required placeholder="tu@correo.cl" aria-label="Correo para el aviso">
                                        <button type="submit" :disabled="avisoEnviando">Avisarme</button>
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
                        @unless ($cerrado)
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
                        @endunless

                        <div class="detalle-caja">
                            <div class="detalle-caja__cabeza">{!! $iconoCalendario !!}<h2 class="detalle-caja__titulo">Horarios de visita</h2></div>
                            <div class="detalle-visitas">
                                @forelse ($r['visitas'] as [$fecha, $hora])
                                    <div class="detalle-visita"><span class="detalle-visita__fecha">{{ $fecha }}</span><span class="detalle-visita__hora">{{ $hora }}</span></div>
                                @empty
                                    <div class="detalle-visita"><span class="detalle-visita__fecha">Sin horarios publicados</span><span class="detalle-visita__hora">Coordínala con Colliers</span></div>
                                @endforelse
                                <a href="{{ $correoVisita }}" class="detalle-visitas__boton">Coordinar visita</a>
                            </div>
                        </div>
                    @endif

                    <div class="detalle-caja">
                        <div class="detalle-caja__cabeza">
                            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="#25408f" stroke-width="1.5"><path d="M4 4h6l2 3h8v13H4Z"></path></svg>
                            <h2 class="detalle-caja__titulo">Documentos disponibles</h2>
                        </div>
                        <div class="detalle-docs">
                            @forelse ($r['documentos'] as [$nombre, $peso, $url])
                                @if ($url)
                                    <a href="{{ $url }}" class="detalle-doc"><span class="detalle-doc__nombre">{{ $nombre }}</span><span class="detalle-doc__peso">{{ $peso }}</span></a>
                                @else
                                    <div class="detalle-doc detalle-doc--bloqueado"><span class="detalle-doc__nombre">{{ $nombre }}</span><span class="detalle-doc__peso">Con garantía aprobada</span></div>
                                @endif
                            @empty
                                <div class="detalle-doc detalle-doc--bloqueado"><span class="detalle-doc__nombre">Documentos en preparación</span><span class="detalle-doc__peso">Pídelos a Colliers</span></div>
                            @endforelse
                            <div class="detalle-docs__nota">Los antecedentes legales completos se entregan a los postores con garantía aprobada.</div>
                        </div>
                    </div>

                    <div class="detalle-caja detalle-ayuda">
                        <h2 class="detalle-caja__titulo">¿Necesitas ayuda?</h2>
                        <p>El equipo de remates resuelve dudas sobre la propiedad, la garantía y el procedimiento.</p>
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
                    <h2 class="recomendados__titulo">Otros remates que podrían interesarte</h2>
                    <a href="{{ route('remates.index') }}" class="recomendados__todos">Ver todos los remates</a>
                </div>
                <div class="recomendados__grilla">
                    @foreach ($r['recomendados'] as $rec)
                        <div class="recomendado">
                            <div class="recomendado__foto">
                                <x-imagen-slot :src="$rec['foto']" />
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
        @endif

        <x-publico.pie :separado="$vivo" />
    </div>
</x-layouts.base>
