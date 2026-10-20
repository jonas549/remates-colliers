@php
    use App\Support\Formato;
    use App\Support\Sitio;

    $clp = fn ($n) => Formato::clp($n);
    $sesion = $visitante['sesion'];
    $logueado = $visitante['logueado'];
    // En local, ?sesion= reproduce el prototipo (remate de ejemplo); con datos reales, el remate de la inscripción.
    $remateBarra = $visitante['remate']['titulo'] ?? ($hero['direccion'] ?? '');
    $slugBarra = $visitante['remate']['slug'] ?? ($hero['id'] ?? null);
    $barra = [
        'registrado' => ['clase' => 'registrado', 'msg' => __('listado.barra.registrado'), 'accion' => __('listado.barra.registrado_accion')],
        'en-revision' => ['clase' => 'revision', 'msg' => __('listado.barra.en_revision', ['remate' => $remateBarra, 'horas' => Sitio::horasRevision()]), 'accion' => __('listado.barra.en_revision_accion')],
        'aprobada' => ['clase' => 'aprobada', 'msg' => __('listado.barra.aprobada', ['remate' => $remateBarra]), 'accion' => __('listado.barra.aprobada_accion')],
    ][$sesion] ?? null;
    $heroCta = $hero === null ? null : match ($sesion) {
        'visitante' => $logueado ? ['texto' => __('listado.hero.ver_bases'), 'href' => route('remates.show', $hero['id'])] : ['texto' => __('listado.hero.crear_cuenta'), 'href' => route('register')],
        'aprobada' => ['texto' => __('listado.hero.ir_al_remate'), 'href' => $visitante['remate'] ? route('sala.show', $visitante['remate']['slug']) : route('remates.show', $hero['id'])],
        default => ['texto' => __('listado.hero.constituir_garantia'), 'href' => route('remates.show', $hero['id'])],
    };
    // Claves: valores del catálogo (datos del filtro); valores: etiquetas.
    $opcionesEstado = ['Todos' => __('listado.filtros.estado_todos'), 'En vivo' => __('listado.filtros.estado_en_vivo'), 'Próximo' => __('listado.filtros.estado_proximo'), 'Cerrado' => __('listado.filtros.estado_cerrado')];
    $opcionesOcupacion = ['Todas' => __('listado.filtros.ocupacion_todas'), 'Desocupada' => __('listado.filtros.ocupacion_desocupada'), 'Ocupada' => __('listado.filtros.ocupacion_ocupada')];
    // Opciones de filtro generadas desde los remates publicados.
    $tipos = collect($remates)->pluck('tipo')->unique()->sortBy(fn ($t) => $t === 'Departamento' ? 0 : 1)->values();
    $regiones = \App\Support\RegionesChile::ordenar(collect($remates)->pluck('region')->unique()->values()->all());
    $comunas = collect($remates)->mapWithKeys(fn ($r) => [$r['comuna'] => $r['region']])->sortKeysUsing(fn ($x, $y) => collator_compare(collator_create('es_CL'), $x, $y))->all();
    // $mostrarFiltroGarantia: Administración → Configuración → Sitio público (ver la nota junto al grupo "Garantía requerida").
    $config = [
        'remates' => $remates,
        'sesion' => $sesion,
        'rutas' => ['detalle' => route('remates.show', '__ID__'), 'avisame' => route('suscripciones.store')],
    ];
