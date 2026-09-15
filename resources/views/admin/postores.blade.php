@php
    $filtros = ['Todos', 'Pendiente', 'En revisión', 'Aprobada', 'Rechazada'];
@endphp
<x-layouts.admin seccion="postores" titulo="Postores">
    <div x-data="adminPostores(@js(['postores' => $postores]))" @keydown.escape.window="ficha = null">
        <div class="admin-encabezado">
            <div class="admin-encabezado__texto--angosto">
                <div class="admin-encabezado__kicker">POSTORES</div>
                <h1 class="admin-encabezado__titulo admin-encabezado__titulo--con-bajada">Cuentas y garantías</h1>
                <div class="admin-encabezado__bajada">Aprobación manual. La recepción del vale a la vista o la transferencia ocurre fuera de la plataforma.</div>
            </div>
            <div class="admin-encabezado__acciones">
                <button type="button" class="admin-boton-borde">Exportar listado</button>
            </div>
        </div>

        <div class="admin-pestanas admin-pestanas--postores">
            <div class="admin-pestanas__tira" role="tablist" aria-label="Filtrar por estado de garantía">
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
                                        <button type="button" class="admin-aprobar" :class="{ 'es-inactivo': p.garantia === 'Aprobada' }" @click="marcar(p.id, 'Aprobada')">Aprobar</button>
                                        <button type="button" class="admin-rechazar" :class="{ 'es-inactivo': p.garantia === 'Rechazada' }" @click="marcar(p.id, 'Rechazada')">Rechazar</button>
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
                            <button type="button" class="admin-aprobar" :class="{ 'es-inactivo': p.garantia === 'Aprobada' }" @click="marcar(p.id, 'Aprobada')" x-text="'Aprobar garantía'"></button>
                            <button type="button" class="admin-rechazar" :class="{ 'es-inactivo': p.garantia === 'Rechazada' }" @click="marcar(p.id, 'Rechazada')">Rechazar</button>
                            <button type="button" class="admin-ver-ficha" @click="ficha = p.id">Ver ficha</button>
                        </div>
                    </div>
                </template>
            </div>

            <template x-if="filtrados.length === 0">
                <div class="admin-postores__vacio">No hay postores con ese filtro.</div>
            </template>

            <div class="admin-postores__nota">Al aprobar una garantía, el postor queda habilitado para pujar solo en el remate indicado y recibe un correo automático. Al rechazarla debes indicar el motivo; puede corregir y volver a enviar el comprobante hasta el cierre de garantías.</div>
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
                        <div class="admin-ficha__nota">Comprobante de garantía recibido por correo el 29-08-2026. Verificación manual contra cartola bancaria pendiente de confirmación por finanzas.</div>
                        <div class="admin-ficha__botones">
                            <button type="button" class="admin-ficha__aprobar" @click="marcar(seleccionado.id, 'Aprobada')">Aprobar garantía</button>
                            <button type="button" class="admin-ficha__rechazar" @click="marcar(seleccionado.id, 'Rechazada')">Rechazar</button>
                        </div>
                    </div>
                </div>
            </div>
        </template>
    </div>
</x-layouts.admin>
