@php
    use App\Demo\RematesDemo;

    $clp = fn ($n) => RematesDemo::clp($n);
    // Datos de la sala del prototipo (Puja en Vivo.dc.html). Historial: monto, postor, segundos atrás, es mía.
    $config = [
        'base' => 185000000,
        'paso' => 100000,
        'actual' => 198500000,
        'deltaCierre' => 7 * 60 + 12,
        'postor' => 21,
        'historial' => [
            [198500000, 7, 34, false], [195000000, 3, 96, false], [191500000, 21, 172, true],
            [188000000, 12, 240, false], [186500000, 3, 318, false], [185000000, 12, 402, false],
        ],
    ];
    $propiedad = [
        'folio' => 'R-2026-114',
        'direccion' => 'Av. Apoquindo 4501, Depto. 1802',
        'meta' => 'Las Condes, Región Metropolitana · Departamento · 118 m² útiles · 3D / 2B · Desocupada',
        'garantia' => 8000000,
        'martillero' => 'M. Ossandón',
        'video' => 'jfKfPfyJRdk',
    ];
@endphp
<x-layouts.base titulo="Sala de pujas" clase-cuerpo="">
    <div class="sala" x-data="salaPuja(@js($config))" @keydown.escape.window="modal = false; hoja = false">

        <div class="sala-cabecera">
            <div class="sala-cabecera__nav contenedor">
                <a href="{{ route('remates.index') }}"><img src="{{ asset('img/colliers-logo.png') }}" alt="Colliers" class="sala-cabecera__logo"></a>
                <div class="sala-cabecera__titulo">Sala de pujas · <span>{{ $propiedad['folio'] }}</span></div>
                <div class="sala-cabecera__derecha">
                    <span class="sala-cabecera__chip">GARANTÍA APROBADA</span>
                    <span class="sala-cabecera__usuario">María Paz González · Postor #21</span>
                </div>
            </div>
            <div class="sala-ticker" :class="{ 'es-cerrado': vencido }">
                <span class="sala-ticker__estado">
                    <span class="sala-ticker__punto"></span>
                    <span x-text="estadoTexto"></span>
                </span>
                <span class="sala-ticker__direccion">{{ $propiedad['direccion'] }}</span>
                <span class="sala-ticker__tiempo" x-text="tiempoTexto"></span>
            </div>
        </div>

        <div class="sala__contenido contenedor">
            <div class="sala__layout">

                <div class="sala__principal">
                    <div class="sala__video">
                        <iframe src="https://www.youtube-nocookie.com/embed/{{ $propiedad['video'] }}?rel=0" title="Transmisión en vivo del remate" allow="accelerometer; autoplay; clipboard-write; encrypted-media; picture-in-picture" allowfullscreen></iframe>
                    </div>
                    <div class="sala__video-pie">
                        <span>Transmisión del canal de Colliers Chile · Martillero <span>{{ $propiedad['martillero'] }}</span></span>
                        <span><span x-text="espectadores"></span> personas viendo</span>
                    </div>

                    <div class="sala__ficha">
                        <div class="sala__ficha-fila">
                            <div style="min-width: 0">
                                <h1 class="sala__titulo">{{ $propiedad['direccion'] }}</h1>
                                <div class="sala__meta">{{ $propiedad['meta'] }}</div>
                            </div>
                            <a href="{{ route('remates.show', 'apoquindo') }}" class="sala__antecedentes">Ver antecedentes</a>
                        </div>
                        <div class="sala__datos">
                            <div class="sala__dato"><div class="sala__dato-etiqueta">PRECIO BASE</div><div class="sala__dato-valor">{{ $clp($config['base']) }}</div></div>
                            <div class="sala__dato"><div class="sala__dato-etiqueta">INCREMENTO MÍNIMO</div><div class="sala__dato-valor">{{ $clp($config['paso']) }}</div></div>
                            <div class="sala__dato"><div class="sala__dato-etiqueta">TU GARANTÍA</div><div class="sala__dato-valor">{{ $clp($propiedad['garantia']) }}</div></div>
                            <div class="sala__dato"><div class="sala__dato-etiqueta">REFERENCIA UF</div><div class="sala__dato-valor" x-text="actualEnUf"></div></div>
                        </div>
                    </div>

                    <div class="sala__historial">
                        <div class="sala__historial-cabeza">
                            <h2 class="sala__historial-titulo">Historial de pujas</h2>
                            <span class="sala__historial-conteo"><span x-text="historialBruto.length"></span> POSTURAS · TIEMPO REAL</span>
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
                            <div class="sala-panel__etiqueta">PRECIO ACTUAL</div>
                            <template x-for="precio in [actualTexto]" :key="precio">
                                <div class="sala-panel__precio" x-text="precio"></div>
                            </template>
                            <div class="sala-panel__base">Base {{ $clp($config['base']) }} · <span x-text="sobreBase"></span> sobre el mínimo</div>

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

                            <div class="sala-panel__etiqueta sala-panel__etiqueta--cierra">CIERRA EN</div>
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
                            <button type="button" class="sala-modal__confirmar" @click="confirmar()">Confirmar puja</button>
                            <button type="button" class="sala-modal__cancelar" @click="modal = false">Cancelar</button>
                        </div>
                    </div>
                </div>
            </div>
        </template>
    </div>
</x-layouts.base>
