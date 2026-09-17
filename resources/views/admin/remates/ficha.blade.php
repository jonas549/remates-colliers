@php
    use App\Models\Remate;
    use App\Remates\FormularioRemate;
    use App\Support\Formato;

    $f = $formulario;
    $campo = fn (string $nombre) => $errors->has($nombre) ? 'es-invalido' : '';
    $estadoLote = ['programado' => ['Programado', 'proximo'], 'abierto' => ['Abierto', 'vivo'], 'liquidando' => ['Liquidando', 'revision'], 'adjudicado' => ['Adjudicado', 'adjudicada'], 'desierto' => ['Desierto', 'cerrado'], 'cerrado' => ['Cerrado', 'cerrado'], 'incumplido' => ['Incumplido', 'rechazada']];
    $puedeCancelar = in_array($vista, [Remate::VISTA_BORRADOR, Remate::VISTA_PROXIMO], true);
    $puedeRepublicar = in_array($vista, [Remate::VISTA_CERRADO, Remate::VISTA_ADJUDICADO, Remate::VISTA_CANCELADO], true);
    $publico = in_array($vista, [Remate::VISTA_PROXIMO, Remate::VISTA_EN_VIVO, Remate::VISTA_ADJUDICADO, Remate::VISTA_CERRADO], true);
