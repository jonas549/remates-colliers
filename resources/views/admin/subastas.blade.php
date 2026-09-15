@php
    $filtros = ['Todas', 'En vivo', 'Próximas', 'Borradores', 'Cerradas'];
@endphp
<x-layouts.admin seccion="subastas" titulo="Subastas" clase-cuerpo="placeholder-claro">
    <div x-data="adminSubastas(@js(['subastas' => $subastas]))" @keydown.escape.window="cerrar = null; menuAbierto = null">
        <div class="admin-encabezado">
            <div class="admin-encabezado__texto">
                <div class="admin-encabezado__kicker">SUBASTAS</div>
                <h1 class="admin-encabezado__titulo admin-encabezado__titulo--con-bajada">Publicación y cierre</h1>
                <div class="admin-encabezado__bajada">Cierre automático al vencer el tiempo, sin extensiones. Un remate que no se concreta no se reabre: se crea uno nuevo.</div>
            </div>
            <div class="admin-encabezado__acciones">
                <button type="button" class="admin-boton-toggle" :class="{ 'es-abierto': form }" @click="form = !form" x-text="form ? 'Cerrar formulario' : 'Crear subasta'">Crear subasta</button>
            </div>
        </div>

        <template x-if="form">
            <div class="admin-form">
                <form class="admin-form__interior" @submit.prevent="form = false">
                    <div class="admin-form__cabeza">
                        <h2 class="admin-form__titulo">Crear subasta</h2>
                        <button type="button" class="admin-form__cerrar" @click="form = false">CERRAR ✕</button>
                    </div>

                    <div class="admin-form__seccion"><span class="admin-form__num">01</span><span class="admin-form__subtitulo">Propiedad</span></div>
                    <div class="admin-form__grilla">
                        <label class="admin-form__campo"><span class="admin-form__etiqueta">DIRECCIÓN</span><input name="direccion" placeholder="Av. Providencia 2410, Depto. 802" class="admin-form__input"></label>
                        <label class="admin-form__campo"><span class="admin-form__etiqueta">COMUNA</span><input name="comuna" placeholder="Providencia" class="admin-form__input"></label>
                        <label class="admin-form__campo"><span class="admin-form__etiqueta">TIPO</span><select name="tipo" class="admin-form__input"><option>Departamento</option><option>Casa</option></select></label>
                        <label class="admin-form__campo"><span class="admin-form__etiqueta">SUPERFICIE ÚTIL (M²)</span><input name="superficie" placeholder="82" inputmode="numeric" class="admin-form__input"></label>
                        <label class="admin-form__campo"><span class="admin-form__etiqueta">ROL SII</span><input name="rol" placeholder="1234-56" class="admin-form__input"></label>
                        <label class="admin-form__campo"><span class="admin-form__etiqueta">OCUPACIÓN</span><select name="ocupacion" class="admin-form__input"><option>Desocupada</option><option>Ocupada</option></select></label>
                    </div>

                    <div class="admin-form__seccion admin-form__seccion--siguiente"><span class="admin-form__num">02</span><span class="admin-form__subtitulo">Condiciones económicas (CLP)</span></div>
                    <div class="admin-form__grilla">
                        <label class="admin-form__campo"><span class="admin-form__etiqueta">PRECIO BASE</span><input name="base" :value="base" @input="soloDigitos('base', $event.target.value); $event.target.value = base" inputmode="numeric" placeholder="120000000" class="admin-form__input"></label>
                        <label class="admin-form__campo"><span class="admin-form__etiqueta">INCREMENTO MÍNIMO</span><input name="incremento" :value="incremento" @input="soloDigitos('incremento', $event.target.value); $event.target.value = incremento" inputmode="numeric" placeholder="100000" class="admin-form__input"></label>
                        <label class="admin-form__campo"><span class="admin-form__etiqueta">GARANTÍA REQUERIDA</span><input name="garantia" :value="garantia" @input="soloDigitos('garantia', $event.target.value); $event.target.value = garantia" inputmode="numeric" placeholder="5000000" class="admin-form__input"></label>
                    </div>
                    <div class="admin-form__resumen" x-text="resumenMontos"></div>

                    <div class="admin-form__seccion admin-form__seccion--siguiente"><span class="admin-form__num">03</span><span class="admin-form__subtitulo">Calendario y transmisión</span></div>
                    <div class="admin-form__grilla">
                        <label class="admin-form__campo"><span class="admin-form__etiqueta">INICIO DEL REMATE</span><input type="datetime-local" name="inicio" class="admin-form__input"></label>
                        <label class="admin-form__campo"><span class="admin-form__etiqueta">DURACIÓN</span><select name="duracion" class="admin-form__input"><option>30 minutos</option><option>45 minutos</option><option>60 minutos</option></select></label>
                        <label class="admin-form__campo"><span class="admin-form__etiqueta">CIERRE DE GARANTÍAS</span><input type="datetime-local" name="cierre_garantias" class="admin-form__input"></label>
                        <label class="admin-form__campo"><span class="admin-form__etiqueta">MARTILLERO</span><select name="martillero" class="admin-form__input"><option>M. Ossandón</option><option>C. Vergara</option><option>R. Fuentes</option></select></label>
                        <label class="admin-form__campo"><span class="admin-form__etiqueta">ID DEL VIDEO DE YOUTUBE</span><input name="video" placeholder="jfKfPfyJRdk" class="admin-form__input"></label>
                        <label class="admin-form__campo"><span class="admin-form__etiqueta">MANDANTE</span><input name="mandante" placeholder="Banco Consorcio" class="admin-form__input"></label>
                    </div>

                    <div class="admin-form__aviso">El cierre es automático al vencer el tiempo configurado, sin extensiones por posturas de último minuto. La garantía se recibe fuera de la plataforma y se aprueba postor por postor.</div>

                    <div class="admin-form__botones">
                        <button type="submit" class="admin-form__publicar">Publicar subasta</button>
                        <button type="button" class="admin-form__borrador" @click="form = false">Guardar como borrador</button>
                    </div>
                </form>
            </div>
        </template>

        <div class="admin-pestanas" role="tablist" aria-label="Filtrar subastas">
            @foreach ($filtros as $f)
                <button type="button" role="tab" class="admin-pestana" :class="{ 'es-activa': filtro === @js($f) }" :aria-selected="filtro === @js($f)" @click="filtro = @js($f)">
                    <div class="admin-pestana__etiqueta">{{ mb_strtoupper($f) }}</div>
                    <div class="admin-pestana__cuenta" x-text="cuenta(@js($f))"></div>
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
                            <td><span class="badge-admin" :class="{ 'badge-admin--vivo': s.estado === 'En vivo', 'badge-admin--proximo': s.estado === 'Próxima', 'badge-admin--borrador': s.estado === 'Borrador', 'badge-admin--adjudicada': s.estado === 'Adjudicada', 'badge-admin--cerrado': s.estado === 'Cerrada' }" x-text="s.estado.toUpperCase()"></span></td>
                            <td class="es-num" x-text="'$' + s.base.toLocaleString('es-CL')"></td>
                            <td class="es-fuerte" x-text="s.actual ? '$' + s.actual.toLocaleString('es-CL') : (s.estado === 'Cerrada' ? 'Sin postores' : '—')"></td>
                            <td class="es-num" x-text="'$' + s.garantia.toLocaleString('es-CL')"></td>
                            <td x-text="s.inscritos"></td>
                            <td x-text="s.inicio"></td>
                            <td class="col-acciones">
                                <div class="admin-acciones">
                                    <button type="button" class="admin-accion">Editar</button>
                                    <template x-if="s.estado === 'En vivo' || s.estado === 'Próxima'">
                                        <button type="button" class="admin-accion admin-accion--peligro" @click="cerrar = s.id">Cerrar ahora</button>
                                    </template>
                                    <template x-if="s.estado === 'Cerrada'">
                                        <button type="button" class="admin-accion" @click="form = true">Crear remate nuevo</button>
                                    </template>
                                </div>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>


        {{-- Hoja de acciones (tablet y móvil). Fuera de la tabla: las celdas fijas crean su propio apilamiento. --}}
        <template x-if="enMenu">
            <div>
                <div class="admin-acciones-menu__velo" @click="menuAbierto = null"></div>
                <div class="admin-acciones-menu__lista" role="dialog" aria-modal="true" :aria-label="'Acciones de ' + enMenu.direccion">
                    <div class="admin-acciones-menu__titulo" x-text="enMenu.direccion"></div>
                    <button type="button" @click="menuAbierto = null">Editar</button>
                    <template x-if="enMenu.estado === 'En vivo' || enMenu.estado === 'Próxima'">
                        <button type="button" class="es-peligro" @click="cerrar = enMenu.id; menuAbierto = null">Cerrar ahora</button>
                    </template>
                    <template x-if="enMenu.estado === 'Cerrada'">
                        <button type="button" @click="form = true; menuAbierto = null">Crear remate nuevo</button>
                    </template>
                    <button type="button" class="es-cancelar" @click="menuAbierto = null">Cancelar</button>
                </div>
            </div>
        </template>

        <template x-if="enCierre">
            <div class="admin-modal" role="dialog" aria-modal="true" aria-labelledby="titulo-cierre">
                <div class="admin-modal__caja" x-init="$nextTick(() => $el.querySelector('.admin-modal__cancelar').focus())">
                    <div class="admin-modal__cabeza">
                        <div class="admin-modal__kicker">ACCIÓN IRREVERSIBLE</div>
                        <h2 class="admin-modal__titulo" id="titulo-cierre">Cerrar el remate anticipadamente</h2>
                    </div>
                    <div class="admin-modal__cuerpo">
                        <p class="admin-modal__texto">Vas a cerrar <strong x-text="enCierre.direccion"></strong> antes de que venza el tiempo. La puja más alta al momento del cierre queda como adjudicada y no se aceptan nuevas posturas.</p>
                        <label>
                            <span class="admin-modal__etiqueta">MOTIVO DEL CIERRE ANTICIPADO</span>
                            <textarea class="admin-modal__textarea" placeholder="Ej.: instrucción del mandante, error en la publicación, retiro de la propiedad"></textarea>
                        </label>
                        <div class="admin-modal__botones">
                            <button type="button" class="admin-modal__confirmar" @click="confirmarCierre()">Cerrar remate</button>
                            <button type="button" class="admin-modal__cancelar" @click="cerrar = null">Cancelar</button>
                        </div>
                    </div>
                </div>
            </div>
        </template>
    </div>
</x-layouts.admin>
