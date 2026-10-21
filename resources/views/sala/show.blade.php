@php
    /*
     * Sala de puja. Datos desde SalaController: `componente` es salaPuja (motor real, Bloque K) o salaPujaDemo
     * (datos fijos del prototipo, solo local con ?demo=1, para la comparación visual 1:1).
     */
    $clp = fn ($n) => \App\Support\Formato::clp($n);
    // Enlaces a datos que cambian con el lote vigente: solo en la sala real (la demo tiene un solo lote fijo).
    $real = $componente === 'salaPuja';
@endphp
<x-layouts.base :titulo="__('sala.titulo')" clase-cuerpo="">
    <div class="sala" :class="{ 'es-sin-conexion': sinConexion }" x-data="{{ $componente }}(@js($config))" @keydown.escape.window="modal = false; hoja = false">

        <div class="sala-cabecera">
            <div class="sala-cabecera__nav contenedor">
                <a href="{{ route('remates.index') }}"><img src="{{ asset('img/colliers-logo.png') }}" alt="Colliers" class="sala-cabecera__logo"></a>
                <div class="sala-cabecera__titulo">{{ __('sala.titulo') }} · <span>{{ $propiedad['folio'] }}</span>@if ($real)<span x-show="etiquetaLote" x-text="' · ' + etiquetaLote.toUpperCase()"></span>@endif</div>
                <div class="sala-cabecera__derecha">
                    <x-publico.idioma class="pub-cabecera__idioma--siempre" />
                    <span class="sala-cabecera__chip">{{ __('sala.cabecera.garantia_aprobada') }}</span>
                    {{-- El nombre lleva a «Mi cuenta» (21/09): la sala no tenía salida hacia la cuenta del postor. --}}
                    @if ($real)
                        <a href="{{ route('cuenta.estado') }}" class="sala-cabecera__usuario sala-cabecera__usuario--enlace" title="{{ __('sala.cabecera.ir_a_mi_cuenta') }}">{{ $propiedad['usuario'] }}</a>
                    @else
                        <span class="sala-cabecera__usuario">{{ $propiedad['usuario'] }}</span>
                    @endif
                </div>
            </div>
            <div class="sala-ticker" :class="{ 'es-cerrado': vencido }">
                <span class="sala-ticker__estado">
                    <span class="sala-ticker__punto"></span>
                    <span @if ($real) x-text="estadoConLote" @else x-text="estadoTexto" @endif></span>
                </span>
                <span class="sala-ticker__direccion" @if ($real) x-text="info.direccion" @endif>{{ $propiedad['direccion'] }}</span>
                <span class="sala-ticker__tiempo" x-text="tiempoTexto"></span>
            </div>
        </div>
        @if ($real)
            {{-- Sin conexión (QA 21/10): se pega arriba al hacer scroll, en todos los anchos; Pujar queda bloqueado hasta que vuelva el estado. --}}
            <div class="sala-conexion" x-show="sinConexion" x-cloak role="alert" data-sin-conexion>
                <strong>{{ __('sala.conexion.titulo') }}</strong> {{ __('sala.conexion.texto') }}
            </div>
        @endif

        <div class="sala__contenido contenedor">
            <div class="sala__layout">

                <div class="sala__principal">
                    @if ($real)
                        @include('sala._avisos', ['donde' => 'contenido'])
                    @endif
                    <div class="sala__video">
                        @if ($real)
                            {{-- El video llega en el estado público: aparece, cambia o se quita sin recargar (QA 21/10). x-for con
                                 clave: el iframe se crea una vez por video y nunca se le vuelve a fijar el src (eso lo recargaría). --}}
                            <template x-for="id in (video ? [video] : [])" :key="id">
                                <iframe :src="videoSrc" title="{{ __('sala.video.titulo') }}" allow="accelerometer; autoplay; clipboard-write; encrypted-media; picture-in-picture" allowfullscreen></iframe>
                            </template>
                        @elseif ($propiedad['video'])
                            <iframe src="https://www.youtube-nocookie.com/embed/{{ $propiedad['video'] }}?rel=0" title="{{ __('sala.video.titulo') }}" allow="accelerometer; autoplay; clipboard-write; encrypted-media; picture-in-picture" allowfullscreen></iframe>
                        @endif
                        @if ($real || ! $propiedad['video'])
                            {{-- QA 21/10, 3.1: sin video cargado no queda un recuadro negro. Se puede pujar igual. --}}
                            <div class="sala__sin-video" data-sin-video @if ($real) x-show="! video" @if ($propiedad['video']) x-cloak @endif @endif>
                                <strong class="sala__sin-video-titulo">{{ __('sala.video.sin_transmision.titulo') }}</strong>
                                <p>{{ __('sala.video.sin_transmision.texto') }}</p>
                                <p class="sala__sin-video-nota">{{ __('sala.video.sin_transmision.recargar') }}</p>
                                @if ($propiedad['canal'] ?? null)
                                    <a href="{{ $propiedad['canal'] }}" target="_blank" rel="noopener" class="sala__sin-video-canal">{{ __('sala.video.sin_transmision.ver_canal') }}</a>
                                @endif
                            </div>
                        @endif
                    </div>
                    <div class="sala__video-pie">
                        <span>{{ __('sala.video.canal') }}{!! $propiedad['martillero'] ? ' · ' . e(__('sala.video.martillero')) . ' <span>' . e($propiedad['martillero']) . '</span>' : '' !!}</span>
                        {{-- Acta: el cronómetro y el precio de la plataforma son la fuente oficial, no el video (10–30 s de retraso). --}}
                        <span class="sala__video-oficial">{{ __('sala.video.oficial') }}</span>
                    </div>

                    <div class="sala__ficha">
                        <div class="sala__ficha-fila">
                            <div style="min-width: 0">
                                <h1 class="sala__titulo" @if ($real) x-text="info.direccion" @endif>{{ $propiedad['direccion'] }}</h1>
                                <div class="sala__meta" @if ($real) x-text="info.meta" @endif>{{ $propiedad['meta'] }}</div>
                            </div>
                            <a href="{{ route('remates.show', $propiedad['slug']) }}" class="sala__antecedentes">{{ __('sala.ficha.ver_antecedentes') }}</a>
                        </div>
                        <div class="sala__datos">
                            <div class="sala__dato"><div class="sala__dato-etiqueta">{{ __('sala.ficha.precio_base') }}</div><div class="sala__dato-valor" @if ($real) x-text="formatoClp(info.base)" @endif>{{ $clp($propiedad['base']) }}</div></div>
                            <div class="sala__dato"><div class="sala__dato-etiqueta">{{ __('sala.ficha.incremento_minimo') }}</div><div class="sala__dato-valor">{{ $clp($propiedad['incremento']) }}</div></div>
                            <div class="sala__dato"><div class="sala__dato-etiqueta">{{ __('sala.ficha.tu_garantia') }}</div><div class="sala__dato-valor">{{ $clp($propiedad['garantia']) }}</div></div>
                            <div class="sala__dato"><div class="sala__dato-etiqueta">{{ __('sala.ficha.referencia_uf') }}</div><div class="sala__dato-valor" x-text="actualEnUf"></div></div>
                        </div>
                    </div>

                    <div class="sala__historial">
                        <div class="sala__historial-cabeza">
                            <h2 class="sala__historial-titulo">{{ __('sala.historial.titulo') }}</h2>
                            {{-- Presencia (sin diseño): el número de postores con la sala abierta, en la misma línea del conteo. Solo el número. --}}
                            <span class="sala__historial-conteo">{!! __('sala.historial.conteo', ['total' => '<span x-text="totalPujas"></span>', 'conectados' => $real ? '<span class="sala__conectados" x-show="conectadosTexto" x-text="\' · \' + conectadosTexto"></span>' : '']) !!}</span>
                        </div>
                        <div class="sala__historial-lista">
                            <template x-for="(p, i) in historialVista" :key="p.hora + p.monto">
                                <div class="historial-puja" :class="{ 'es-primera': i === 0, 'es-mia': p.yo }">
                                    <div style="min-width: 0">
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
                        <div class="sala__historial-nota">{{ __('sala.historial.nota') }}</div>
                    </div>
                </div>

                <div class="sala__lateral">
                    <div class="sala-panel">
                        <div class="sala-panel__cabeza" :class="{ 'es-cerrado': vencido }">
                            <span class="sala-panel__estado" @if ($real) x-text="estadoConLote" @else x-text="estadoTexto" @endif></span>
                            <span class="sala-panel__folio">{{ $propiedad['folio'] }}</span>
                        </div>
                        <div class="sala-panel__cuerpo">
                            @if ($real)
                                @include('sala._avisos', ['donde' => 'panel'])
                            @endif
                            <div class="sala-panel__etiqueta">{{ __('sala.panel.precio_actual') }}</div>
                            <template x-for="precio in [actualTexto]" :key="precio">
                                <div class="sala-panel__precio" x-text="precio"></div>
                            </template>
                            <div class="sala-panel__base">{!! __('sala.panel.base', ['base' => '<span' . ($real ? '  x-text="formatoClp(info.base)" ' : ' ') . '>' . e($clp($propiedad['base'])) . '</span>', 'sobre' => '<span x-text="sobreBase"></span>']) !!}</div>

                            <div class="sala-estado" :class="{ 'es-ganando': yoGanando }">
                                <template x-if="yoGanando">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M5 13l4 4L19 7"></path></svg>
                                </template>
                                <template x-if="!yoGanando">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M10.3 3.9 1.9 18a2 2 0 0 0 1.7 3h16.8a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z"></path><path d="M12 9v4M12 17h.01"></path></svg>
                                </template>
                                <div>
                                    <div class="sala-estado__titulo" x-text="estadoTitulo"></div>
                                    <div class="sala-estado__texto" x-text="estadoDetalle"></div>
                                </div>
                            </div>

                            <div class="sala-panel__etiqueta sala-panel__etiqueta--cierra" x-text="etiquetaContador">{{ __('sala.panel.cierra_en') }}</div>
                            <div class="sala-contador" :class="{ 'es-final': enMinutoFinal }">
                                <div class="sala-contador__caja"><div class="sala-contador__digito" x-text="hh"></div><div class="sala-contador__etiqueta">{{ __('sala.panel.horas') }}</div></div>
                                <div class="sala-contador__caja"><div class="sala-contador__digito" x-text="mm"></div><div class="sala-contador__etiqueta">{{ __('sala.panel.min') }}</div></div>
                                <div class="sala-contador__caja"><div class="sala-contador__digito" x-text="ss"></div><div class="sala-contador__etiqueta">{{ __('sala.panel.seg') }}</div></div>
                            </div>
                            <div class="sala-panel__sin-extension">{{ __('sala.panel.sin_extension') }}</div>

                            <template x-if="!vencido">
                                @include('sala._formulario-puja', ['id' => 'panel'])
                            </template>
                            <template x-if="vencido">
                                <div class="sala-resultado" :class="{ 'es-desierto': resultadoDesierto }">
                                    <div class="sala-resultado__titulo" x-text="resultadoTitulo"></div>
                                    <div class="sala-resultado__texto" x-show="resultadoTexto" x-text="resultadoTexto"></div>
                                    {{-- Varios lotes: cómo terminó cada uno para este postor (QA 21/10). --}}
                                    <template x-if="resumenLotes.length">
                                        <ul class="sala-resultado__lotes" data-resumen-lotes>
                                            <template x-for="r in resumenLotes" :key="r.orden">
                                                <li :class="{ 'es-ganado': r.gane, 'es-desierto': !r.adjudicado }" x-text="r.texto"></li>
                                            </template>
                                        </ul>
                                    </template>
                                    @if ($real)
                                        <a x-show="retirado === 'cancelado'" href="{{ route('cuenta.estado') }}" class="sala-resultado__enlace">{{ __('sala.cabecera.ir_a_mi_cuenta') }}</a>
                                        <a x-show="retirado === 'no-disponible'" href="{{ route('remates.index') }}" class="sala-resultado__enlace">{{ __('sala.panel.ver_remates') }}</a>
                                    @endif
                                </div>
                            </template>
                        </div>
                    </div>

                    <div class="sala-soporte">
                        <div class="sala-soporte__titulo">{{ __('sala.soporte.titulo') }}</div>
                        <p>{{ __('sala.soporte.texto') }}</p>
                        <div class="sala-soporte__enlaces">
                            <a href="{{ \App\Support\Sitio::telefonoEnlace() }}">{{ \App\Support\Sitio::telefono() }}</a>
                            <a href="mailto:{{ \App\Support\Sitio::correo() }}">{{ \App\Support\Sitio::correo() }}</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Barra fija inferior y hoja de puja (solo < 1120px, ver sala.css) --}}
        <div class="sala-barra" role="region" aria-label="{{ __('sala.barra.aria') }}">
            <div class="sala-barra__datos">
                <div class="sala-barra__fila">
                    <span class="sala-barra__precio" x-text="actualTexto"></span>
                    <span class="sala-barra__tiempo" :class="{ 'es-final': enMinutoFinal }" x-text="vencido ? {{ Js::from(__('sala.barra.finalizado')) }} : hh + ':' + mm + ':' + ss"></span>
                </div>
                <span class="sala-barra__estado" :class="{ 'es-ganando': yoGanando && !vencido && !sinConexion, 'es-cerrado': vencido }" x-text="sinConexion ? {{ Js::from(__('sala.conexion.barra')) }} : (vencido ? (resumenLotes.length ? resultadoTitulo.toUpperCase() : estadoTexto) : estadoTitulo.toUpperCase())"></span>
            </div>
            @if ($real)
                {{-- Remate cancelado o retirado: en vez de un «Pujar» apagado, la salida a «Mi cuenta». --}}
                <a x-show="retirado === 'cancelado'" x-cloak href="{{ route('cuenta.estado') }}" class="sala-barra__boton sala-barra__boton--enlace">{{ __('sala.barra.mi_cuenta') }}</a>
                <a x-show="retirado === 'no-disponible'" x-cloak href="{{ route('remates.index') }}" class="sala-barra__boton sala-barra__boton--enlace">{{ __('sala.barra.remates') }}</a>
                <button type="button" x-show="!retirado" class="sala-barra__boton" :disabled="vencido || sinConexion" @click="hoja = true" aria-haspopup="dialog">{{ __('sala.barra.pujar') }}</button>
            @else
                <button type="button" class="sala-barra__boton" :disabled="vencido" @click="hoja = true" aria-haspopup="dialog">{{ __('sala.barra.pujar') }}</button>
            @endif
        </div>

        <template x-if="hoja && !vencido">
            <div>
                <div class="sala-hoja__velo" @click="hoja = false"></div>
                <div class="sala-hoja" role="dialog" aria-modal="true" aria-label="{{ __('sala.hoja.titulo') }}">
                    <div class="sala-hoja__cabeza">
                        <h2 class="sala-hoja__titulo">{{ __('sala.hoja.titulo') }}</h2>
                        <button type="button" class="sala-hoja__cerrar" @click="hoja = false" aria-label="{{ __('sala.hoja.cerrar') }}">✕</button>
                    </div>
                    <div class="sala-hoja__resumen">{!! __('sala.hoja.resumen', ['precio' => '<strong x-text="actualTexto"></strong>', 'tiempo' => '<span x-text="hh + \':\' + mm + \':\' + ss"></span>']) !!}</div>
                    @include('sala._formulario-puja', ['id' => 'hoja'])
                </div>
            </div>
        </template>

        <template x-if="modal">
            <div class="sala-modal" role="dialog" aria-modal="true" aria-labelledby="titulo-confirmacion">
                <div class="sala-modal__caja" x-init="$nextTick(() => $el.querySelector('.sala-modal__cancelar').focus())">
                    <div class="sala-modal__cabeza">
                        <h2 class="sala-modal__titulo" id="titulo-confirmacion">{{ __('sala.modal.titulo') }}</h2>
                    </div>
                    <div class="sala-modal__cuerpo">
                        <div class="sala-modal__propiedad">{{ __('sala.modal.propiedad', ['direccion' => $propiedad['direccion']]) }}</div>
                        <div class="sala-modal__monto" x-text="modalMonto"></div>
                        <div class="sala-modal__detalle"><span x-text="modalUf ? modalUf + ' · ' : ''"></span>{!! __('sala.modal.supera', ['diferencia' => '<span x-text="modalDiferencia"></span>']) !!}</div>
                        <div class="sala-modal__aviso">{{ __('sala.modal.aviso', ['garantia' => $clp($propiedad['garantia'])]) }}</div>
                        <div class="sala-modal__botones">
                            <button type="button" class="sala-modal__confirmar" :disabled="enviando || sinConexion" @click="confirmar()">{{ __('sala.modal.confirmar') }}</button>
                            <button type="button" class="sala-modal__cancelar" @click="modal = false">{{ __('sala.modal.cancelar') }}</button>
                        </div>
                    </div>
                </div>
            </div>
        </template>
    </div>
</x-layouts.base>