@endphp
<x-layouts.admin seccion="subastas" :titulo="$remate->folio" clase-cuerpo="placeholder-claro">
    <div x-data="{ cancelar: false }" @keydown.escape.window="cancelar = false">
        <div class="admin-encabezado">
            <div class="admin-encabezado__texto">
                <div class="admin-gestion__migas"><a href="{{ route('admin.subastas') }}">Subastas</a> / {{ $remate->folio }}</div>
                <div class="admin-encabezado__kicker">SUBASTA {{ $remate->folio }} · <span class="badge-admin badge-admin--{{ $fila['tono'] }}">{{ mb_strtoupper($fila['estado']) }}</span></div>
                <h1 class="admin-encabezado__titulo admin-encabezado__titulo--con-bajada">{{ $fila['direccion'] }}</h1>
                <div class="admin-encabezado__bajada">
                    {{ $remate->lotes->count() === 1 ? '1 lote' : $remate->lotes->count() . ' lotes' }} · base {{ Formato::clp($fila['base']) }} · garantía {{ Formato::clp($fila['garantia']) }}
                    · inicio {{ $fila['inicio'] }}@if ($remate->remateOrigen) · nuevo remate de <a href="{{ route('admin.remates.show', $remate->remateOrigen) }}">{{ $remate->remateOrigen->folio }}</a>@endif
                    @foreach ($remate->republicaciones as $nuevo) · republicado en <a href="{{ route('admin.remates.show', $nuevo) }}">{{ $nuevo->folio }}</a>@endforeach
                </div>
            </div>
            <div class="admin-encabezado__acciones">
                @if ($remate->estado === Remate::ESTADO_BORRADOR)
                    <form method="POST" action="{{ route('admin.remates.publicar', $remate) }}" class="formulario-en-linea">
                        @csrf
                        <button type="submit" class="admin-boton-toggle" @disabled($faltan !== [])>Publicar</button>
                    </form>
                @endif
                @if (in_array($vista, [Remate::VISTA_EN_VIVO, Remate::VISTA_PROXIMO, Remate::VISTA_ADJUDICADO, Remate::VISTA_CERRADO], true))
                    <a href="{{ route('admin.remates.en-vivo', $remate) }}" class="admin-boton-borde">Panel en vivo</a>
                @endif
                @if ($publico)
                    <a href="{{ route('remates.show', $remate->slug) }}" class="admin-boton-borde" target="_blank" rel="noopener">Ver en el sitio</a>
                @endif
                @if ($puedeRepublicar)
                    <form method="POST" action="{{ route('admin.remates.republicar', $remate) }}" class="formulario-en-linea">
                        @csrf
                        <button type="submit" class="admin-boton-borde">Crear remate nuevo</button>
                    </form>
                @endif
                @if ($puedeCancelar)
                    <button type="button" class="admin-accion admin-accion--peligro" @click="cancelar = true">Cancelar remate</button>
                @endif
            </div>
        </div>

        <x-admin.avisos />

        <div class="admin-gestion">
            @if ($faltan !== [])
                <div class="admin-faltan">
                    <strong>Para publicar falta:</strong>
                    <ul>
                        @foreach ($faltan as $falta)
                            <li>{{ $falta }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
            @if ($remate->estado === Remate::ESTADO_CANCELADO)
                <div class="admin-aviso admin-aviso--error">Cancelado el {{ Formato::fecha($remate->cancelado_en) }}. Motivo: {{ $remate->motivo_cancelacion }}</div>
            @endif

            {{-- 01 · Datos y condiciones --}}
            <div class="admin-gestion__bloque">
                <div class="admin-seccion__cabeza"><h2 class="admin-seccion__titulo">Datos y condiciones</h2></div>
                @unless ($editable)
                    <p class="admin-gestion__nota">El remate ya comenzó o terminó: el horario y las condiciones económicas quedan fijos. Se pueden cambiar el título, la descripción, el martillero y el video.</p>
                @endunless
                <form method="POST" action="{{ route('admin.remates.update', $remate) }}" class="admin-form admin-form--plano">
                    @csrf
                    @method('PUT')
                    <div class="admin-form__interior">
                        <div class="admin-form__grilla">
                            <label class="admin-form__campo admin-form__campo--ancho {{ $campo('titulo') }}"><span class="admin-form__etiqueta">TÍTULO</span><input name="titulo" value="{{ old('titulo', $remate->titulo) }}" class="admin-form__input" maxlength="200"></label>
                            <label class="admin-form__campo admin-form__campo--ancho {{ $campo('descripcion') }}"><span class="admin-form__etiqueta">DESCRIPCIÓN DEL REMATE (OPCIONAL)</span><textarea name="descripcion" class="admin-form__textarea" maxlength="5000">{{ old('descripcion', $remate->descripcion) }}</textarea></label>
                            <label class="admin-form__campo {{ $campo('martillero_id') }}"><span class="admin-form__etiqueta">MARTILLERO</span>
                                <select name="martillero_id" class="admin-form__input">
                                    <option value="">Sin asignar</option>
                                    @foreach ($f['martilleros'] as $id => $nombre)
                                        <option value="{{ $id }}" @selected((string) old('martillero_id', $remate->martillero_id) === (string) $id)>{{ $nombre }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label class="admin-form__campo {{ $campo('youtube_video_id') }}"><span class="admin-form__etiqueta">ID DEL VIDEO DE YOUTUBE</span><input name="youtube_video_id" value="{{ old('youtube_video_id', $remate->youtube_video_id) }}" placeholder="jfKfPfyJRdk" class="admin-form__input"></label>
                            <label class="admin-form__campo {{ $campo('inicio_en') }}"><span class="admin-form__etiqueta">INICIO DEL REMATE (HORA DE SANTIAGO)</span><input type="datetime-local" name="inicio_en" value="{{ old('inicio_en', FormularioRemate::local($remate->inicio_en)) }}" class="admin-form__input" @disabled(! $editable)></label>
                            <label class="admin-form__campo {{ $campo('cierre_garantias_en') }}"><span class="admin-form__etiqueta">CIERRE DE GARANTÍAS</span><input type="datetime-local" name="cierre_garantias_en" value="{{ old('cierre_garantias_en', FormularioRemate::local($remate->cierre_garantias_en)) }}" class="admin-form__input" @disabled(! $editable)><span class="admin-form__ayuda">Vacío: {{ \App\Models\Configuracion::valor('garantias_cierre_horas_antes') }} h antes del inicio al publicar.</span></label>
                            <label class="admin-form__campo {{ $campo('duracion_minutos') }}"><span class="admin-form__etiqueta">DURACIÓN DE CADA LOTE (MIN)</span><input name="duracion_minutos" value="{{ old('duracion_minutos', $remate->duracion_lote_segundos ? intdiv($remate->duracion_lote_segundos, 60) : '') }}" placeholder="{{ $f['duracion'] }} (global)" inputmode="numeric" class="admin-form__input" @disabled(! $editable)></label>
                            <label class="admin-form__campo {{ $campo('pausa_minutos') }}"><span class="admin-form__etiqueta">PAUSA ENTRE LOTES (MIN)</span><input name="pausa_minutos" value="{{ old('pausa_minutos', intdiv($remate->pausa_entre_lotes_segundos, 60)) }}" inputmode="numeric" class="admin-form__input" @disabled(! $editable)></label>
                            <label class="admin-form__campo {{ $campo('incremento_minimo') }}"><span class="admin-form__etiqueta">INCREMENTO MÍNIMO (CLP)</span><input name="incremento_minimo" value="{{ old('incremento_minimo', $remate->incremento_minimo) }}" placeholder="{{ $f['incremento'] }} (global)" inputmode="numeric" class="admin-form__input" @disabled(! $editable)></label>
                            <label class="admin-form__campo {{ $campo('porcentaje_garantia') }}"><span class="admin-form__etiqueta">GARANTÍA (% DEL PRECIO BASE)</span><input name="porcentaje_garantia" value="{{ old('porcentaje_garantia', $remate->porcentaje_garantia !== null ? rtrim(rtrim($remate->porcentaje_garantia, '0'), '.') : '') }}" placeholder="{{ rtrim(rtrim($f['porcentaje'], '0'), '.') }} (global)" inputmode="decimal" class="admin-form__input" @disabled(! $editable)></label>
                        </div>
                        <x-admin.errores />
                        <div class="admin-form__botones admin-form__botones--compactos">
                            <button type="submit" class="admin-form__publicar">Guardar datos</button>
                        </div>
                    </div>
                </form>
            </div>

            {{-- 02 · Lotes --}}
            <div class="admin-gestion__bloque">
                <div class="admin-seccion__cabeza">
                    <h2 class="admin-seccion__titulo">Lotes</h2>
                    @if ($editable)
                        <a href="{{ route('admin.lotes.create', $remate) }}" class="admin-seccion__enlace">Agregar lote →</a>
                    @endif
                </div>
                <div class="admin-tabla-scroll">
                    <table class="admin-tabla">
                        <thead>
                            <tr><th>LOTE</th><th>ESTADO</th><th>PRECIO BASE</th><th>ACTUAL / FINAL</th><th>ABRE</th><th>CIERRA</th><th>FOTOS</th><th></th></tr>
                        </thead>
                        <tbody>
                            @forelse ($remate->lotes as $lote)
                                <tr>
                                    <td>
                                        <div class="admin-tabla__principal">{{ $lote->orden }}. {{ $lote->direccion ?: $lote->titulo }}</div>
                                        <div class="admin-tabla__secundario">{{ collect([$lote->tipo_propiedad, $lote->comuna])->filter()->join(' · ') }}</div>
                                    </td>
                                    <td><span class="badge-admin badge-admin--{{ $estadoLote[$lote->estado][1] ?? 'cerrado' }}">{{ mb_strtoupper($estadoLote[$lote->estado][0] ?? $lote->estado) }}</span></td>
                                    <td class="es-num">{{ Formato::clp($lote->precio_base) }}</td>
                                    <td class="es-fuerte">{{ $lote->precio_actual ? Formato::clp($lote->precio_actual) : '—' }}</td>
                                    <td>{{ Formato::fechaCorta($lote->abre_en) }}</td>
                                    <td>{{ Formato::fechaCorta($lote->cierra_en) }}@if ($lote->nota_cierre)<div class="admin-tabla__secundario">Cierre anticipado: {{ $lote->nota_cierre }}</div>@endif</td>
                                    <td class="es-num">{{ $lote->imagenes->count() }}</td>
                                    <td class="admin-gestion__acciones-lote">
                                        @if ($editable && $remate->lotes->count() > 1)
                                            @unless ($loop->first)
                                                <form method="POST" action="{{ route('admin.lotes.mover', [$remate, $lote]) }}" class="formulario-en-linea">
                                                    @csrf
                                                    <input type="hidden" name="direccion" value="subir">
                                                    <button type="submit" class="admin-accion" aria-label="Subir lote {{ $lote->orden }}">↑ Subir</button>
                                                </form>
                                            @endunless
                                            @unless ($loop->last)
                                                <form method="POST" action="{{ route('admin.lotes.mover', [$remate, $lote]) }}" class="formulario-en-linea">
                                                    @csrf
                                                    <input type="hidden" name="direccion" value="bajar">
                                                    <button type="submit" class="admin-accion" aria-label="Bajar lote {{ $lote->orden }}">↓ Bajar</button>
                                                </form>
                                            @endunless
                                        @endif
                                        <a href="{{ route('admin.lotes.edit', [$remate, $lote]) }}" class="admin-accion">Editar</a>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="8">Sin lotes todavía.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <p class="admin-gestion__nota">Horario fijo: cada lote abre al cerrar el anterior más la pausa. El orden, el precio base y la duración se editan solo antes de que abra el primer lote.</p>
            </div>

            {{-- 03 · Documentos --}}
            <div class="admin-gestion__bloque">
                <div class="admin-seccion__cabeza"><h2 class="admin-seccion__titulo">Documentos descargables</h2></div>
                @forelse ($remate->documentos as $documento)
                    <div class="admin-fila">
                        <div>
                            <div class="admin-fila__principal">{{ $documento->titulo }}</div>
                            <div class="admin-fila__secundario">
                                {{ $documento->lote ? 'Lote ' . $documento->lote->orden : 'Todo el remate' }} ·
                                {{ $documento->publico ? 'Público' : 'Solo postores con garantía aprobada' }} ·
                                {{ strtoupper(pathinfo($documento->nombre_original, PATHINFO_EXTENSION)) }} · {{ Formato::numero(($documento->tamano_bytes ?? 0) / 1024) }} KB
                            </div>
                        </div>
                        <div class="admin-acciones">
                            <a href="{{ route('admin.documentos.descargar', [$remate, $documento]) }}" class="admin-accion">Descargar</a>
                            <form method="POST" action="{{ route('admin.documentos.destroy', [$remate, $documento]) }}" class="formulario-en-linea" onsubmit="return confirm('¿Eliminar este documento?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="admin-accion admin-accion--peligro">Eliminar</button>
                            </form>
                        </div>
                    </div>
                @empty
                    <div class="admin-vacio">Sin documentos. Las bases del remate, el procedimiento y los certificados se suben aquí.</div>
                @endforelse
                <form method="POST" action="{{ route('admin.documentos.store', $remate) }}" enctype="multipart/form-data" class="admin-form admin-form--plano">
                    @csrf
                    <div class="admin-form__interior">
                        <div class="admin-form__grilla">
                            <label class="admin-form__campo {{ $campo('titulo') }}"><span class="admin-form__etiqueta">TÍTULO DEL DOCUMENTO</span><input name="titulo" placeholder="Bases especiales del remate" class="admin-form__input" maxlength="200" required></label>
                            <label class="admin-form__campo"><span class="admin-form__etiqueta">CORRESPONDE A</span>
                                <select name="lote_id" class="admin-form__input">
                                    <option value="">Todo el remate</option>
                                    @foreach ($remate->lotes as $lote)
                                        <option value="{{ $lote->id }}">Lote {{ $lote->orden }} · {{ $lote->direccion }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label class="admin-form__campo {{ $campo('archivo') }}"><span class="admin-form__etiqueta">ARCHIVO (PDF, IMAGEN, WORD O EXCEL · HASTA 20 MB)</span><input type="file" name="archivo" required accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx"></label>
                            <label class="admin-form__campo admin-form__check"><input type="checkbox" name="publico" value="1" checked><span>Público (si no, solo postores con garantía aprobada)</span></label>
                        </div>
                        <div class="admin-form__botones admin-form__botones--compactos">
                            <button type="submit" class="admin-form__borrador">Subir documento</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <template x-if="cancelar">
            <div class="admin-modal" role="dialog" aria-modal="true" aria-labelledby="titulo-cancelar">
                <form method="POST" action="{{ route('admin.remates.cancelar', $remate) }}" class="admin-modal__caja" x-init="$nextTick(() => $el.querySelector('.admin-modal__cancelar').focus())">
                    @csrf
                    <div class="admin-modal__cabeza">
                        <div class="admin-modal__kicker">ACCIÓN IRREVERSIBLE</div>
                        <h2 class="admin-modal__titulo" id="titulo-cancelar">Cancelar el remate</h2>
                    </div>
                    <div class="admin-modal__cuerpo">
                        <p class="admin-modal__texto">Vas a cancelar <strong>{{ $fila['direccion'] }}</strong>. Sale del sitio y no acepta más inscripciones. Un remate cancelado no se reabre: para rematar de nuevo, crea un remate nuevo.</p>
                        <label>
                            <span class="admin-modal__etiqueta">MOTIVO</span>
                            <textarea name="motivo" required maxlength="500" class="admin-modal__textarea" placeholder="Ej.: instrucción del mandante, retiro de la propiedad"></textarea>
                        </label>
                        <div class="admin-modal__botones">
                            <button type="submit" class="admin-modal__confirmar">Cancelar remate</button>
                            <button type="button" class="admin-modal__cancelar" @click="cancelar = false">Volver</button>
                        </div>
                    </div>
                </form>
            </div>
        </template>
    </div>
</x-layouts.admin>
