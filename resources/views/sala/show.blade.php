@php
    /*
     * Sala de puja. Datos desde SalaController: `componente` es salaPuja (motor real, Bloque K) o salaPujaDemo
     * (datos fijos del prototipo, solo local con ?demo=1, para la comparación visual 1:1).
     */
    $clp = fn ($n) => \App\Demo\RematesDemo::clp($n);
    // Enlaces a datos que cambian con el lote vigente: solo en la sala real (la demo tiene un solo lote fijo).
    $real = $componente === 'salaPuja';
@endphp
<x-layouts.base titulo="Sala de pujas" clase-cuerpo="">
    <div class="sala" x-data="{{ $componente }}(@js($config))" @keydown.escape.window="modal = false; hoja = false">

        <div class="sala-cabecera">
            <div class="sala-cabecera__nav contenedor">
                <a href="{{ route('remates.index') }}"><img src="{{ asset('img/colliers-logo.png') }}" alt="Colliers" class="sala-cabecera__logo"></a>
                <div class="sala-cabecera__titulo">Sala de pujas · <span>{{ $propiedad['folio'] }}</span>@if ($real)<span x-show="etiquetaLote" x-text="' · ' + etiquetaLote.toUpperCase()"></span>@endif</div>
                <div class="sala-cabecera__derecha">
                    <span class="sala-cabecera__chip">GARANTÍA APROBADA</span>
                    <span class="sala-cabecera__usuario">{{ $propiedad['usuario'] }}</span>
                </div>
            </div>
            <div class="sala-ticker" :class="{ 'es-cerrado': vencido }">
                <span class="sala-ticker__estado">
                    <span class="sala-ticker__punto"></span>
                    <span x-text="estadoTexto"></span>
                </span>
                <span class="sala-ticker__direccion" @if ($real) x-text="info.direccion" @endif>{{ $propiedad['direccion'] }}</span>
                <span class="sala-ticker__tiempo" x-text="tiempoTexto"></span>
            </div>
        </div>

        <div class="sala__contenido contenedor">
            <div class="sala__layout">

                <div class="sala__principal">
                    @if ($real)
                        @include('sala._avisos', ['donde' => 'contenido'])
                    @endif
                    <div class="sala__video">
                        @if ($propiedad['video'])
                            <iframe src="https://www.youtube-nocookie.com/embed/{{ $propiedad['video'] }}?rel=0" title="Transmisión en vivo del remate" allow="accelerometer; autoplay; clipboard-write; encrypted-media; picture-in-picture" allowfullscreen></iframe>
                        @endif
                    </div>
                    <div class="sala__video-pie">
                        <span>Transmisión del canal de Colliers Chile{!! $propiedad['martillero'] ? ' · Martillero <span>' . e($propiedad['martillero']) . '</span>' : '' !!}</span>
                        {{-- Acta: el cronómetro y el precio de la plataforma son la fuente oficial, no el video (10–30 s de retraso). --}}
                        <span class="sala__video-oficial">Precio y cronómetro oficiales: el video tiene 10–30 s de retraso</span>
                    </div>

                    <div class="sala__ficha">
                        <div class="sala__ficha-fila">
                            <div style="min-width: 0">
                                <h1 class="sala__titulo" @if ($real) x-text="info.direccion" @endif>{{ $propiedad['direccion'] }}</h1>
                                <div class="sala__meta" @if ($real) x-text="info.meta" @endif>{{ $propiedad['meta'] }}</div>
                            </div>
                            <a href="{{ route('remates.show', $propiedad['slug']) }}" class="sala__antecedentes">Ver antecedentes</a>
                        </div>
                        <div class="sala__datos">
                            <div class="sala__dato"><div class="sala__dato-etiqueta">PRECIO BASE</div><div class="sala__dato-valor" @if ($real) x-text="formatoClp(info.base)" @endif>{{ $clp($propiedad['base']) }}</div></div>
                            <div class="sala__dato"><div class="sala__dato-etiqueta">INCREMENTO MÍNIMO</div><div class="sala__dato-valor">{{ $clp($propiedad['incremento']) }}</div></div>
                            <div class="sala__dato"><div class="sala__dato-etiqueta">TU GARANTÍA</div><div class="sala__dato-valor">{{ $clp($propiedad['garantia']) }}</div></div>
                            <div class="sala__dato"><div class="sala__dato-etiqueta">REFERENCIA UF</div><div class="sala__dato-valor" x-text="actualEnUf"></div></div>
                        </div>
                    </div>

                    <div class="sala__historial">
                        <div class="sala__historial-cabeza">
                            <h2 class="sala__historial-titulo">Historial de pujas</h2>
                            <span class="sala__historial-conteo"><span x-text="totalPujas"></span> POSTURAS · TIEMPO REAL</span>
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
                        <div class="sala__historial-nota">El remate cierra automáticamente al vencer el temporizador, sin extensiones. Colliers puede cerrarlo de forma anticipada.</div>
                    </div>
                </div>

                <div class="sala__lateral">
                    <div class="sala-panel">
                        <div class="sala-panel__cabeza" :class="{ 'es-cerrado': vencido }">
                            <span class="sala-panel__estado" x-text="estadoTexto"></span>
                            <span class="sala-panel__folio">{{ $propiedad['folio'] }}</span>
                        </div>
                        <div class="sala-panel__cuerpo">
                            @if ($real)
                                @include('sala._avisos', ['donde' => 'panel'])
                            @endif
                            <div class="sala-panel__etiqueta">PRECIO ACTUAL</div>
                            <template x-for="precio in [actualTexto]" :key="precio">
                                <div class="sala-panel__precio" x-text="precio"></div>
                            </template>
                            <div class="sala-panel__base">Base <span @if ($real) x-text="formatoClp(info.base)" @endif>{{ $clp($propiedad['base']) }}</span> · <span x-text="sobreBase"></span> sobre el mínimo</div>

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

                            <div class="sala-panel__etiqueta sala-panel__etiqueta--cierra" x-text="etiquetaContador">CIERRA EN</div>
                            <div class="sala-contador" :class="{ 'es-final': enMinutoFinal }">
                                <div class="sala-contador__caja"><div class="sala-contador__digito" x-text="hh"></div><div class="sala-contador__etiqueta">HORAS</div></div>
                                <div class="sala-contador__caja"><div class="sala-contador__digito" x-text="mm"></div><div class="sala-contador__etiqueta">MIN</div></div>
                                <div class="sala-contador__caja"><div class="sala-contador__digito" x-text="ss"></div><div class="sala-contador__etiqueta">SEG</div></div>
                            </div>
                            <div class="sala-panel__sin-extension">Sin extensiones por posturas de último minuto.</div>

                            <template x-if="!vencido">
                                @include('sala._formulario-puja', ['id' => 'panel'])
                            </template>
                            <template x-if="vencido">
                                <div class="sala-resultado" :class="{ 'es-desierto': !hayPujas }">
                                    <div class="sala-resultado__titulo" x-text="resultadoTitulo"></div>
                                    <div class="sala-resultado__texto" x-text="resultadoTexto"></div>
                                </div>
                            </template>
                        </div>
                    </div>

                    <div class="sala-soporte">
                        <div class="sala-soporte__titulo">SOPORTE DURANTE EL REMATE</div>
                        <p>Si se corta la transmisión o tienes un problema para pujar, llámanos: el remate no se detiene.</p>
                        <div class="sala-soporte__enlaces">
                            <a href="tel:+56227603535">+56 2 2760 3535</a>
                            <a href="mailto:remates@colliers.cl">remates@colliers.cl</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Barra fija inferior y hoja de puja (solo < 1120px, ver sala.css) --}}
        <div class="sala-barra" role="region" aria-label="Estado de la puja">
            <div class="sala-barra__datos">
                <div class="sala-barra__fila">
                    <span class="sala-barra__precio" x-text="actualTexto"></span>
                    <span class="sala-barra__tiempo" :class="{ 'es-final': enMinutoFinal }" x-text="vencido ? 'Finalizado' : hh + ':' + mm + ':' + ss"></span>
                </div>
                <span class="sala-barra__estado" :class="{ 'es-ganando': yoGanando && !vencido, 'es-cerrado': vencido }" x-text="vencido ? estadoTexto : estadoTitulo.toUpperCase()"></span>
            </div>
            <button type="button" class="sala-barra__boton" :disabled="vencido" @click="hoja = true" aria-haspopup="dialog">Pujar</button>
        </div>

        <template x-if="hoja && !vencido">
            <div>
                <div class="sala-hoja__velo" @click="hoja = false"></div>
                <div class="sala-hoja" role="dialog" aria-modal="true" aria-label="Ingresar puja">
                    <div class="sala-hoja__cabeza">
                        <h2 class="sala-hoja__titulo">Ingresar puja</h2>
                        <button type="button" class="sala-hoja__cerrar" @click="hoja = false" aria-label="Cerrar">✕</button>
                    </div>
                    <div class="sala-hoja__resumen">Precio actual <strong x-text="actualTexto"></strong> · cierra en <span x-text="hh + ':' + mm + ':' + ss"></span></div>
                    @include('sala._formulario-puja', ['id' => 'hoja'])
                </div>
            </div>
        </template>

        <template x-if="modal">
            <div class="sala-modal" role="dialog" aria-modal="true" aria-labelledby="titulo-confirmacion">
                <div class="sala-modal__caja" x-init="$nextTick(() => $el.querySelector('.sala-modal__cancelar').focus())">
                    <div class="sala-modal__cabeza">
                        <h2 class="sala-modal__titulo" id="titulo-confirmacion">Confirma tu puja</h2>
                    </div>
                    <div class="sala-modal__cuerpo">
                        <div class="sala-modal__propiedad">Vas a ofertar por {{ $propiedad['direccion'] }}</div>
                        <div class="sala-modal__monto" x-text="modalMonto"></div>
                        <div class="sala-modal__detalle"><span x-text="modalUf"></span> · supera en <span x-text="modalDiferencia"></span> la puja actual</div>
                        <div class="sala-modal__aviso">La postura es irrevocable y compromete tu garantía de {{ $clp($propiedad['garantia']) }}. Si resultas adjudicatario, deberás suscribir la escritura y pagar el saldo en el plazo de las bases. El remate cierra automáticamente al vencer el tiempo.</div>
                        <div class="sala-modal__botones">
                            <button type="button" class="sala-modal__confirmar" :disabled="enviando" @click="confirmar()">Confirmar puja</button>
                            <button type="button" class="sala-modal__cancelar" @click="modal = false">Cancelar</button>
                        </div>
                    </div>
                </div>
            </div>
        </template>
    </div>
</x-layouts.base>
