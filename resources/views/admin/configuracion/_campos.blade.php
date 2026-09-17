{{-- Campos de una sección, agrupados por subtítulo. Recibe: $grupos (clave de grupo => campos) y $valores. --}}
@php
    use App\Models\Configuracion;

    $error = fn (string $clave) => $errors->has("config.{$clave}") ? 'es-invalido' : '';
    // Tras un error de validación, la lista de montos vuelve como arreglo (ya separada por el controlador).
    $viejo = fn (string $clave, $actual) => is_array($v = old("config.{$clave}", $actual)) ? implode(', ', $v) : $v;
    $variosGrupos = count($grupos) > 1;
@endphp
@foreach ($grupos as $grupo => $campos)
    @if ($variosGrupos)
        <div @class(['admin-form__seccion', 'admin-form__seccion--siguiente' => ! $loop->first]) id="grupo-{{ $grupo }}">
            <span class="admin-form__num">{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>
            <span class="admin-form__subtitulo">{{ Configuracion::GRUPOS[$grupo] ?? $grupo }}</span>
        </div>
    @endif
    <div class="admin-form__grilla">
        @foreach ($campos as $clave => $c)
            @php($actual = $valores[$clave])
            @switch($c['tipo'])
                @case('booleano')
                    <label class="admin-form__campo admin-form__check admin-form__campo--ancho {{ $error($clave) }}">
                        <input type="hidden" name="config[{{ $clave }}]" value="0">
                        <input type="checkbox" name="config[{{ $clave }}]" value="1" @checked((bool) $viejo($clave, $actual))>
                        <span>{{ $c['etiqueta'] }}<span class="admin-form__ayuda" style="display: block">{{ $c['descripcion'] }}</span></span>
                    </label>
                    @break
                @case('opcion')
                    <label class="admin-form__campo {{ $error($clave) }}"><span class="admin-form__etiqueta">{{ mb_strtoupper($c['etiqueta']) }}</span>
                        <select name="config[{{ $clave }}]" class="admin-form__input">
                            @foreach ($c['opciones'] as $valor => $texto)
                                <option value="{{ $valor }}" @selected($viejo($clave, $actual) === $valor)>{{ $texto }}</option>
                            @endforeach
                        </select>
                        @if ($c['descripcion'])<span class="admin-form__ayuda">{{ $c['descripcion'] }}</span>@endif
                    </label>
                    @break
                @case('texto_largo')
                    <label class="admin-form__campo admin-form__campo--ancho {{ $error($clave) }}"><span class="admin-form__etiqueta">{{ mb_strtoupper($c['etiqueta']) }}</span>
                        <textarea name="config[{{ $clave }}]" class="admin-form__textarea" maxlength="2000">{{ $viejo($clave, $actual) }}</textarea>
                        @if ($c['descripcion'])<span class="admin-form__ayuda">{{ $c['descripcion'] }}</span>@endif
                    </label>
                    @break
                @case('secreto')
                    <label class="admin-form__campo {{ $error($clave) }}"><span class="admin-form__etiqueta">{{ mb_strtoupper($c['etiqueta']) }}</span>
                        <input type="password" name="config[{{ $clave }}]" autocomplete="new-password" placeholder="{{ $actual ? '•••••••• (configurada)' : 'Sin configurar' }}" class="admin-form__input">
                        <span class="admin-form__ayuda">{{ $c['descripcion'] }}</span>
                    </label>
                    @break
                @default
                    @php($mostrado = $c['tipo'] === 'lista_montos' ? implode(', ', array_map(fn ($m) => number_format($m, 0, ',', '.'), (array) $actual)) : $actual)
                    <label class="admin-form__campo {{ $error($clave) }}"><span class="admin-form__etiqueta">{{ mb_strtoupper($c['etiqueta']) }}</span>
                        <input name="config[{{ $clave }}]" value="{{ $viejo($clave, $mostrado) }}" class="admin-form__input"
                            @if (! empty($c['solo_lectura'])) readonly disabled @endif
                            @if (in_array($c['tipo'], ['entero', 'porcentaje', 'decimal'], true)) inputmode="decimal" @endif
                            @if ($c['tipo'] === 'correo') type="email" @elseif ($c['tipo'] === 'url') type="url" placeholder="https://" @endif>
                        @if ($c['descripcion'])<span class="admin-form__ayuda">{{ $c['descripcion'] }}</span>@endif
                    </label>
            @endswitch
        @endforeach
    </div>
@endforeach
