@php
    use App\Models\Configuracion;
    use App\Support\Formato;

    $error = fn (string $clave) => $errors->has("config.{$clave}") ? 'es-invalido' : '';
    // Tras un error de validación, la lista de montos vuelve como arreglo (ya separada por el controlador).
    $viejo = fn (string $clave, $actual) => is_array($v = old("config.{$clave}", $actual)) ? implode(', ', $v) : $v;
@endphp
<x-layouts.admin seccion="configuracion" titulo="Configuración" clase-cuerpo="placeholder-claro">
    <div class="admin-encabezado">
        <div class="admin-encabezado__texto">
            <div class="admin-encabezado__kicker">CONFIGURACIÓN</div>
            <h1 class="admin-encabezado__titulo admin-encabezado__titulo--con-bajada">Valores del negocio</h1>
            <div class="admin-encabezado__bajada">Todo lo que aquí cambias rige desde la próxima acción, sin desplegar. Las garantías y remates ya creados conservan su monto y sus condiciones.</div>
        </div>
    </div>

    <x-admin.avisos />

    <div class="admin-gestion">
        <form method="POST" action="{{ route('admin.configuracion.update') }}" class="admin-form admin-form--plano">
            @csrf
            @method('PUT')
            <div class="admin-form__interior">
                <x-admin.errores />
                @foreach ($grupos as $grupo => $campos)
                    <div @class(['admin-form__seccion', 'admin-form__seccion--siguiente' => ! $loop->first]) id="grupo-{{ $grupo }}">
                        <span class="admin-form__num">{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>
                        <span class="admin-form__subtitulo">{{ Configuracion::GRUPOS[$grupo] ?? $grupo }}</span>
                    </div>
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
                <div class="admin-form__botones">
                    <button type="submit" class="admin-form__publicar">Guardar configuración</button>
                </div>
            </div>
        </form>

        <div class="admin-gestion__bloque">
            <div class="admin-seccion__cabeza"><h2 class="admin-seccion__titulo">Probar el correo saliente</h2></div>
            <p class="admin-gestion__nota">Guarda primero los cambios. La prueba se envía en el momento (no pasa por la cola) y muestra el error si el servidor SMTP lo rechaza.</p>
            <form method="POST" action="{{ route('admin.configuracion.probar-correo') }}" class="admin-form admin-form--plano">
                @csrf
                <div class="admin-form__interior">
                    <div class="admin-form__grilla">
                        <label class="admin-form__campo {{ $errors->has('destino') ? 'es-invalido' : '' }}"><span class="admin-form__etiqueta">ENVIAR PRUEBA A</span><input type="email" name="destino" value="{{ old('destino', auth()->user()->email) }}" class="admin-form__input" required></label>
                    </div>
                    <div class="admin-form__botones admin-form__botones--compactos"><button type="submit" class="admin-form__borrador">Enviar correo de prueba</button></div>
                </div>
            </form>
        </div>

        <div class="admin-gestion__bloque">
            <div class="admin-seccion__cabeza"><h2 class="admin-seccion__titulo">Unidad de fomento</h2></div>
            <p class="admin-gestion__nota">
                Valor vigente: {{ $valores['uf_valor'] ? '$' . number_format((float) $valores['uf_valor'], 2, ',', '.') : 'sin valor' }}{{ $valores['uf_fecha'] ? ' del ' . Formato::dia(\Carbon\CarbonImmutable::parse($valores['uf_fecha'], Formato::ZONA)) : '' }}.
                Con fuente automática se actualiza cada hora desde mindicador.cl; si el servidor no puede salir a Internet, se mantiene el último valor.
            </p>
            <form method="POST" action="{{ route('admin.configuracion.actualizar-uf') }}" class="formulario-en-linea">
                @csrf
                <button type="submit" class="admin-form__borrador">Actualizar la UF ahora</button>
            </form>
        </div>

        <div class="admin-gestion__bloque">
            <div class="admin-seccion__cabeza"><h2 class="admin-seccion__titulo">Sistema</h2></div>
            <p class="admin-gestion__nota">Visto desde la web, que es el PHP que atiende las pujas (la consola puede ser distinta).</p>
            <div class="admin-datos">
                @foreach ($sistema as $k => $v)
                    <div class="admin-ficha__dato"><span class="admin-ficha__k">{{ $k }}</span><span class="admin-ficha__v">{{ $v }}</span></div>
                @endforeach
            </div>
        </div>
    </div>
</x-layouts.admin>
