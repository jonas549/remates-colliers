@php
    use App\Demo\RematesDemo;

    $clp = fn ($n) => RematesDemo::clp($n);
    $logueado = $sesion !== 'visitante';
    $barra = [
        'registrado' => ['clase' => 'registrado', 'msg' => 'Aún no registras una garantía. Necesitas una aprobada por remate para poder pujar.', 'accion' => 'Cómo constituirla'],
        'en-revision' => ['clase' => 'revision', 'msg' => 'Recibimos tu comprobante para Los Militares 5620, Depto. 703. Lo revisamos dentro de 24 horas hábiles.', 'accion' => 'Ver estado'],
        'aprobada' => ['clase' => 'aprobada', 'msg' => 'Garantía aprobada para Los Militares 5620, Depto. 703. Puedes pujar cuando comience el remate.', 'accion' => 'Ir al remate'],
    ][$sesion] ?? null;
    $heroCta = match ($sesion) {
        'visitante' => ['texto' => 'Crear cuenta para pujar', 'href' => route('registro')],
        'aprobada' => ['texto' => 'Ir al remate', 'href' => route('sala.show', $hero['id'])],
        default => ['texto' => 'Constituir la garantía', 'href' => route('cuenta.estado')],
    };
    $opcionesEstado = ['Todos' => 'Todos los remates', 'En vivo' => 'En vivo', 'Próximo' => 'Próximos', 'Cerrado' => 'Cerrados'];
    $opcionesOcupacion = ['Todas' => 'Todas', 'Desocupada' => 'Desocupada', 'Ocupada' => 'Ocupada'];
    $grupos = [
        'Fecha de remate' => ['Esta semana', 'Próximos 30 días', 'Próximos 90 días'],
        'Tipo de propiedad' => ['Departamento', 'Casa'],
        'Características' => ['2 dormitorios o más', '3 dormitorios o más', 'Con estacionamiento', 'Con bodega', 'Con visita programada'],
    ];
    $gruposFinales = [
        'Región' => ['Metropolitana', 'Valparaíso', 'Biobío', 'La Araucanía'],
        'Comuna' => ['Las Condes', 'Providencia', 'Ñuñoa', 'Colina'],
        'Garantía requerida' => ['Hasta $5.000.000', '$5.000.001 a $10.000.000', 'Más de $10.000.000'],
    ];
    $config = [
        'remates' => $remates,
        'sesion' => $sesion,
        'rutas' => ['detalle' => route('remates.show', '__ID__'), 'fotos' => asset('img/demo')],
    ];
