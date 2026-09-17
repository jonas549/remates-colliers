@php
    use App\Support\Formato;
@endphp
<x-layouts.admin seccion="subastas" :titulo="'En vivo · ' . $remate->folio" clase-cuerpo="placeholder-claro">
    <div x-data="adminEnVivo(@js($config))" @keydown.escape.window="modal = false">
        <div class="admin-encabezado">
            <div class="admin-encabezado__texto">
                <div class="admin-gestion__migas"><a href="{{ route('admin.subastas') }}">Subastas</a> / @if (auth()->user()->esAdmin())<a href="{{ route('admin.remates.show', $remate) }}">{{ $remate->folio }}</a>@else{{ $remate->folio }}@endif / En vivo</div>
                <div class="admin-encabezado__kicker">PANEL DEL MARTILLERO · {{ $remate->folio }}</div>
                <h1 class="admin-encabezado__titulo admin-encabezado__titulo--con-bajada" x-text="info.direccion">{{ $remate->titulo }}</h1>
                <div class="admin-encabezado__bajada">
                    Martillero: {{ $remate->martillero?->name ?? 'sin asignar' }} · {{ $habilitados }} postores habilitados
                    · <span x-text="etiquetaLote"></span>
                </div>
            </div>
            <div class="admin-encabezado__acciones">
                <a href="{{ route('remates.show', $remate->slug) }}" class="admin-boton-borde" target="_blank" rel="noopener">Ver como espectador</a>
            </div>
        </div>

        <template x-if="aviso">
            <div class="admin-avisos"><div class="admin-aviso" :class="{ 'admin-aviso--error': avisoError }" x-text="aviso"></div></div>
        </template>

        <div class="admin-vivo">
            <div>
                <template x-if="estado.lotes.length > 1">
                    <div class="admin-vivo__lotes">
                        <template x-for="l in estado.lotes" :key="l.id">
                            <span class="badge-admin" :class="l.id === lote.id ? 'badge-admin--vivo' : (terminales.includes(l.estado) ? 'badge-admin--cerrado' : 'badge-admin--proximo')" x-text="'LOTE ' + l.orden + ' · ' + l.estado.toUpperCase()"></span>
                        </template>
                    </div>
                </template>

                <div class="admin-seccion__cabeza">
                    <h2 class="admin-seccion__titulo">Historial de pujas</h2>
                    <span class="admin-reportes__nota-titulo"><span x-text="lote.total_pujas"></span> posturas · tiempo real</span>
                </div>
                <div class="admin-vivo__historial">
                    <template x-for="p in historial" :key="p.en_ms + '-' + p.monto">
                        <div class="admin-fila">
                            <div>
                                <div class="admin-fila__principal" x-text="p.montoTexto"></div>
                                <div class="admin-fila__secundario" x-text="p.quien"></div>
                            </div>
                            <div class="admin-fila__secundario" x-text="p.hora"></div>
                        </div>
                    </template>
                    <template x-if="historial.length === 0">
                        <div class="admin-vacio">Sin posturas en este lote.</div>
                    </template>
                </div>
                <p class="admin-gestion__nota">Se muestran las últimas 10 posturas del lote. El sitio público identifica a cada postor solo con su número; aquí se ve quién es.</p>
            </div>

            <div>
                <div class="admin-vivo__panel">
                    <div class="admin-vivo__cabeza" :class="{ 'es-cerrado': !abierto }">
                        <span x-text="estadoTexto"></span>
                        <span>{{ $remate->folio }}</span>
                    </div>
                    <div class="admin-vivo__cuerpo">
                        <div class="admin-form__etiqueta">PRECIO ACTUAL</div>
                        <div class="admin-vivo__precio" x-text="precioTexto"></div>
                        <div class="admin-vivo__ganador" x-text="ganadorTexto"></div>
                        <div class="admin-form__etiqueta" x-text="antesDeAbrir ? 'ABRE EN' : 'CIERRA EN'"></div>
                        <div class="admin-vivo__contador">
                            <div class="admin-vivo__caja"><div class="admin-vivo__digito" x-text="hh"></div><div class="admin-vivo__caja-etiqueta">HORAS</div></div>
                            <div class="admin-vivo__caja"><div class="admin-vivo__digito" x-text="mm"></div><div class="admin-vivo__caja-etiqueta">MIN</div></div>
                            <div class="admin-vivo__caja"><div class="admin-vivo__digito" x-text="ss"></div><div class="admin-vivo__caja-etiqueta">SEG</div></div>
                        </div>
                        <div class="admin-gestion__nota">Precio base <span x-text="formato(lote.precio_base)"></span> · incremento <span x-text="formato(estado.incremento_minimo)"></span>. Sin extensiones.</div>

                        <div class="admin-vivo__acciones">
                            <button type="button" class="admin-modal__confirmar" :disabled="!abierto || enviando" @click="modal = true">Cerrar este lote ahora</button>
                        </div>
                    </div>
                </div>

                <form class="admin-form admin-form--plano" @submit.prevent="enviarMensaje()">
                    <div class="admin-form__interior">
                        <div class="admin-form__seccion admin-form__seccion--siguiente"><span class="admin-form__subtitulo">Mensaje a la sala</span></div>
                        <label class="admin-form__campo"><span class="admin-form__etiqueta">APARECE A LOS POSTORES EN LA SALA</span><textarea x-model="mensaje" maxlength="500" class="admin-form__textarea" placeholder="Ej.: Última llamada para el lote 1"></textarea></label>
                        <div class="admin-form__botones admin-form__botones--compactos">
                            <button type="submit" class="admin-form__publicar" :disabled="enviando">Publicar mensaje</button>
                            <button type="button" class="admin-form__borrador" :disabled="enviando" @click="mensaje = ''; enviarMensaje()">Quitar mensaje</button>
                        </div>
                        <div class="admin-gestion__nota" x-show="estado.mensaje_martillero" x-text="'Publicado: «' + (estado.mensaje_martillero?.texto || '') + '»'"></div>
                    </div>
                </form>
            </div>
        </div>

        <template x-if="modal">
            <div class="admin-modal" role="dialog" aria-modal="true" aria-labelledby="titulo-cierre-vivo">
                <form class="admin-modal__caja" @submit.prevent="cerrarLote()" x-init="$nextTick(() => $el.querySelector('.admin-modal__cancelar').focus())">
                    <div class="admin-modal__cabeza">
                        <div class="admin-modal__kicker">ACCIÓN IRREVERSIBLE</div>
                        <h2 class="admin-modal__titulo" id="titulo-cierre-vivo">Cerrar el lote anticipadamente</h2>
                    </div>
                    <div class="admin-modal__cuerpo">
                        <p class="admin-modal__texto">Vas a cerrar <strong x-text="'el lote ' + lote.orden"></strong> antes de que venza el tiempo. La puja más alta al momento del cierre queda como adjudicada y no se aceptan nuevas posturas.</p>
                        <label>
                            <span class="admin-modal__etiqueta">MOTIVO DEL CIERRE ANTICIPADO</span>
                            <textarea x-model="motivo" required maxlength="500" class="admin-modal__textarea" placeholder="Ej.: instrucción del mandante"></textarea>
                        </label>
                        <div class="admin-modal__botones">
                            <button type="submit" class="admin-modal__confirmar" :disabled="enviando">Cerrar lote</button>
                            <button type="button" class="admin-modal__cancelar" @click="modal = false">Cancelar</button>
                        </div>
                    </div>
                </form>
            </div>
        </template>
    </div>
</x-layouts.admin>
