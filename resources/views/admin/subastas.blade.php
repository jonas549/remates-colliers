@php
    $filtros = ['Todas', 'En vivo', 'Próximas', 'Borradores', 'Cerradas'];
    $esAdmin = auth()->user()?->esAdmin();
    $f = $formulario;
    $campo = fn (string $nombre) => $errors->has($nombre) ? 'es-invalido' : '';
@endphp
<x-layouts.admin seccion="subastas" titulo="Subastas" clase-cuerpo="placeholder-claro">
    <div x-data="adminSubastas(@js(['subastas' => $subastas, 'abierto' => $errors->any(), 'uf' => $f['uf'], 'porcentajeGlobal' => (float) $f['porcentaje'], 'incrementoGlobal' => $f['incremento'], 'base' => old('precio_base', ''), 'incremento' => old('incremento_minimo', ''), 'porcentaje' => old('porcentaje_garantia', '')]))" @keydown.escape.window="cerrar = null; menuAbierto = null">
        <div class="admin-encabezado">
            <div class="admin-encabezado__texto">
                <div class="admin-encabezado__kicker">SUBASTAS</div>
                <h1 class="admin-encabezado__titulo admin-encabezado__titulo--con-bajada">Publicación y cierre</h1>
                <div class="admin-encabezado__bajada">Cierre automático al vencer el tiempo, sin extensiones. Un remate que no se concreta no se reabre: se crea uno nuevo.</div>
            </div>
            @if ($esAdmin)
                <div class="admin-encabezado__acciones">
                    <button type="button" class="admin-boton-toggle" :class="{ 'es-abierto': form }" @click="form = !form" x-text="form ? 'Cerrar formulario' : 'Crear subasta'">Crear subasta</button>
                </div>
            @endif
        </div>

        <x-admin.avisos />

        @if ($esAdmin)
            <template x-if="form">
                <div class="admin-form">
                    <form class="admin-form__interior" method="POST" action="{{ route('admin.remates.store') }}">
                        @csrf
                        <div class="admin-form__cabeza">
                            <h2 class="admin-form__titulo">Crear subasta</h2>
                            <button type="button" class="admin-form__cerrar" @click="form = false">CERRAR ✕</button>
                        </div>

                        <div class="admin-form__seccion"><span class="admin-form__num">01</span><span class="admin-form__subtitulo">Propiedad</span></div>
                        <div class="admin-form__grilla">
                            <label class="admin-form__campo {{ $campo('direccion') }}"><span class="admin-form__etiqueta">DIRECCIÓN</span><input name="direccion" value="{{ old('direccion') }}" placeholder="Av. Providencia 2410, Depto. 802" class="admin-form__input" required></label>
                            <label class="admin-form__campo {{ $campo('comuna') }}"><span class="admin-form__etiqueta">COMUNA</span><input name="comuna" value="{{ old('comuna') }}" placeholder="Providencia" class="admin-form__input" required></label>
                            <label class="admin-form__campo {{ $campo('region') }}"><span class="admin-form__etiqueta">REGIÓN</span>
                                <select name="region" class="admin-form__input" required>
                                    @foreach ($f['regiones'] as $region)
                                        <option value="{{ $region }}" @selected(old('region', 'Región Metropolitana') === $region)>{{ $region }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label class="admin-form__campo {{ $campo('tipo_propiedad') }}"><span class="admin-form__etiqueta">TIPO</span>
                                <select name="tipo_propiedad" class="admin-form__input">
                                    @foreach ($f['tipos'] as $tipo)
                                        <option @selected(old('tipo_propiedad') === $tipo)>{{ $tipo }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label class="admin-form__campo {{ $campo('superficie_util') }}"><span class="admin-form__etiqueta">SUPERFICIE ÚTIL (M²)</span><input name="superficie_util" value="{{ old('superficie_util') }}" placeholder="82" inputmode="decimal" class="admin-form__input"></label>
                            <label class="admin-form__campo"><span class="admin-form__etiqueta">ROL SII</span><input name="atributos[rol_avaluo]" value="{{ old('atributos.rol_avaluo') }}" placeholder="1234-56" class="admin-form__input"></label>
                            <label class="admin-form__campo {{ $campo('ocupacion') }}"><span class="admin-form__etiqueta">OCUPACIÓN</span>
                                <select name="ocupacion" class="admin-form__input">
                                    @foreach ($f['ocupaciones'] as $ocupacion)
                                        <option @selected(old('ocupacion') === $ocupacion)>{{ $ocupacion }}</option>
                                    @endforeach
                                </select>
                            </label>
                        </div>

                        <div class="admin-form__seccion admin-form__seccion--siguiente"><span class="admin-form__num">02</span><span class="admin-form__subtitulo">Condiciones económicas (CLP)</span></div>
                        <div class="admin-form__grilla">
                            <label class="admin-form__campo {{ $campo('precio_base') }}"><span class="admin-form__etiqueta">PRECIO BASE</span><input name="precio_base" :value="base" @input="soloDigitos('base', $event.target.value); $event.target.value = base" inputmode="numeric" placeholder="120000000" class="admin-form__input" required></label>
                            <label class="admin-form__campo {{ $campo('incremento_minimo') }}"><span class="admin-form__etiqueta">INCREMENTO MÍNIMO</span><input name="incremento_minimo" :value="incremento" @input="soloDigitos('incremento', $event.target.value); $event.target.value = incremento" inputmode="numeric" placeholder="{{ $f['incremento'] }} (global)" class="admin-form__input"></label>
                            {{-- Acta: la garantía es un % del valor mínimo, configurable (10 % por defecto). El diseño pedía un monto. --}}
                            <label class="admin-form__campo {{ $campo('porcentaje_garantia') }}"><span class="admin-form__etiqueta">GARANTÍA (% DEL PRECIO BASE)</span><input name="porcentaje_garantia" :value="porcentaje" @input="porcentaje = $event.target.value.replace(/[^\d.,]/g, ''); $event.target.value = porcentaje" inputmode="decimal" placeholder="{{ rtrim(rtrim($f['porcentaje'], '0'), '.') }} (global)" class="admin-form__input"></label>
                        </div>
                        <div class="admin-form__resumen" x-text="resumenMontos"></div>

                        <div class="admin-form__seccion admin-form__seccion--siguiente"><span class="admin-form__num">03</span><span class="admin-form__subtitulo">Calendario y transmisión</span></div>
                        <div class="admin-form__grilla">
                            <label class="admin-form__campo {{ $campo('inicio_en') }}"><span class="admin-form__etiqueta">INICIO DEL REMATE</span><input type="datetime-local" name="inicio_en" value="{{ old('inicio_en') }}" class="admin-form__input"></label>
                            <label class="admin-form__campo {{ $campo('duracion_minutos') }}"><span class="admin-form__etiqueta">DURACIÓN</span>
                                <select name="duracion_minutos" class="admin-form__input">
                                    @foreach (collect([30, 45, 60, $f['duracion']])->unique()->sort() as $minutos)
                                        <option value="{{ $minutos }}" @selected((int) old('duracion_minutos', $f['duracion']) === $minutos)>{{ $minutos }} minutos</option>
                                    @endforeach
                                </select>
                            </label>
                            <label class="admin-form__campo {{ $campo('cierre_garantias_en') }}"><span class="admin-form__etiqueta">CIERRE DE GARANTÍAS</span><input type="datetime-local" name="cierre_garantias_en" value="{{ old('cierre_garantias_en') }}" class="admin-form__input"></label>
                            <label class="admin-form__campo {{ $campo('martillero_id') }}"><span class="admin-form__etiqueta">MARTILLERO</span>
                                <select name="martillero_id" class="admin-form__input">
                                    <option value="">Sin asignar</option>
                                    @foreach ($f['martilleros'] as $id => $nombre)
                                        <option value="{{ $id }}" @selected((string) old('martillero_id') === (string) $id)>{{ $nombre }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label class="admin-form__campo {{ $campo('youtube_video_id') }}"><span class="admin-form__etiqueta">ID DEL VIDEO DE YOUTUBE</span><input name="youtube_video_id" value="{{ old('youtube_video_id') }}" placeholder="jfKfPfyJRdk" class="admin-form__input"></label>
                            <label class="admin-form__campo"><span class="admin-form__etiqueta">MANDANTE</span><input name="atributos[mandante]" value="{{ old('atributos.mandante') }}" placeholder="Banco Consorcio" class="admin-form__input"></label>
                        </div>

                        <x-admin.errores />

                        <div class="admin-form__aviso">El cierre es automático al vencer el tiempo configurado, sin extensiones por posturas de último minuto. La garantía se recibe fuera de la plataforma y se aprueba postor por postor. Si no fijas el cierre de garantías, se usa el plazo global antes del inicio. Fotos, documentos, visitas y más lotes se agregan en la ficha del remate.</div>

                        <div class="admin-form__botones">
                            <button type="submit" name="accion" value="publicar" class="admin-form__publicar">Publicar subasta</button>
                            <button type="submit" name="accion" value="borrador" class="admin-form__borrador">Guardar como borrador</button>
                        </div>
                    </form>
                </div>
            </template>
        @endif

        <div class="admin-pestanas" role="tablist" aria-label="Filtrar subastas">
            @foreach ($filtros as $filtro)
                <button type="button" role="tab" class="admin-pestana" :class="{ 'es-activa': filtro === @js($filtro) }" :aria-selected="filtro === @js($filtro)" @click="filtro = @js($filtro)">
                    <div class="admin-pestana__etiqueta">{{ mb_strtoupper($filtro) }}</div>
                    <div class="admin-pestana__cuenta" x-text="cuenta(@js($filtro))"></div>
                </button>
            @endforeach
        </div>

        <div class="admin-subastas__tabla">
            <table class="admin-tabla admin-tabla--subastas">
                <thead>
                    <tr>
                        <th>REMATE</th>
                        <th>ESTADO</th>
                        <th>BASE</th>
                        <th>ACTUAL / FINAL</th>
                        <th>GARANTÍA</th>
                        <th>INSCRITOS</th>
                        <th>INICIO</th>
                        <th class="col-acciones">ACCIONES</th>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="s in filtradas" :key="s.id">
                        <tr>
                            <td class="es-primera" :class="{ 'regla-vivo': s.estado === 'En vivo', 'regla-proxima': s.estado === 'Próxima', 'regla-borrador': s.estado === 'Borrador', 'regla-adjudicada': s.estado === 'Adjudicada' }">
                                <div class="admin-tabla__principal" x-text="s.direccion"></div>
                                <div class="admin-tabla__secundario"><span x-text="s.folio"></span> · <span x-text="s.comuna"></span> · <span x-text="s.martillero"></span></div>
                                <div class="admin-acciones-menu">
                                    <button type="button" class="admin-acciones-menu__boton" @click="menuAbierto = s.id" aria-haspopup="dialog">Acciones ▾</button>
                                </div>
                            </td>
                            <td><span class="badge-admin" :class="'badge-admin--' + s.tono" x-text="s.estado.toUpperCase()"></span></td>
                            <td class="es-num" x-text="'$' + s.base.toLocaleString('es-CL')"></td>
                            <td class="es-fuerte" x-text="s.actual ? '$' + s.actual.toLocaleString('es-CL') : (s.estado === 'Cerrada' ? 'Sin postores' : '—')"></td>
                            <td class="es-num" x-text="'$' + s.garantia.toLocaleString('es-CL')"></td>
                            <td x-text="s.inscritos"></td>
                            <td x-text="s.inicio"></td>
                            <td class="col-acciones">
                                <div class="admin-acciones">
                                    @if ($esAdmin)
                                        <a :href="s.urls.ficha" class="admin-accion">Editar</a>
                                    @endif
                                    <template x-if="s.estado === 'En vivo'">
                                        <a :href="s.urls.enVivo" class="admin-accion">Panel en vivo</a>
                                    </template>
                                    @if ($esAdmin)
                                        <template x-if="s.estado === 'En vivo' || s.estado === 'Próxima'">
                                            <button type="button" class="admin-accion admin-accion--peligro" @click="cerrar = s.id">Cerrar ahora</button>
                                        </template>
                                        <template x-if="s.estado === 'Cerrada' || s.estado === 'Cancelada'">
                                            <form method="POST" :action="s.urls.republicar" class="formulario-en-linea">
                                                @csrf
                                                <button type="submit" class="admin-accion">Crear remate nuevo</button>
                                            </form>
                                        </template>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
            <template x-if="filtradas.length === 0">
                <div class="admin-postores__vacio">No hay subastas con ese filtro.</div>
            </template>
        </div>

        {{-- Hoja de acciones (tablet y móvil). Fuera de la tabla: las celdas fijas crean su propio apilamiento. --}}
        <template x-if="enMenu">
            <div>
                <div class="admin-acciones-menu__velo" @click="menuAbierto = null"></div>
                <div class="admin-acciones-menu__lista" role="dialog" aria-modal="true" :aria-label="'Acciones de ' + enMenu.direccion">
                    <div class="admin-acciones-menu__titulo" x-text="enMenu.direccion"></div>
                    @if ($esAdmin)
                        <a :href="enMenu.urls.ficha" class="admin-acciones-menu__enlace">Editar</a>
                    @endif
                    <template x-if="enMenu.estado === 'En vivo'">
                        <a :href="enMenu.urls.enVivo" class="admin-acciones-menu__enlace">Panel en vivo</a>
                    </template>
                    @if ($esAdmin)
                        <template x-if="enMenu.estado === 'En vivo' || enMenu.estado === 'Próxima'">
                            <button type="button" class="es-peligro" @click="cerrar = enMenu.id; menuAbierto = null">Cerrar ahora</button>
                        </template>
                        <template x-if="enMenu.estado === 'Cerrada' || enMenu.estado === 'Cancelada'">
                            <form method="POST" :action="enMenu.urls.republicar">
                                @csrf
                                <button type="submit">Crear remate nuevo</button>
                            </form>
                        </template>
                    @endif
                    <button type="button" class="es-cancelar" @click="menuAbierto = null">Cancelar</button>
                </div>
            </div>
        </template>

        <template x-if="enCierre">
            <div class="admin-modal" role="dialog" aria-modal="true" aria-labelledby="titulo-cierre">
                <form method="POST" :action="enCierre.urls.cerrar" class="admin-modal__caja" x-init="$nextTick(() => $el.querySelector('.admin-modal__cancelar').focus())">
                    @csrf
                    <div class="admin-modal__cabeza">
                        <div class="admin-modal__kicker">ACCIÓN IRREVERSIBLE</div>
                        <h2 class="admin-modal__titulo" id="titulo-cierre" x-text="enCierre.estado === 'En vivo' ? 'Cerrar el remate anticipadamente' : 'Cancelar el remate'">Cerrar el remate anticipadamente</h2>
                    </div>
                    <div class="admin-modal__cuerpo">
                        <template x-if="enCierre.estado === 'En vivo'">
                            <p class="admin-modal__texto">Vas a cerrar <strong x-text="enCierre.direccion"></strong> antes de que venza el tiempo. La puja más alta al momento del cierre queda como adjudicada y no se aceptan nuevas posturas.</p>
                        </template>
                        <template x-if="enCierre.estado !== 'En vivo'">
                            <p class="admin-modal__texto">Vas a cancelar <strong x-text="enCierre.direccion"></strong>. Todavía no comienza: queda cancelado, sale del sitio y no se aceptan más inscripciones. Para volver a rematarlo, crea un remate nuevo.</p>
                        </template>
                        <label>
                            <span class="admin-modal__etiqueta">MOTIVO DEL CIERRE ANTICIPADO</span>
                            <textarea name="motivo" required maxlength="500" class="admin-modal__textarea" placeholder="Ej.: instrucción del mandante, error en la publicación, retiro de la propiedad"></textarea>
                        </label>
                        <div class="admin-modal__botones">
                            <button type="submit" class="admin-modal__confirmar" x-text="enCierre.estado === 'En vivo' ? 'Cerrar remate' : 'Cancelar remate'">Cerrar remate</button>
                            <button type="button" class="admin-modal__cancelar" @click="cerrar = null">Cancelar</button>
                        </div>
                    </div>
                </form>
            </div>
        </template>
    </div>
</x-layouts.admin>