@endphp
<x-layouts.base titulo="Remates" pagina-completa>
    <div class="listado" x-data="listado(@js($config))">

        <x-publico.cabecera :sesion="$sesion">
            @if ($barra)
                <div class="pub-garantia pub-garantia--{{ $barra['clase'] }}">
                    <div class="pub-garantia__interior contenedor">
                        <span class="pub-garantia__chip">GARANTÍA</span>
                        <span>{{ $barra['msg'] }}</span>
                        <a href="{{ $sesion === 'aprobada' ? route('sala.show', $hero['id']) : route('cuenta.estado') }}" class="pub-garantia__accion">{{ $barra['accion'] }}</a>
                    </div>
                </div>
            @endif
        </x-publico.cabecera>

        <div class="listado__encabezado contenedor">
            <div class="listado__migas">Inicio <span>/</span> Remates</div>
            <div class="listado__encabezado-grilla">
                <div>
                    <div class="listado__kicker">REMATES INMOBILIARIOS</div>
                    <h1 class="listado__titulo">Casas y departamentos en remate</h1>
                    <p class="listado__bajada">Propiedades adjudicadas en subasta pública en línea, con transmisión en vivo y cierre automático al vencer el tiempo. Todas las pujas se cursan en pesos chilenos.</p>
                </div>
                <div class="listado__uf">
                    <div>
                        <div class="listado__uf-etiqueta">UF DE HOY</div>
                        <div class="listado__uf-valor">$39.412,73</div>
                        <div class="listado__uf-fecha">31 de agosto de 2026</div>
                    </div>
                    <div class="listado__uf-divisor"></div>
                    <p>Valor de referencia informativa. Los precios base, las garantías y las pujas se expresan y se pagan en pesos (CLP).</p>
                </div>
            </div>
        </div>

        <div class="listado__hero">
            <div class="listado__hero-interior contenedor">
                <div>
                    <div class="listado__hero-chip">PRÓXIMO REMATE</div>
                    <h2 class="listado__hero-titulo">{{ $hero['direccion'] }}</h2>
                    <div class="listado__hero-meta">{{ $hero['comuna'] }}, {{ $hero['region'] }} · {{ $hero['tipo'] }} · {{ $hero['sup'] }} m² útiles · {{ $hero['dorm'] }}D / {{ $hero['banos'] }}B · {{ $hero['ocupacion'] }}</div>
                    <div class="listado__contador">
                        @foreach (['d' => 'DÍAS', 'h' => 'HORAS', 'm' => 'MIN', 's' => 'SEG'] as $parte => $etiqueta)
                            <div class="listado__caja-tiempo">
                                <div class="listado__digito" x-text="hero({{ $hero['delta'] }}).{{ $parte }}"></div>
                                <div class="listado__caja-etiqueta">{{ $etiqueta }}</div>
                            </div>
                        @endforeach
                    </div>
                    <div class="listado__hero-plazo">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#ffd400" stroke-width="1.5"><circle cx="12" cy="12" r="9"></circle><path d="M12 7v5l3 2"></path></svg>
                        <span>Garantía aprobada a más tardar el</span>
                        <span>07-09-2026, 18:00</span>
                    </div>
                    <div class="listado__hero-datos">
                        <div class="listado__hero-dato">
                            <div class="listado__hero-dato-etiqueta">PRECIO BASE</div>
                            <div class="listado__hero-dato-valor">{{ $clp($hero['precio']) }}</div>
                        </div>
                        <div class="listado__hero-dato">
                            <div class="listado__hero-dato-etiqueta">GARANTÍA REQUERIDA</div>
                            <div class="listado__hero-dato-valor">{{ $clp($hero['garantia']) }}</div>
                        </div>
                        <div class="listado__hero-dato">
                            <div class="listado__hero-dato-etiqueta">INICIO</div>
                            <div class="listado__hero-dato-valor">{{ $hero['fecha'] }}</div>
                        </div>
                    </div>
                    <div class="listado__hero-acciones">
                        <a href="{{ $heroCta['href'] }}" class="listado__hero-cta">{{ $heroCta['texto'] }}</a>
                        <a href="{{ route('remates.show', $hero['id']) }}" class="listado__hero-cta2">Ver bases y condiciones</a>
                        <a href="#" class="listado__hero-visita">Agendar visita a la propiedad</a>
                    </div>
                    <p class="listado__hero-nota">Martillero: <span>{{ $hero['martillero'] }}</span>. La garantía se constituye por vale a la vista o transferencia y es revisada manualmente por Colliers.</p>
                </div>
                <div class="listado__hero-foto">
                    <x-imagen-slot :src="asset('img/demo/prop-hero.jpg')" alt="Foto de la propiedad destacada" />
                </div>
            </div>
        </div>

        <div class="listado__lista contenedor">
            <div class="listado__herramientas">
                <button type="button" class="listado__btn-filtros" @click="alternarFiltros()">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#25408f" stroke-width="1.5"><path d="M3 5h18M6 12h12M10 19h4"></path></svg>
                    <span x-text="filtrosAbiertos && !compacto ? 'Ocultar filtros' : 'Filtros'">Ocultar filtros</span>
                </button>
                <label class="listado__buscador">
                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="#5b6572" stroke-width="1.5"><circle cx="11" cy="11" r="7"></circle><path d="m20 20-3.5-3.5"></path></svg>
                    <input :value="q" @input="buscar($event.target.value)" placeholder="Busca por dirección, comuna o tipo" aria-label="Buscar remates">
                </label>
                <select class="listado__orden" x-model="orden" aria-label="Ordenar remates">
                    <option value="fecha">Ordenar por: fecha de remate</option>
                    <option value="precio-asc">Ordenar por: precio base (menor a mayor)</option>
                    <option value="precio-desc">Ordenar por: precio base (mayor a menor)</option>
                    <option value="superficie">Ordenar por: superficie</option>
                </select>
                <div class="listado__vista">
                    <button type="button" :class="{ 'es-activa': vista === 'Grilla' }" @click="vista = 'Grilla'" class="es-activa">Grilla</button>
                    <button type="button" :class="{ 'es-activa': vista === 'Tabla' }" @click="vista = 'Tabla'">Tabla</button>
                </div>
            </div>

            <div class="listado__layout" :class="{ 'sin-filtros': !filtrosAbiertos }">

                <template x-if="compacto && filtrosAbiertos">
                    <div class="listado__velo" @click="alternarFiltros()"></div>
                </template>

                <template x-if="filtrosAbiertos">
                    <div class="listado__filtros" @keydown.escape.window="compacto && alternarFiltros()">
                        <div class="listado__filtros-cabeza">
                            <div>Filtros</div>
                            <button type="button" @click="alternarFiltros()" aria-label="Cerrar filtros">✕</button>
                        </div>

                        <details open class="listado__grupo" x-data="{ abierto: true }" @toggle="abierto = $el.open">
                            <summary>Estado del remate <span x-text="abierto ? '–' : '+'">–</span></summary>
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
                            <summary>Ocupación <span x-text="abierto ? '–' : '+'">–</span></summary>
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

                        @foreach ($grupos as $titulo => $items)
                            <details class="listado__grupo" x-data="{ abierto: false }" @toggle="abierto = $el.open">
                                <summary>{{ $titulo }} <span x-text="abierto ? '–' : '+'">+</span></summary>
                                <div class="listado__opciones">
                                    @foreach ($items as $item)
                                        <label class="listado__opcion"><input type="checkbox">{{ $item }}</label>
                                    @endforeach
                                </div>
                            </details>
                        @endforeach

                        <details class="listado__grupo" x-data="{ abierto: false }" @toggle="abierto = $el.open">
                            <summary>Rango de precio base <span x-text="abierto ? '–' : '+'">+</span></summary>
                            <div class="listado__rango">
                                <input placeholder="Desde CLP" aria-label="Precio base desde">
                                <input placeholder="Hasta CLP" aria-label="Precio base hasta">
                            </div>
                        </details>

                        @foreach ($gruposFinales as $titulo => $items)
                            <details class="listado__grupo" x-data="{ abierto: false }" @toggle="abierto = $el.open">
                                <summary>{{ $titulo }} <span x-text="abierto ? '–' : '+'">+</span></summary>
                                <div class="listado__opciones">
                                    @foreach ($items as $item)
                                        <label class="listado__opcion"><input type="checkbox">{{ $item }}</label>
                                    @endforeach
                                </div>
                            </details>
                        @endforeach

                        <div class="listado__filtros-acciones">
                            <button type="button" class="listado__limpiar" @click="limpiar()">Limpiar filtros</button>
                            <button type="button" class="listado__ver-resultados" @click="alternarFiltros()" x-text="'Ver ' + filtrados.length + ' remates'"></button>
                        </div>
                    </div>
                </template>

                <div>
                    <div class="listado__resultados-cabeza">
                        <div class="listado__conteo" x-text="conteo"></div>
                        <div class="listado__nota-cierre">Los remates cierran automáticamente al vencer el tiempo, sin extensiones.</div>
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
                                                    <button type="button" class="tarjeta__guardar" :class="{ 'es-guardado': l.guardado }" title="Guardar remate" :aria-pressed="l.guardado" @click="alternarGuardado(l.id)">
                                                        <svg width="18" height="18" viewBox="0 0 24 24" :fill="l.guardado ? '#25408f' : 'none'" stroke="currentColor" stroke-width="1.5"><path d="M6 3h12v18l-6-4.5L6 21Z"></path></svg>
                                                    </button>
                                                </div>
                                                <div class="tarjeta__precio">
                                                    <div class="tarjeta__precio-etiqueta">PRECIO BASE</div>
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
                                                    <div class="tarjeta__dato-etiqueta">REMATE</div>
                                                    <div class="tarjeta__dato-valor" x-text="l.fecha"></div>
                                                </div>
                                                <div>
                                                    <div class="tarjeta__dato-etiqueta">GARANTÍA</div>
                                                    <div class="tarjeta__dato-valor" x-text="l.garantia"></div>
                                                    <div class="tarjeta__dato-limite" x-text="l.limite"></div>
                                                </div>
                                            </div>
                                            <template x-if="l.visita">
                                                <div class="tarjeta__visita">
                                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#25408f" stroke-width="1.5"><rect x="3" y="5" width="18" height="16"></rect><path d="M3 10h18M8 3v4M16 3v4"></path></svg>
                                                    <span>Visita a la propiedad:</span>
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
                                                        <div class="tarjeta__cerrado-caja">Remate cerrado</div>
                                                        <template x-if="l.nuevoRemate">
                                                            <a href="#" class="tarjeta__nuevo" x-text="'Se republicó en un remate nuevo: ' + l.nuevoRemate"></a>
                                                        </template>
                                                    </div>
                                                </template>
                                                <template x-if="l.abierto">
                                                    <a :href="l.href" class="tarjeta__cta" x-text="l.cta"></a>
                                                </template>
                                                <div class="tarjeta__enlaces">
                                                    <a href="#">Bases del remate</a>
                                                    <span>Martillero: <span x-text="l.martillero"></span></span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </template>
                            </div>
                            <template x-if="hayMas">
                                <div class="listado__mas">
                                    <button type="button" @click="visibles += 6">Cargar más remates</button>
                                </div>
                            </template>
                        </div>
                    </template>

                    <template x-if="esTabla">
                        <table class="listado__tabla">
                            <thead>
                                <tr>
                                    <th>PROPIEDAD</th>
                                    <th>TIPO / SUP.</th>
                                    <th>OCUPACIÓN</th>
                                    <th>PRECIO BASE</th>
                                    <th>GARANTÍA</th>
                                    <th>REMATE</th>
                                    <th>ESTADO</th>
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
                                            <template x-if="!l.abierto"><span>Cerrado</span></template>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </template>

                    <template x-if="tarjetas.length === 0">
                        <div class="listado__vacio">
                            <div class="listado__vacio-titulo">Sin remates para esta combinación</div>
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
                    <h2 class="listado__aviso-titulo">Avísame de los próximos remates</h2>
                    <p>Recibe un correo cuando se publique un nuevo remate de casas y departamentos, y un recordatorio 48 horas antes del cierre de garantías.</p>
                </div>
                <div class="listado__aviso-form">
                    <input placeholder="tu@correo.cl" aria-label="Correo electrónico">
                    <button type="button">Suscribirme</button>
                </div>
            </div>
        </div>

        <div class="listado__como">
            <div class="listado__como-interior contenedor">
                <h2 class="listado__como-titulo">Cómo participar</h2>
                <div class="listado__como-grilla">
                    <div class="listado__paso">
                        <div class="listado__paso-num">01</div>
                        <h3>Crea tu cuenta</h3>
                        <p>Regístrate con tu RUT y antecedentes. Puedes revisar todos los remates sin cuenta, pero necesitas una para pujar.</p>
                    </div>
                    <div class="listado__paso">
                        <div class="listado__paso-num">02</div>
                        <h3>Constituye la garantía</h3>
                        <p>Por vale a la vista o transferencia, fuera de la plataforma. Colliers revisa el comprobante y aprueba tu participación de forma manual, hasta 48 horas antes del remate.</p>
                    </div>
                    <div class="listado__paso">
                        <div class="listado__paso-num">03</div>
                        <h3>Puja durante la transmisión</h3>
                        <p>El remate se transmite en vivo por el canal de YouTube de Colliers. Las pujas se ingresan en pesos y el remate cierra automáticamente al vencer el tiempo.</p>
                    </div>
                </div>
                <p class="listado__como-nota">Colliers puede cerrar un remate de forma anticipada. Si un remate no se concreta, la propiedad se publica en un remate nuevo con fecha y condiciones propias.</p>
            </div>
        </div>

        <x-publico.pie borde />
    </div>
</x-layouts.base>
