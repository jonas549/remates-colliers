@php
    use App\Models\Lote;
    use App\Remates\FormularioRemate;
    use App\Support\Formato;

    $nuevo = ! $lote->exists;
    $campo = fn (string $nombre) => $errors->has($nombre) ? 'es-invalido' : '';
    $valor = fn (string $nombre) => old($nombre, $lote->{$nombre});
    // Sin separador de miles: el valor vuelve tal cual al guardar.
    $numero = fn ($n) => $n === null ? '' : rtrim(rtrim((string) $n, '0'), '.');
@endphp
<x-layouts.admin seccion="subastas" :titulo="$nuevo ? 'Nuevo lote' : 'Lote ' . $lote->orden" clase-cuerpo="placeholder-claro">
    <div class="admin-encabezado">
        <div class="admin-encabezado__texto">
            <div class="admin-gestion__migas"><a href="{{ route('admin.subastas') }}">Subastas</a> / <a href="{{ route('admin.remates.show', $remate) }}">{{ $remate->folio }}</a> / {{ $nuevo ? 'Nuevo lote' : 'Lote ' . $lote->orden }}</div>
            <div class="admin-encabezado__kicker">{{ $nuevo ? 'NUEVO LOTE' : 'LOTE ' . $lote->orden }}</div>
            <h1 class="admin-encabezado__titulo">{{ $nuevo ? 'Datos del activo' : ($lote->direccion ?: $lote->titulo) }}</h1>
        </div>
        <div class="admin-encabezado__acciones">
            <a href="{{ route('admin.remates.show', $remate) }}" class="admin-boton-borde">Volver al remate</a>
        </div>
    </div>

    <x-admin.avisos />

    <div class="admin-gestion">
        <form method="POST" action="{{ $nuevo ? route('admin.lotes.store', $remate) : route('admin.lotes.update', [$remate, $lote]) }}" class="admin-form admin-form--plano">
            @csrf
            @unless ($nuevo)
                @method('PUT')
            @endunless
            <div class="admin-form__interior">
                <div class="admin-form__seccion"><span class="admin-form__num">01</span><span class="admin-form__subtitulo">Propiedad</span></div>
                <div class="admin-form__grilla">
                    <label class="admin-form__campo admin-form__campo--ancho {{ $campo('direccion') }}"><span class="admin-form__etiqueta">DIRECCIÓN</span><input name="direccion" value="{{ $valor('direccion') }}" placeholder="Av. Providencia 2410, Depto. 802" class="admin-form__input" required maxlength="255"></label>
                    <label class="admin-form__campo {{ $campo('comuna') }}"><span class="admin-form__etiqueta">COMUNA</span><input name="comuna" value="{{ $valor('comuna') }}" class="admin-form__input" required maxlength="80"></label>
                    <label class="admin-form__campo {{ $campo('region') }}"><span class="admin-form__etiqueta">REGIÓN</span>
                        <select name="region" class="admin-form__input" required>
                            @foreach ($regiones as $region)
                                <option value="{{ $region }}" @selected(($valor('region') ?? 'Región Metropolitana') === $region)>{{ $region }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="admin-form__campo {{ $campo('tipo_propiedad') }}"><span class="admin-form__etiqueta">TIPO</span>
                        <select name="tipo_propiedad" class="admin-form__input" required>
                            @foreach ($tipos as $tipo)
                                <option @selected($valor('tipo_propiedad') === $tipo)>{{ $tipo }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="admin-form__campo {{ $campo('ocupacion') }}"><span class="admin-form__etiqueta">OCUPACIÓN</span>
                        <select name="ocupacion" class="admin-form__input">
                            @foreach ($ocupaciones as $ocupacion)
                                <option @selected($valor('ocupacion') === $ocupacion)>{{ $ocupacion }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="admin-form__campo {{ $campo('superficie_util') }}"><span class="admin-form__etiqueta">SUPERFICIE ÚTIL (M²)</span><input name="superficie_util" value="{{ old('superficie_util', $numero($lote->superficie_util)) }}" inputmode="decimal" class="admin-form__input"></label>
                    <label class="admin-form__campo {{ $campo('superficie_terraza') }}"><span class="admin-form__etiqueta">TERRAZA (M²)</span><input name="superficie_terraza" value="{{ old('superficie_terraza', $numero($lote->superficie_terraza)) }}" inputmode="decimal" class="admin-form__input"></label>
                    <label class="admin-form__campo {{ $campo('superficie_terreno') }}"><span class="admin-form__etiqueta">TERRENO (M²)</span><input name="superficie_terreno" value="{{ old('superficie_terreno', $numero($lote->superficie_terreno)) }}" inputmode="decimal" class="admin-form__input"></label>
                    <label class="admin-form__campo {{ $campo('dormitorios') }}"><span class="admin-form__etiqueta">DORMITORIOS</span><input name="dormitorios" value="{{ $valor('dormitorios') }}" inputmode="numeric" class="admin-form__input"></label>
                    <label class="admin-form__campo {{ $campo('banos') }}"><span class="admin-form__etiqueta">BAÑOS</span><input name="banos" value="{{ $valor('banos') }}" inputmode="numeric" class="admin-form__input"></label>
                    <label class="admin-form__campo {{ $campo('estacionamientos') }}"><span class="admin-form__etiqueta">ESTACIONAMIENTOS</span><input name="estacionamientos" value="{{ $valor('estacionamientos') }}" inputmode="numeric" class="admin-form__input"></label>
                    <label class="admin-form__campo admin-form__check"><input type="checkbox" name="bodega" value="1" @checked(old('bodega', $lote->bodega))><span>Con bodega</span></label>
                    <label class="admin-form__campo {{ $campo('latitud') }}"><span class="admin-form__etiqueta">LATITUD (MAPA)</span><input name="latitud" value="{{ old('latitud', $lote->latitud !== null ? (float) $lote->latitud : '') }}" placeholder="-33.4145" inputmode="decimal" class="admin-form__input"></label>
                    <label class="admin-form__campo {{ $campo('longitud') }}"><span class="admin-form__etiqueta">LONGITUD (MAPA)</span><input name="longitud" value="{{ old('longitud', $lote->longitud !== null ? (float) $lote->longitud : '') }}" placeholder="-70.5810" inputmode="decimal" class="admin-form__input"></label>
                    <label class="admin-form__campo admin-form__campo--ancho {{ $campo('descripcion') }}"><span class="admin-form__etiqueta">DESCRIPCIÓN</span><textarea name="descripcion" class="admin-form__textarea" maxlength="5000">{{ $valor('descripcion') }}</textarea></label>
                </div>

                <div class="admin-form__seccion admin-form__seccion--siguiente"><span class="admin-form__num">02</span><span class="admin-form__subtitulo">Ficha y antecedentes</span></div>
                <div class="admin-form__grilla">
                    @foreach (Lote::ATRIBUTOS as $clave => $etiqueta)
                        <label class="admin-form__campo {{ $campo('atributos.' . $clave) }}"><span class="admin-form__etiqueta">{{ mb_strtoupper($etiqueta) }}</span><input name="atributos[{{ $clave }}]" value="{{ old('atributos.' . $clave, $lote->atributos[$clave] ?? '') }}" class="admin-form__input" maxlength="255"></label>
                    @endforeach
                </div>

                <div class="admin-form__seccion admin-form__seccion--siguiente"><span class="admin-form__num">03</span><span class="admin-form__subtitulo">Subasta (CLP)</span></div>
                @unless ($editable)
                    <p class="admin-gestion__nota">El remate ya comenzó: el precio base y la duración quedan fijos.</p>
                @endunless
                <div class="admin-form__grilla">
                    <label class="admin-form__campo {{ $campo('precio_base') }}"><span class="admin-form__etiqueta">PRECIO BASE</span><input name="precio_base" value="{{ $valor('precio_base') }}" inputmode="numeric" placeholder="120000000" class="admin-form__input" required @readonly(! $editable)></label>
                    <label class="admin-form__campo {{ $campo('duracion_minutos') }}"><span class="admin-form__etiqueta">DURACIÓN PROPIA (MIN, OPCIONAL)</span><input name="duracion_minutos" value="{{ old('duracion_minutos', $lote->duracion_segundos ? intdiv($lote->duracion_segundos, 60) : '') }}" inputmode="numeric" placeholder="La del remate" class="admin-form__input" @readonly(! $editable)></label>
                </div>

                <x-admin.errores />
                <div class="admin-form__botones">
                    <button type="submit" class="admin-form__publicar">{{ $nuevo ? 'Crear lote' : 'Guardar lote' }}</button>
                    <a href="{{ route('admin.remates.show', $remate) }}" class="admin-form__borrador">Cancelar</a>
                </div>
            </div>
        </form>

        @unless ($nuevo)
            <div class="admin-gestion__bloque">
                <div class="admin-seccion__cabeza"><h2 class="admin-seccion__titulo">Fotos</h2></div>
                <p class="admin-gestion__nota">La primera foto es la principal en el listado y en el detalle. Se reducen a 1920 px al subirlas.</p>
                <div class="admin-fotos">
                    @forelse ($lote->imagenes as $imagen)
                        <div class="admin-foto">
                            <img src="{{ $imagen->url() }}" alt="{{ $imagen->texto_alternativo }}" loading="lazy">
                            <div class="admin-foto__pie">
                                @if ($loop->first)
                                    <span class="admin-foto__principal">PRINCIPAL</span>
                                @else
                                    <form method="POST" action="{{ route('admin.lotes.imagenes.portada', [$remate, $lote, $imagen]) }}" class="formulario-en-linea">
                                        @csrf
                                        <button type="submit" class="admin-accion">Hacer principal</button>
                                    </form>
                                @endif
                                <form method="POST" action="{{ route('admin.lotes.imagenes.destroy', [$remate, $lote, $imagen]) }}" class="formulario-en-linea" onsubmit="return confirm('¿Eliminar esta foto?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="admin-accion admin-accion--peligro">Eliminar</button>
                                </form>
                            </div>
                        </div>
                    @empty
                        <div class="admin-vacio">Sin fotos todavía.</div>
                    @endforelse
                </div>
                <form method="POST" action="{{ route('admin.lotes.imagenes.store', [$remate, $lote]) }}" enctype="multipart/form-data" class="admin-form admin-form--plano">
                    @csrf
                    <div class="admin-form__interior">
                        <div class="admin-form__grilla">
                            <label class="admin-form__campo {{ $campo('imagenes') }}"><span class="admin-form__etiqueta">AGREGAR FOTOS (JPG, PNG O WEBP · HASTA 20 A LA VEZ)</span><input type="file" name="imagenes[]" accept="image/jpeg,image/png,image/webp" multiple required></label>
                        </div>
                        <div class="admin-form__botones admin-form__botones--compactos"><button type="submit" class="admin-form__borrador">Subir fotos</button></div>
                    </div>
                </form>
            </div>

            <div class="admin-gestion__bloque">
                <div class="admin-seccion__cabeza"><h2 class="admin-seccion__titulo">Horarios de visita</h2></div>
                @forelse ($lote->visitas as $visita)
                    <div class="admin-fila">
                        <div>
                            <div class="admin-fila__principal">{{ Formato::fechaLarga($visita->inicia_en) }}</div>
                            <div class="admin-fila__secundario">{{ $visita->inicia_en->setTimezone(Formato::ZONA)->format('H:i') }} – {{ $visita->termina_en->setTimezone(Formato::ZONA)->format('H:i') }}{{ $visita->notas ? ' · ' . $visita->notas : '' }}</div>
                        </div>
                        <form method="POST" action="{{ route('admin.lotes.visitas.destroy', [$remate, $lote, $visita->id]) }}" class="formulario-en-linea">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="admin-accion admin-accion--peligro">Eliminar</button>
                        </form>
                    </div>
                @empty
                    <div class="admin-vacio">Sin horarios de visita. El sitio muestra «Coordinar visita» igual.</div>
                @endforelse
                <form method="POST" action="{{ route('admin.lotes.visitas.store', [$remate, $lote]) }}" class="admin-form admin-form--plano">
                    @csrf
                    <div class="admin-form__interior">
                        <div class="admin-form__grilla">
                            <label class="admin-form__campo {{ $campo('inicia_en') }}"><span class="admin-form__etiqueta">DESDE (HORA DE SANTIAGO)</span><input type="datetime-local" name="inicia_en" class="admin-form__input" required></label>
                            <label class="admin-form__campo {{ $campo('termina_en') }}"><span class="admin-form__etiqueta">HASTA</span><input type="datetime-local" name="termina_en" class="admin-form__input" required></label>
                            <label class="admin-form__campo"><span class="admin-form__etiqueta">NOTAS (OPCIONAL)</span><input name="notas" maxlength="255" class="admin-form__input" placeholder="Consultar en conserjería"></label>
                        </div>
                        <div class="admin-form__botones admin-form__botones--compactos"><button type="submit" class="admin-form__borrador">Agregar horario</button></div>
                    </div>
                </form>
            </div>
        @endunless
    </div>
</x-layouts.admin>
