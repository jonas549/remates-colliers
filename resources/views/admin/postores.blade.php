@php
    // Pestañas del diseño (estado de la garantía) + «Cuentas por aprobar», que el diseño no tenía y la cola de revisión necesita.
    $filtros = ['Todos', 'Pendiente', 'En revisión', 'Aprobada', 'Rechazada', 'Cuentas por aprobar'];
@endphp
<x-layouts.admin seccion="postores" titulo="Postores">
    <div x-data="adminPostores(@js(['postores' => $postores]))" @keydown.escape.window="ficha = null; motivo = null">
        <div class="admin-encabezado">
            <div class="admin-encabezado__texto--angosto">
                <div class="admin-encabezado__kicker">POSTORES</div>
                <h1 class="admin-encabezado__titulo admin-encabezado__titulo--con-bajada">Cuentas y garantías</h1>
                <div class="admin-encabezado__bajada">Aprobación manual. La recepción del vale a la vista o la transferencia ocurre fuera de la plataforma.</div>
            </div>
            <div class="admin-encabezado__acciones">
                <a href="{{ route('admin.postores.exportar') }}" class="admin-boton-borde">Exportar listado</a>
            </div>
        </div>

        <template x-if="aviso">
            <div class="admin-avisos" role="status" aria-live="polite"><div class="admin-aviso" :class="{ 'admin-aviso--error': avisoError }" x-text="aviso"></div></div>
        </template>

        <div class="admin-pestanas admin-pestanas--postores">
            <div class="admin-pestanas__tira" role="tablist" aria-label="Filtrar por estado">
                @foreach ($filtros as $f)
                    <button type="button" role="tab" class="admin-pestana" :class="{ 'es-activa': filtro === @js($f) }" :aria-selected="filtro === @js($f)" @click="filtro = @js($f)">
                        <div class="admin-pestana__etiqueta">{{ mb_strtoupper($f) }}</div>
                        <div class="admin-pestana__cuenta" x-text="cuenta(@js($f))"></div>
                    </button>
                @endforeach
            </div>
            <label class="admin-buscador">
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="#a3abb8" stroke-width="1.5"><circle cx="11" cy="11" r="7"></circle><path d="m20 20-3.5-3.5"></path></svg>
                <input x-model="q" placeholder="Buscar por nombre, RUT o remate" aria-label="Buscar postores">
            </label>
        </div>

        <div class="admin-postores">
            <div class="admin-postores__resumen">
                <div class="admin-postores__conteo" x-text="conteo"></div>
                <div class="admin-postores__total">Garantías aprobadas: <strong x-text="totalGarantias"></strong></div>
            </div>

            {{-- Escritorio y tablet: tabla --}}
            <div class="admin-tabla-scroll admin-postores__tabla">
                <table class="admin-tabla admin-tabla--postores">
                    <thead>
                        <tr>
                            <th>POSTOR</th>
                            <th>CONTACTO</th>
                            <th>REMATE</th>
                            <th>GARANTÍA</th>
                            <th>CUENTA</th>
                            <th>ESTADO GARANTÍA</th>
                            <th>ACCIONES</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="p in filtrados" :key="p.id">
                            <tr>
                                <td class="es-primera" :class="regla(p)">
                                    <div class="admin-tabla__principal" x-text="p.nombre"></div>
                                    <div class="admin-tabla__secundario"><span x-text="p.rut"></span> · <span x-text="p.tipo"></span></div>
                                </td>
                                <td>
                                    <div x-text="p.correo"></div>
                                    <div class="admin-tabla__secundario" x-text="p.telefono"></div>
                                </td>
                                <td>
                                    <div x-text="p.remate"></div>
                                    <div class="admin-tabla__secundario" x-text="p.folio"></div>
                                </td>
                                <td>
                                    <div class="admin-postor__monto" x-text="monto(p)"></div>
                                    <div class="admin-tabla__secundario" x-text="p.medio"></div>
                                </td>
                                <td><span class="badge-admin" :class="'badge-admin--' + tono(p.cuenta)" x-text="p.cuenta.toUpperCase()"></span></td>
                                <td>
                                    <span class="badge-admin" :class="'badge-admin--' + tono(p.garantia)" x-text="p.garantia.toUpperCase()"></span>
                                    <div class="admin-postor__actualizado" x-text="p.actualizado"></div>
                                </td>
                                <td>
                                    <div class="admin-acciones">
                                        <button type="button" class="admin-aprobar" :class="{ 'es-inactivo': !puedeAprobar(p) }" :disabled="!puedeAprobar(p) || enviando" @click="aprobar(p)" x-text="textoAprobar(p, false)">Aprobar</button>
                                        <button type="button" class="admin-rechazar" :class="{ 'es-inactivo': !puedeRechazar(p) }" :disabled="!puedeRechazar(p) || enviando" @click="pedirMotivo(p, sobreCuenta(p) ? 'rechazarCuenta' : 'rechazarGarantia')">Rechazar</button>
                                        <button type="button" class="admin-ver-ficha" @click="ficha = p.id">Ficha</button>
                                    </div>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>

            {{-- Móvil: tarjetas --}}
            <div class="admin-tarjetas">
                <template x-for="p in filtrados" :key="'t' + p.id">
                    <div class="admin-tarjeta" :class="regla(p)">
                        <div class="admin-tarjeta__nombre" x-text="p.nombre"></div>
                        <div class="admin-tarjeta__sub" x-text="p.rut + ' · ' + p.tipo"></div>
                        <div class="admin-tarjeta__fila">
                            <div class="admin-tarjeta__remate">
                                <div x-text="p.remate"></div>
                                <div class="admin-tarjeta__sub" x-text="p.folio + ' · ' + p.medio"></div>
                            </div>
                            <div class="admin-postor__monto" x-text="monto(p)"></div>
                        </div>
                        <div class="admin-tarjeta__badges">
                            <span class="badge-admin" :class="'badge-admin--' + tono(p.cuenta)" x-text="'CUENTA ' + p.cuenta.toUpperCase()"></span>
                            <span class="badge-admin" :class="'badge-admin--' + tono(p.garantia)" x-text="'GARANTÍA ' + p.garantia.toUpperCase()"></span>
                        </div>
                        <div class="admin-tarjeta__actualizado" x-text="p.actualizado"></div>
                        <div class="admin-tarjeta__botones">
                            <button type="button" class="admin-aprobar" :class="{ 'es-inactivo': !puedeAprobar(p) }" :disabled="!puedeAprobar(p) || enviando" @click="aprobar(p)" x-text="textoAprobar(p, true)"></button>
                            <button type="button" class="admin-rechazar" :class="{ 'es-inactivo': !puedeRechazar(p) }" :disabled="!puedeRechazar(p) || enviando" @click="pedirMotivo(p, sobreCuenta(p) ? 'rechazarCuenta' : 'rechazarGarantia')">Rechazar</button>
                            <button type="button" class="admin-ver-ficha" @click="ficha = p.id">Ver ficha</button>
                        </div>
                    </div>
                </template>
            </div>

            <template x-if="filtrados.length === 0">
                <div class="admin-postores__vacio">No hay postores con ese filtro.</div>
            </template>

            <div class="admin-postores__nota">Mientras la cuenta no esté aprobada, «Aprobar» y «Rechazar» actúan sobre la cuenta; después, sobre la garantía del remate indicado. Al aprobar una garantía, el postor queda habilitado para pujar solo en ese remate y recibe un correo automático. Al rechazar debes indicar el motivo; puede corregir y volver a enviar el comprobante hasta el cierre de garantías.</div>
        </div>

        <template x-if="seleccionado">
            <div class="admin-ficha" role="dialog" aria-modal="true" aria-labelledby="ficha-nombre" @click.self="ficha = null">
                <div class="admin-ficha__caja">
                    <div class="admin-ficha__cabeza">
                        <div>
                            <div class="admin-ficha__kicker">FICHA DE POSTOR</div>
                            <h2 class="admin-ficha__nombre" id="ficha-nombre" x-text="seleccionado.nombre"></h2>
                        </div>
                        <button type="button" class="admin-ficha__cerrar" @click="ficha = null" aria-label="Cerrar ficha">✕</button>
                    </div>
                    <div class="admin-ficha__cuerpo">
                        <template x-for="[k, v] in datosFicha(seleccionado)" :key="k">
                            <div class="admin-ficha__dato"><span class="admin-ficha__k" x-text="k"></span><span class="admin-ficha__v" x-text="v"></span></div>
                        </template>
                        <template x-if="seleccionado.documentos.length || seleccionado.comprobante">
                            <div class="admin-ficha__nota">
                                <strong>Documentos:</strong>
                                <template x-for="d in seleccionado.documentos" :key="d.url"><a :href="d.url" class="admin-ficha__documento" x-text="d.nombre"></a></template>
                                <template x-if="seleccionado.comprobante"><a :href="seleccionado.comprobante" class="admin-ficha__documento">Comprobante de garantía</a></template>
                            </div>
                        </template>
                        <template x-if="!seleccionado.documentos.length && !seleccionado.comprobante">
                            <div class="admin-ficha__nota">Sin documentos cargados. La verificación del vale a la vista o la transferencia se hace contra la cartola, fuera de la plataforma.</div>
                        </template>
                        <div class="admin-ficha__botones">
                            <button type="button" class="admin-ficha__aprobar" :disabled="!puedeAprobar(seleccionado) || enviando" @click="aprobar(seleccionado)" x-text="textoAprobar(seleccionado, true)"></button>
                            <button type="button" class="admin-ficha__rechazar" :disabled="!puedeRechazar(seleccionado) || enviando" @click="pedirMotivo(seleccionado, sobreCuenta(seleccionado) ? 'rechazarCuenta' : 'rechazarGarantia')">Rechazar</button>
                        </div>
                        <div class="admin-ficha__botones">
                            <template x-if="seleccionado.cuentaEstado === 'aprobado'">
                                <button type="button" class="admin-boton-borde admin-ficha__secundario" :disabled="enviando" @click="pedirMotivo(seleccionado, 'bloquear')">Bloquear cuenta</button>
                            </template>
                            <template x-if="seleccionado.cuentaEstado === 'bloqueado'">
                                <button type="button" class="admin-boton-borde admin-ficha__secundario" :disabled="enviando" @click="accion(seleccionado.urls.desbloquear, {})">Desbloquear cuenta</button>
                            </template>
                        </div>
                    </div>
                </div>
            </div>
        </template>

        <template x-if="motivo">
            <div class="admin-modal" role="dialog" aria-modal="true" aria-labelledby="titulo-motivo">
                <form class="admin-modal__caja" @submit.prevent="confirmarMotivo()" x-init="$nextTick(() => $el.querySelector('textarea').focus())">
                    <div class="admin-modal__cabeza">
                        <div class="admin-modal__kicker" x-text="motivo.p.nombre.toUpperCase()"></div>
                        <h2 class="admin-modal__titulo" id="titulo-motivo" x-text="{ rechazarCuenta: 'Rechazar la cuenta', rechazarGarantia: 'Rechazar la garantía', bloquear: 'Bloquear la cuenta' }[motivo.tipo]"></h2>
                    </div>
                    <div class="admin-modal__cuerpo">
                        <p class="admin-modal__texto" x-text="motivo.tipo === 'bloquear' ? 'La cuenta no podrá pujar ni inscribirse hasta que la desbloquees.' : 'El postor recibe el motivo por correo.'"></p>
                        <label>
                            <span class="admin-modal__etiqueta">MOTIVO</span>
                            <textarea x-model="motivo.texto" required maxlength="1000" class="admin-modal__textarea" placeholder="Ej.: el comprobante no corresponde al monto o al titular"></textarea>
                        </label>
                        <div class="admin-modal__botones">
                            <button type="submit" class="admin-modal__confirmar" :disabled="enviando">Confirmar</button>
                            <button type="button" class="admin-modal__cancelar" @click="motivo = null">Cancelar</button>
                        </div>
                    </div>
                </form>
            </div>
        </template>
    </div>
</x-layouts.admin>