@endphp
<x-layouts.base :titulo="__('listado.titulo_pagina')" pagina-completa>
    <div class="listado" x-data="listado(@js($config))">

        <x-publico.cabecera :sesion="$sesion" :visitante="$visitante">
            @if ($barra)
                <div class="pub-garantia pub-garantia--{{ $barra['clase'] }}">
                    <div class="pub-garantia__interior contenedor">
                        <span class="pub-garantia__chip">{{ __('listado.barra.chip') }}</span>
                        <span>{{ $barra['msg'] }}</span>
                        <a href="{{ $sesion === 'aprobada' && $slugBarra ? route('sala.show', $slugBarra) : route('cuenta.estado', array_filter(['remate' => $visitante['remate']['slug'] ?? null])) }}" class="pub-garantia__accion">{{ $barra['accion'] }}</a>
                    </div>
                </div>
            @endif
        </x-publico.cabecera>

        <div class="listado__encabezado contenedor">
            <div class="listado__migas">{{ __('listado.encabezado.migas_inicio') }} <span>/</span> {{ __('listado.encabezado.migas_remates') }}</div>
            <div class="listado__encabezado-grilla">
                <div>
                    <div class="listado__kicker">{{ __('listado.encabezado.kicker') }}</div>
                    <h1 class="listado__titulo">{{ __('listado.encabezado.titulo') }}</h1>
                    <p class="listado__bajada">{{ __('listado.encabezado.bajada') }}</p>
                </div>
                <div class="listado__uf">
                    <div>
                        <div class="listado__uf-etiqueta">{{ __('listado.encabezado.uf_etiqueta') }}</div>
                        <div class="listado__uf-valor">{{ $uf['valor'] ? '$' . (\App\Support\Idioma::esIngles() ? number_format($uf['valor'], 2, '.', ',') : number_format($uf['valor'], 2, ',', '.')) : '—' }}</div>
                        <div class="listado__uf-fecha">{{ $uf['fecha'] ? Formato::fechaLarga(\Carbon\CarbonImmutable::parse($uf['fecha'], Formato::ZONA)) : __('listado.encabezado.uf_sin_valor') }}</div>
                    </div>
                    <div class="listado__uf-divisor"></div>
                    <p>{{ __('listado.encabezado.uf_nota') }}</p>
                </div>
            </div>
        </div>

        @if ($hero)
        <div class="listado__hero">
            <div class="listado__hero-interior contenedor">
                <div>
                    <div class="listado__hero-chip">{{ $hero['enVivo'] ? __('listado.hero.en_vivo') : __('listado.hero.proximo') }}</div>
                    <h2 class="listado__hero-titulo">{{ $hero['direccion'] }}</h2>
                    <div class="listado__hero-meta">{{ collect([trim($hero['comuna'] . ', ' . $hero['region'], ', '), \App\Publico\Catalogo::etiquetaTipo($hero['tipo']), $hero['sup'] ? __('listado.hero.superficie', ['sup' => Formato::numero($hero['sup'], 2)]) : null, $hero['dorm'] ? __('listado.hero.dorm_banos', ['dorm' => $hero['dorm'], 'banos' => $hero['banos']]) : null, \App\Publico\Catalogo::etiquetaOcupacion($hero['ocupacion'])])->filter()->join(' · ') }}</div>
                    <div class="listado__contador">
                        @foreach (['d' => __('listado.hero.dias'), 'h' => __('listado.hero.horas'), 'm' => __('listado.hero.min'), 's' => __('listado.hero.seg')] as $parte => $etiqueta)
                            <div class="listado__caja-tiempo">
                                <div class="listado__digito" x-text="hero({{ $hero['delta'] }}).{{ $parte }}"></div>
                                <div class="listado__caja-etiqueta">{{ $etiqueta }}</div>
                            </div>
                        @endforeach
                    </div>
                    @if ($hero['limiteLargo'] && ! $hero['enVivo'])
                        <div class="listado__hero-plazo">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#ffd400" stroke-width="1.5"><circle cx="12" cy="12" r="9"></circle><path d="M12 7v5l3 2"></path></svg>
                            <span>{{ __('listado.hero.garantia_hasta') }}</span>
                            <span>{{ $hero['limiteLargo'] }}</span>
                        </div>
                    @endif
                    <div class="listado__hero-datos">
                        <div class="listado__hero-dato">
                            <div class="listado__hero-dato-etiqueta">{{ __('listado.hero.precio_base') }}</div>
                            <div class="listado__hero-dato-valor">{{ $clp($hero['precio']) }}</div>
                        </div>
                        <div class="listado__hero-dato">
                            <div class="listado__hero-dato-etiqueta">{{ __('listado.hero.garantia_requerida') }}</div>
                            <div class="listado__hero-dato-valor">{{ $clp($hero['garantia']) }}</div>
                        </div>
                        <div class="listado__hero-dato">
                            <div class="listado__hero-dato-etiqueta">{{ __('listado.hero.inicio') }}</div>
                            <div class="listado__hero-dato-valor">{{ $hero['fecha'] }}</div>
                        </div>
                    </div>
                    <div class="listado__hero-acciones">
                        <a href="{{ $heroCta['href'] }}" class="listado__hero-cta">{{ $heroCta['texto'] }}</a>
                        <a href="{{ route('remates.show', $hero['id']) }}" class="listado__hero-cta2">{{ __('listado.hero.ver_bases') }}</a>
                        <a href="mailto:{{ Sitio::correo() }}?subject={{ rawurlencode(__('listado.hero.visita_asunto', ['direccion' => $hero['direccion'], 'folio' => $hero['folio']])) }}" class="listado__hero-visita">{{ __('listado.hero.coordinar_visita') }}</a>
                    </div>
                    <p class="listado__hero-nota">{!! __('listado.hero.nota', ['martillero' => '<span>' . e($hero['martillero']) . '</span>']) !!}</p>
                </div>
                <div class="listado__hero-foto">
                    <x-imagen-slot :src="$hero['foto']" :alt="__('listado.hero.foto_alt')" />
                </div>
            </div>
        </div>
        @endif

        <div class="listado__lista contenedor">
            <div class="listado__herramientas">
                <button type="button" class="listado__btn-filtros" @click="alternarFiltros()">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#25408f" stroke-width="1.5"><path d="M3 5h18M6 12h12M10 19h4"></path></svg>
                    <span x-text="filtrosAbiertos && !compacto ? @js(__('listado.herramientas.ocultar_filtros')) : @js(__('listado.herramientas.filtros'))">{{ __('listado.herramientas.ocultar_filtros') }}</span>
                </button>
                <label class="listado__buscador">
                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="#5b6572" stroke-width="1.5"><circle cx="11" cy="11" r="7"></circle><path d="m20 20-3.5-3.5"></path></svg>
                    <input id="buscar" :value="q" @input="buscar($event.target.value)" placeholder="{{ __('listado.herramientas.buscar_placeholder') }}" aria-label="{{ __('listado.herramientas.buscar_etiqueta') }}">
                </label>
                <select class="listado__orden" x-model="orden" aria-label="{{ __('listado.herramientas.ordenar_etiqueta') }}">
                    <option value="fecha">{{ __('listado.herramientas.orden_fecha') }}</option>
                    <option value="precio-asc">{{ __('listado.herramientas.orden_precio_asc') }}</option>
                    <option value="precio-desc">{{ __('listado.herramientas.orden_precio_desc') }}</option>
                    <option value="superficie">{{ __('listado.herramientas.orden_superficie') }}</option>
                </select>
                <div class="listado__vista">
                    <button type="button" :class="{ 'es-activa': vista === 'Grilla' }" @click="vista = 'Grilla'" class="es-activa">{{ __('listado.herramientas.vista_grilla') }}</button>
                    <button type="button" :class="{ 'es-activa': vista === 'Tabla' }" @click="vista = 'Tabla'">{{ __('listado.herramientas.vista_tabla') }}</button>
                </div>
            </div>

            <div class="listado__layout" :class="{ 'sin-filtros': !filtrosAbiertos }">

                <template x-if="compacto && filtrosAbiertos">
                    <div class="listado__velo" @click="alternarFiltros()"></div>
                </template>

                <template x-if="filtrosAbiertos">
                    <div class="listado__filtros" @keydown.escape.window="compacto && alternarFiltros()">
                        <div class="listado__filtros-cabeza">
                            <div>{{ __('listado.filtros.titulo') }}</div>
                            <button type="button" @click="alternarFiltros()" aria-label="{{ __('listado.filtros.cerrar') }}">✕</button>
                        </div>

                        <details open class="listado__grupo" x-data="{ abierto: true }" @toggle="abierto = $el.open">
                            <summary>{{ __('listado.filtros.estado') }} <span x-text="abierto ? '–' : '+'">–</span></summary>
                            <div class="listado__opciones">
                                @foreach ($opcionesEstado as $clave => $etiqueta)
                                    <label class="listado__opcion listado__opcion--radio">
                                        <input type="radio" name="estado-remate" :checked="estado === @js($clave)" @change="elegir('estado', @js($clave), 'Todos')">
                                        <span>{{ $etiqueta }}</span>
                                        <span class="listado__opcion-cuenta">{{ $clave === 'Todos' ? count($remates) : collect($remates)->where('estado', $clave)->count() }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </details>

                        <details open class="listado__grupo" x-data="{ abierto: true }" @toggle="abierto = $el.open">
                            <summary>{{ __('listado.filtros.ocupacion') }} <span x-text="abierto ? '–' : '+'">–</span></summary>
                            <div class="listado__opciones">
                                @foreach ($opcionesOcupacion as $clave => $etiqueta)
                                    <label class="listado__opcion listado__opcion--radio">
                                        <input type="radio" name="ocupacion" :checked="ocupacion === @js($clave)" @change="elegir('ocupacion', @js($clave), 'Todas')">
                                        <span>{{ $etiqueta }}</span>
                                        <span class="listado__opcion-cuenta">{{ $clave === 'Todas' ? count($remates) : collect($remates)->where('ocupacion', $clave)->count() }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </details>

                        {{-- Filtros adicionales (decisión del 15/09). Se quitó "Con visita programada": agendar visita quedó fuera de alcance. --}}
                        <details class="listado__grupo" x-data="{ abierto: false }" @toggle="abierto = $el.open">
                            <summary>{{ __('listado.filtros.fecha') }} <span x-text="abierto ? '–' : '+'">+</span></summary>
                            <div class="listado__opciones">
                                <label class="listado__opcion"><input type="checkbox" value="7" x-model="extra.fecha" @change="marcarExtra('fecha')">{{ __('listado.filtros.fecha_semana') }}</label>
                                <label class="listado__opcion"><input type="checkbox" value="30" x-model="extra.fecha" @change="marcarExtra('fecha')">{{ __('listado.filtros.fecha_30') }}</label>
                                <label class="listado__opcion"><input type="checkbox" value="90" x-model="extra.fecha" @change="marcarExtra('fecha')">{{ __('listado.filtros.fecha_90') }}</label>
                            </div>
                        </details>
                        <details class="listado__grupo" x-data="{ abierto: false }" @toggle="abierto = $el.open">
                            <summary>{{ __('listado.filtros.tipo') }} <span x-text="abierto ? '–' : '+'">+</span></summary>
                            <div class="listado__opciones">
                                @foreach ($tipos as $tipo)
                                    <label class="listado__opcion"><input type="checkbox" value="{{ $tipo }}" x-model="extra.tipo" @change="marcarExtra('tipo')">{{ \App\Publico\Catalogo::etiquetaTipo($tipo) }}</label>
                                @endforeach
                            </div>
                        </details>
                        <details class="listado__grupo" x-data="{ abierto: false }" @toggle="abierto = $el.open">
                            <summary>{{ __('listado.filtros.caracteristicas') }} <span x-text="abierto ? '–' : '+'">+</span></summary>
                            <div class="listado__opciones">
                                <label class="listado__opcion"><input type="checkbox" value="2" x-model="extra.dorm" @change="marcarExtra('caracteristicas')">{{ __('listado.filtros.dorm_2') }}</label>
                                <label class="listado__opcion"><input type="checkbox" value="3" x-model="extra.dorm" @change="marcarExtra('caracteristicas')">{{ __('listado.filtros.dorm_3') }}</label>
                                <label class="listado__opcion"><input type="checkbox" x-model="extra.estac" @change="marcarExtra('caracteristicas')">{{ __('listado.filtros.estacionamiento') }}</label>
                                <label class="listado__opcion"><input type="checkbox" x-model="extra.bodega" @change="marcarExtra('caracteristicas')">{{ __('listado.filtros.bodega') }}</label>
                            </div>
                        </details>

                        <details class="listado__grupo" x-data="{ abierto: false }" @toggle="abierto = $el.open">
                            <summary>{{ __('listado.filtros.precio') }} <span x-text="abierto ? '–' : '+'">+</span></summary>
                            <div class="listado__rango">
                                <input placeholder="{{ __('listado.filtros.precio_desde') }}" aria-label="{{ __('listado.filtros.precio_desde_etiqueta') }}" inputmode="numeric" :value="extra.desde" @input="extra.desde = $event.target.value.replace(/[^\d]/g, ''); $event.target.value = extra.desde; marcarExtra('precio')">
                                <input placeholder="{{ __('listado.filtros.precio_hasta') }}" aria-label="{{ __('listado.filtros.precio_hasta_etiqueta') }}" inputmode="numeric" :value="extra.hasta" @input="extra.hasta = $event.target.value.replace(/[^\d]/g, ''); $event.target.value = extra.hasta; marcarExtra('precio')">
                            </div>
                        </details>

                        {{-- Región y comuna: opciones generadas desde los remates publicados (no fijas). --}}
                        <details class="listado__grupo" x-data="{ abierto: false }" @toggle="abierto = $el.open">
                            <summary>{{ __('listado.filtros.region') }} <span x-text="abierto ? '–' : '+'">+</span></summary>
                            <div class="listado__opciones">
                                @foreach ($regiones as $region)
                                    <label class="listado__opcion"><input type="checkbox" value="{{ $region }}" x-model="extra.region" @change="marcarExtra('region')">{{ \App\Support\RegionesChile::nombreCorto($region) }}</label>
                                @endforeach
                            </div>
                        </details>
                        <details class="listado__grupo" x-data="{ abierto: false }" @toggle="abierto = $el.open">
                            <summary>{{ __('listado.filtros.comuna') }} <span x-text="abierto ? '–' : '+'">+</span></summary>
                            <div class="listado__opciones">
                                @foreach ($comunas as $comuna => $regionComuna)
                                    <label class="listado__opcion" x-show="comunaVisible(@js($comuna), @js($regionComuna))"><input type="checkbox" value="{{ $comuna }}" x-model="extra.comuna" @change="marcarExtra('comuna')">{{ $comuna }}</label>
                                @endforeach
                            </div>
                        </details>

                        {{--
                            Garantía requerida: OCULTO, no borrar (decisión del 15/09).
                            Sería redundante con el rango de precio solo si la garantía fuera un porcentaje fijo, pero el
                            porcentaje será configurable (Bloque V) y el prototipo ya muestra garantías distintas del 10%.
                            Si Colliers define porcentajes por remate, el filtro vuelve a ser útil. En el Bloque V se activa
                            desde configuración, reemplazando $mostrarFiltroGarantia. Los tramos no son fijos: se calculan
                            desde los datos (resources/js/modulos/listado.js → tramosGarantia).
                        --}}
                        @if ($mostrarFiltroGarantia)
                            <details class="listado__grupo" x-data="{ abierto: false }" @toggle="abierto = $el.open">
                                <summary>{{ __('listado.filtros.garantia') }} <span x-text="abierto ? '–' : '+'">+</span></summary>
                                <div class="listado__opciones">
                                    <template x-for="(tramo, i) in tramosGarantia" :key="i">
                                        <label class="listado__opcion"><input type="checkbox" :value="i" x-model.number="extra.garantia" @change="marcarExtra('garantia')"><span x-text="tramo.etiqueta"></span></label>
                                    </template>
                                </div>
                            </details>
                        @endif

                        <div class="listado__filtros-acciones">
                            <button type="button" class="listado__limpiar" @click="limpiar()">{{ __('listado.filtros.limpiar') }}</button>
                            <button type="button" class="listado__ver-resultados" @click="alternarFiltros()" x-text="@js(__('listado.filtros.ver_resultados')).replace(':n', filtrados.length)"></button>
                        </div>
                    </div>
                </template>

                <div>
                    <div class="listado__resultados-cabeza">
                        <div class="listado__conteo" x-text="conteo"></div>
                        <div class="listado__nota-cierre">{{ __('listado.resultados.nota_cierre') }}</div>
                    </div>

                    <template x-if="esGrilla">
                        <div>
                            <div class="listado__grilla">
                                <template x-for="l in tarjetas" :key="l.id">
                                    <div class="tarjeta">
                                        <div class="tarjeta__foto">
                                            <span class="imagen-slot"><span class="imagen-slot__marco"><img :src="l.foto" alt="" draggable="false" decoding="async"></span></span>
                                            <div class="tarjeta__capa">
                                                <div class="tarjeta__capa-arriba">
                                                    <div class="badge-remate" :class="l.badgeClase" x-text="l.estado"></div>
                                                    {{-- Guardar remate (favoritos): fuera de alcance por decisión del 15/09. --}}
                                                </div>
                                                <div class="tarjeta__precio">
                                                    <div class="tarjeta__precio-etiqueta">{{ __('listado.resultados.precio_base') }}</div>
                                                    <div class="tarjeta__precio-valor" x-text="l.precio"></div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="tarjeta__cuerpo">
                                            <h3 class="tarjeta__titulo"><a :href="l.href" x-text="l.direccion"></a></h3>
                                            <div class="tarjeta__linea tarjeta__linea--ubicacion">
                                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#25408f" stroke-width="1.5"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"></path><circle cx="12" cy="10" r="3"></circle></svg>
                                                <span x-text="l.ubicacion"></span>
                                            </div>
                                            <div class="tarjeta__linea">
                                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#25408f" stroke-width="1.5"><path d="M3 10.5 12 4l9 6.5V20a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1Z"></path></svg>
                                                <span x-text="l.tipoSup"></span>
                                            </div>
                                            <div class="tarjeta__chips">
                                                <span class="tarjeta__chip" x-text="l.dormBanos"></span>
                                                <template x-if="l.extras"><span class="tarjeta__chip" x-text="l.extras"></span></template>
                                                <span class="tarjeta__chip" :class="l.ocupacionClase" x-text="l.ocupacion"></span>
                                            </div>
                                            <div class="tarjeta__datos">
                                                <div>
                                                    <div class="tarjeta__dato-etiqueta">{{ __('listado.resultados.remate') }}</div>
                                                    <div class="tarjeta__dato-valor" x-text="l.fecha"></div>
                                                </div>
                                                <div>
                                                    <div class="tarjeta__dato-etiqueta">{{ __('listado.resultados.garantia') }}</div>
                                                    <div class="tarjeta__dato-valor" x-text="l.garantia"></div>
                                                    <div class="tarjeta__dato-limite" x-text="l.limite"></div>
                                                </div>
                                            </div>
                                            <template x-if="l.visita">
                                                <div class="tarjeta__visita">
                                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#25408f" stroke-width="1.5"><rect x="3" y="5" width="18" height="16"></rect><path d="M3 10h18M8 3v4M16 3v4"></path></svg>
                                                    <span>{{ __('listado.resultados.visita') }}</span>
                                                    <span x-text="l.visita"></span>
                                                </div>
                                            </template>
                                            <div class="tarjeta__estado">
                                                <span class="tarjeta__estado-etiqueta" x-text="l.statusLabel"></span>
                                                <span class="tarjeta__estado-valor" :class="l.statusClase" x-text="l.statusValor"></span>
                                            </div>
                                            <div class="tarjeta__pie">
                                                <template x-if="!l.abierto">
                                                    <div class="tarjeta__cerrado">
                                                        <div class="tarjeta__cerrado-caja">{{ __('listado.resultados.remate_cerrado') }}</div>
                                                        <template x-if="l.nuevoRemate">
                                                            <a :href="l.nuevoHref || '#'" class="tarjeta__nuevo" x-text="@js(__('listado.resultados.republicado')).replace(':remate', () => l.nuevoRemate)"></a>
                                                        </template>
                                                    </div>
                                                </template>
                                                <template x-if="l.abierto">
                                                    <a :href="l.href" class="tarjeta__cta" x-text="l.cta"></a>
                                                </template>
                                                <div class="tarjeta__enlaces">
                                                    <a :href="l.bases || l.href">{{ __('listado.resultados.bases') }}</a>
                                                    <span>{{ __('listado.resultados.martillero') }} <span x-text="l.martillero"></span></span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </template>
                            </div>
                            <template x-if="hayMas">
                                <div class="listado__mas">
                                    <button type="button" @click="visibles += 6">{{ __('listado.resultados.cargar_mas') }}</button>
                                </div>
                            </template>
                        </div>
                    </template>

                    <template x-if="esTabla">
                        <table class="listado__tabla">
                            <thead>
                                <tr>
                                    <th>{{ __('listado.tabla.propiedad') }}</th>
                                    <th>{{ __('listado.tabla.tipo_sup') }}</th>
                                    <th>{{ __('listado.tabla.ocupacion') }}</th>
                                    <th>{{ __('listado.tabla.precio_base') }}</th>
                                    <th>{{ __('listado.tabla.garantia') }}</th>
                                    <th>{{ __('listado.tabla.remate') }}</th>
                                    <th>{{ __('listado.tabla.estado') }}</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <template x-for="l in tarjetas" :key="l.id">
                                    <tr>
                                        <td>
                                            <div class="listado__tabla-direccion" x-text="l.direccion"></div>
                                            <div class="listado__tabla-ubicacion" x-text="l.ubicacion"></div>
                                        </td>
                                        <td x-text="l.tipoSup"></td>
                                        <td x-text="l.ocupacion"></td>
                                        <td class="es-precio" x-text="l.precio"></td>
                                        <td x-text="l.garantia"></td>
                                        <td x-text="l.fecha"></td>
                                        <td>
                                            <div class="badge-remate" :class="l.badgeClase" x-text="l.estado"></div>
                                            <div class="listado__tabla-estado" x-text="l.statusValor"></div>
                                        </td>
                                        <td class="es-accion">
                                            <template x-if="l.abierto"><a :href="l.href" x-text="l.cta"></a></template>
                                            <template x-if="!l.abierto"><span>{{ __('listado.resultados.cerrado') }}</span></template>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </template>

                    <template x-if="tarjetas.length === 0">
                        <div class="listado__vacio">
                            <div class="listado__vacio-titulo">{{ __('listado.resultados.sin_resultados') }}</div>
                            <p x-text="sugerencia"></p>
                            <button type="button" @click="limpiar()" x-text="sugerenciaAccion"></button>
                        </div>
                    </template>
                </div>
            </div>
        </div>

        <div class="listado__aviso">
            <div class="listado__aviso-interior contenedor">
                <div>
                    <h2 class="listado__aviso-titulo">{{ __('listado.aviso.titulo') }}</h2>
                    <p>{{ __('listado.aviso.texto') }}</p>
                </div>
                {{-- Bloque M: suscripción real a avisos de remates nuevos. --}}
                <form class="listado__aviso-form" @submit.prevent="suscribir($event.target)">
                    <input type="email" name="email" required placeholder="{{ __('listado.aviso.correo_placeholder') }}" aria-label="{{ __('listado.aviso.correo_etiqueta') }}" value="{{ auth()->user()?->email }}">
                    <button type="submit" :disabled="avisoEnviando">{{ __('listado.aviso.suscribirme') }}</button>
                </form>
                <p class="listado__aviso-resultado" x-show="avisoMensaje" x-text="avisoMensaje" role="status"></p>
            </div>
        </div>

        <div class="listado__como">
            <div class="listado__como-interior contenedor">
                <h2 class="listado__como-titulo">{{ __('listado.como.titulo') }}</h2>
                <div class="listado__como-grilla">
                    <div class="listado__paso">
                        <div class="listado__paso-num">01</div>
                        <h3>{{ __('listado.como.paso1_titulo') }}</h3>
                        <p>{{ __('listado.como.paso1_texto') }}</p>
                    </div>
                    <div class="listado__paso">
                        <div class="listado__paso-num">02</div>
                        <h3>{{ __('listado.como.paso2_titulo') }}</h3>
                        <p>{{ __('listado.como.paso2_texto') }}</p>
                    </div>
                    <div class="listado__paso">
                        <div class="listado__paso-num">03</div>
                        <h3>{{ __('listado.como.paso3_titulo') }}</h3>
                        <p>{{ __('listado.como.paso3_texto') }}</p>
                    </div>
                </div>
                <p class="listado__como-nota">{{ __('listado.como.nota') }}</p>
            </div>
        </div>

        <x-publico.pie borde />
    </div>
</x-layouts.base>
