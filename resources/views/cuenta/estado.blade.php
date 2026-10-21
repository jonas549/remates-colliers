@php
    use App\Support\Sitio;

    $d = $detalle;
    $r = $d['remate'];
    $g = $d['garantia'];
    $b = $d['banco'];
    $enlaceCorreo = '<a href="mailto:' . e(Sitio::correo()) . '">' . e(Sitio::correo()) . '</a>';
@endphp
<x-layouts.base :titulo="__('cuenta.nav.titulo')">
    <div class="cuenta">

        <x-tramite.cabecera :etiqueta="__('cuenta.nav.etiqueta')" :ancho="1180" class="tramite-cabecera--cuenta">
            <a href="{{ route('remates.index') }}" class="cuenta__nav-remates">{{ __('cuenta.nav.remates') }}</a>
            <span class="cuenta__nav-usuario">{{ auth()->user()?->name }}</span>
            <form method="POST" action="{{ route('logout') }}" class="formulario-en-linea">
                @csrf
                <button type="submit" class="boton-enlace cuenta__nav-salir">{{ __('cuenta.nav.cerrar_sesion') }}</button>
            </form>
        </x-tramite.cabecera>

        <div class="cuenta__banda" style="background: {{ $estado['fondo'] }}">
            <div class="cuenta__banda-interior">
                <div class="cuenta__banda-etiqueta">{{ $estado['etiqueta'] }}</div>
                <div class="cuenta__banda-grilla">
                    <h1 class="cuenta__banda-titulo">{{ $estado['titulo'] }}</h1>
                    <p class="cuenta__banda-texto">{{ $estado['texto'] }}</p>
                </div>
            </div>
        </div>

        @if ($d['dePrueba'] ?? false)
            {{-- Postor creado con «Crear subasta de prueba»: su remate no está en el listado público, se entra desde aquí. --}}
            <div class="cuenta__avisos" data-cuenta-prueba>
                <div class="cuenta__aviso">{{ __($estado['clave'] === 'aprobada' ? 'cuenta.aviso_prueba_aprobada' : 'cuenta.aviso_prueba') }}</div>
            </div>
        @endif

        @if (session('estado') || session('error') || $errors->any())
            <div class="cuenta__avisos" role="status" aria-live="polite">
                @if (session('estado'))<div class="cuenta__aviso">{{ session('estado') }}</div>@endif
                @if (session('error'))<div class="cuenta__aviso cuenta__aviso--error">{{ session('error') }}</div>@endif
                @foreach ($errors->all() as $error)<div class="cuenta__aviso cuenta__aviso--error">{{ $error }}</div>@endforeach
            </div>
        @endif

        <div class="cuenta__pasos-contenedor">
            <div class="cuenta__pasos">
                @foreach ($estado['pasos'] as $paso)
                    <div @class(['cuenta__paso', 'es-actual' => $paso['actual'], 'es-alcanzado' => $paso['alcanzado']])>
                        <div class="cuenta__paso-cabeza">
                            <div class="cuenta__paso-num">{{ $paso['numero'] }}</div>
                            <div class="chip-tono chip-tono--{{ $paso['tono'] }}">{{ $paso['chip'] }}</div>
                        </div>
                        <div class="cuenta__paso-titulo">{{ $paso['titulo'] }}</div>
                        <div class="cuenta__paso-texto">{{ $paso['texto'] }}</div>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="cuenta__cuerpo">

            <div class="cuenta__columna">
                @if (! empty($d['adjudicaciones']))
                    {{-- Lotes adjudicados al postor (QA 21/10): lo primero que ve al volver después del cierre. --}}
                    <div class="cuenta__adjudicaciones" data-adjudicaciones>
                        <div class="cuenta__subtitulo cuenta__subtitulo--ok">{{ __('cuenta.adjudicaciones.subtitulo') }}</div>
                        @foreach ($d['adjudicaciones'] as $a)
                            <div class="cuenta__item">
                                <span class="cuenta__item-marca cuenta__item-marca--ok">✓</span>
                                <div class="cuenta__item-texto"><strong>{{ $a['texto'] }}</strong><br><a href="{{ $a['url'] }}">{{ $a['remate'] }}</a></div>
                            </div>
                        @endforeach
                    </div>
                @endif
                <div class="cuenta__bloque-titulo">
                    <div class="cuenta__kicker">{{ __($r ? 'cuenta.garantia.kicker_remate' : 'cuenta.garantia.kicker_inscripcion') }}</div>
                </div>

                @if ($r)
                    <div class="cuenta__remate">{{ $r['titulo'] }}</div>
                    <div class="cuenta__remate-meta">{{ $r['meta'] }}</div>

                    <div class="cuenta__datos">
                        <div class="cuenta__dato">
                            <div class="cuenta__dato-etiqueta">{{ __('cuenta.garantia.monto') }}</div>
                            <div class="cuenta__dato-valor">{{ $g['monto'] }}</div>
                        </div>
                        <div class="cuenta__dato">
                            <div class="cuenta__dato-etiqueta">{{ __('cuenta.garantia.plazo') }}</div>
                            <div class="cuenta__dato-valor">{{ $g['plazo'] }}</div>
                        </div>
                        <div class="cuenta__dato">
                            <div class="cuenta__dato-etiqueta">{{ __('cuenta.garantia.estado') }}</div>
                            <div class="cuenta__dato-estado cuenta__dato-estado--{{ $estado['tonoGarantia'] }}">{{ $estado['garantia'] }}</div>
                        </div>
                    </div>

                    @if ($estado['clave'] === 'remate-cancelado')
                        {{-- Remate cancelado (21/09): nada que constituir ni pujar; se dice qué pasó y adónde ir. --}}
                        <div data-remate-cancelado>
                            <div class="cuenta__subtitulo">{{ __('cuenta.cancelado.subtitulo') }}</div>
                            <div class="cuenta__item">
                                <span class="cuenta__item-marca">✕</span>
                                <div class="cuenta__item-texto">{{ $r['cancelado'] ? __('cuenta.cancelado.texto_fecha', ['fecha' => $r['cancelado']]) : __('cuenta.cancelado.texto') }}</div>
                            </div>
                            <div class="cuenta__botones">
                                <a href="{{ route('remates.index') }}" class="cuenta__boton">{{ __('cuenta.cancelado.ver_remates') }}</a>
                                @if ($g['comprobante'])
                                    <a href="{{ $g['comprobante'] }}" class="cuenta__boton-secundario">{{ __('cuenta.cancelado.ver_comprobante') }}</a>
                                @endif
                            </div>
                            <p class="cuenta__nota">{!! __('cuenta.cancelado.nota', ['correo' => $enlaceCorreo]) !!}</p>
                        </div>
                    @elseif (! $estado['habilitado'])
                        <div>
                            <div class="cuenta__subtitulo">{{ __('cuenta.constituir.subtitulo') }}</div>
                            <div class="cuenta__lista">
                                @if ($b)
                                    <div class="cuenta__item">
                                        <span class="cuenta__item-marca">01</span>
                                        <div class="cuenta__item-texto">{!! __($b['vale_vista_direccion'] ? 'cuenta.constituir.vale_entregado' : 'cuenta.constituir.vale', ['titular' => '<strong>' . e($b['banco_titular']) . '</strong>', 'direccion' => e($b['vale_vista_direccion'])]) !!}</div>
                                    </div>
                                    <div class="cuenta__item">
                                        <span class="cuenta__item-marca">02</span>
                                        <div class="cuenta__item-texto">{{ __($b['banco_rut_titular'] ? 'cuenta.constituir.transferencia_rut' : 'cuenta.constituir.transferencia', ['tipo' => mb_strtolower($b['banco_tipo_cuenta']), 'banco' => $b['banco_nombre'], 'numero' => $b['banco_numero_cuenta'], 'rut' => $b['banco_rut_titular']]) }}</div>
                                    </div>
                                @else
                                    <div class="cuenta__item">
                                        <span class="cuenta__item-marca">01</span>
                                        <div class="cuenta__item-texto">{!! __('cuenta.constituir.sin_banco', ['monto' => '<strong>' . e($g['monto']) . '</strong>', 'correo' => $enlaceCorreo]) !!}</div>
                                    </div>
                                @endif
                                <div class="cuenta__item">
                                    <span class="cuenta__item-marca">{{ $b ? '03' : '02' }}</span>
                                    <div class="cuenta__item-texto">{!! __('cuenta.constituir.subir_o_enviar', ['correo' => $enlaceCorreo]) !!}</div>
                                </div>
                            </div>

                            @if ($g['subir'])
                                <form method="POST" action="{{ $g['subir'] }}" enctype="multipart/form-data" class="cuenta__form" x-data="{ abierto: {{ $errors->any() ? 'true' : 'false' }} }">
                                    @csrf
                                    <div class="cuenta__botones">
                                        <button type="button" class="cuenta__boton" @click="abierto = !abierto" x-show="!abierto">{{ __('cuenta.constituir.subir') }}</button>
                                        <a href="{{ $r['url'] }}" class="cuenta__boton-secundario" x-show="!abierto">{{ __('cuenta.constituir.ver_antecedentes') }}</a>
                                    </div>
                                    <div class="cuenta__form-campos" x-show="abierto" x-cloak>
                                        <label class="cuenta__form-campo"><span>{{ __('cuenta.constituir.medio') }}</span>
                                            <select name="medio" required>
                                                <option value="transferencia" @selected(old('medio') === 'transferencia')>{{ __('cuenta.constituir.transferencia_opcion') }}</option>
                                                <option value="vale_vista" @selected(old('medio') === 'vale_vista')>{{ __('cuenta.constituir.vale_opcion') }}</option>
                                            </select>
                                        </label>
                                        <label class="cuenta__form-campo"><span>{{ __('cuenta.constituir.archivo') }}</span>
                                            <input type="file" name="comprobante" accept=".pdf,.jpg,.jpeg,.png" required>
                                        </label>
                                        <div class="cuenta__botones">
                                            <button type="submit" class="cuenta__boton">{{ __('cuenta.constituir.enviar') }}</button>
                                            <button type="button" class="cuenta__boton-secundario" @click="abierto = false">{{ __('cuenta.constituir.cancelar') }}</button>
                                        </div>
                                    </div>
                                </form>
                            @else
                                <div class="cuenta__botones">
                                    @if ($g['comprobante'])
                                        <a href="{{ $g['comprobante'] }}" class="cuenta__boton-secundario">{{ __('cuenta.constituir.ver_comprobante_enviado') }}</a>
                                    @endif
                                    <a href="{{ $r['url'] }}" class="cuenta__boton-secundario">{{ __('cuenta.constituir.ver_antecedentes') }}</a>
                                </div>
                            @endif

                            <p class="cuenta__nota">{{ $g['recibido'] ? __('cuenta.constituir.nota_recibido', ['fecha' => $g['recibido']]) : __('cuenta.constituir.nota') }}</p>
                        </div>
                    @else
                        <div>
                            <div class="cuenta__subtitulo cuenta__subtitulo--ok">{{ __('cuenta.aprobada.subtitulo') }}</div>
                            <div class="cuenta__item">
                                <span class="cuenta__item-marca cuenta__item-marca--ok">✓</span>
                                <div class="cuenta__item-texto">{!! __('cuenta.aprobada.recibida' . ($b ? '_titular' : '') . ($g['recibido'] ? '_fecha' : ''), ['medio' => e($g['medio'] ? ucfirst($g['medio']) : __('cuenta.aprobada.garantia')), 'monto' => '<strong>' . e($g['monto']) . '</strong>', 'titular' => e($b['banco_titular'] ?? ''), 'fecha' => e($g['recibido'] ?? '')]) !!}</div>
                            </div>
                            <div class="cuenta__item">
                                <span class="cuenta__item-marca cuenta__item-marca--ok">✓</span>
                                <div class="cuenta__item-texto">{!! __('cuenta.aprobada.aprobada_el', ['fecha' => '<strong>' . e($g['aprobada']) . '</strong>']) !!}</div>
                            </div>
                            <div class="cuenta__item">
                                <span class="cuenta__item-marca">→</span>
                                <div class="cuenta__item-texto">{!! ($r['finalizado'] ?? false) ? e(__('cuenta.aprobada.finalizado')) : ($r['enVivo'] ? __('cuenta.aprobada.en_vivo') : __('cuenta.aprobada.abre', ['fecha' => '<strong>' . e($r['abre']) . '</strong>'])) !!}</div>
                            </div>

                            <div class="cuenta__botones">
                                @if ($r['sala'])
                                    <a href="{{ $r['sala'] }}" class="cuenta__boton">{{ __('cuenta.aprobada.entrar_sala') }}</a>
                                @endif
                                @if ($g['comprobante'])
                                    <a href="{{ $g['comprobante'] }}" class="cuenta__boton-secundario">{{ __('cuenta.aprobada.ver_comprobante') }}</a>
                                @endif
                            </div>

                            <p class="cuenta__nota">{{ __('cuenta.aprobada.nota') }}</p>
                        </div>
                    @endif
                @elseif ($d['habilitadoEnTodos'] ?? false)
                    {{-- Modo «habilitación única»: no hay nada que inscribir, la garantía ya sirve para todos. --}}
                    <p class="cuenta__lateral-texto">{{ __('cuenta.inscripcion.habilitado_en_todos') }}</p>
                @elseif ($d['abiertos'])
                    <div class="cuenta__lista">
                        @foreach ($d['abiertos'] as $abierto)
                            <div class="cuenta__item cuenta__item--remate">
                                <div class="cuenta__item-texto">
                                    <a href="{{ $abierto['url'] }}"><strong>{{ $abierto['titulo'] }}</strong></a><br>
                                    {{ $abierto['folio'] }} · {{ $abierto['fecha'] }} · {{ __('cuenta.inscripcion.garantia') }} {{ $abierto['garantia'] }}
                                </div>
                                <form method="POST" action="{{ $abierto['inscribir'] }}" class="formulario-en-linea">
                                    @csrf
                                    <button type="submit" class="cuenta__boton-secundario">{{ __('cuenta.inscripcion.inscribirme') }}</button>
                                </form>
                            </div>
                        @endforeach
                    </div>
                    <p class="cuenta__nota">{{ __('cuenta.inscripcion.nota') }}</p>
                @else
                    <p class="cuenta__lateral-texto">{{ __(in_array($estado['clave'], ['cuenta-revision'], true) ? 'cuenta.inscripcion.cuando_apruebe' : 'cuenta.inscripcion.sin_abiertos') }}</p>
                @endif
            </div>

            <div class="cuenta__columna">
                <div class="cuenta__bloque-titulo cuenta__bloque-titulo--lateral">
                    <div class="cuenta__kicker">{{ $estado['tituloLateral'] }}</div>
                </div>
                <p class="cuenta__lateral-texto">{{ $estado['textoLateral'] }}</p>
                <div class="cuenta__accesos">
                    <a href="{{ route('remates.index') }}" class="cuenta__acceso">{{ __('cuenta.lateral.ver_remates') }} <span>→</span></a>
                    @if ($r && $r['sala'])
                        <a href="{{ $r['sala'] }}" class="cuenta__acceso">{{ __('cuenta.lateral.entrar_sala') }} <span>→</span></a>
                    @endif
                    @if ($d['basesGenerales'])
                        <a href="{{ $d['basesGenerales'] }}" class="cuenta__acceso" target="_blank" rel="noopener">{{ __('cuenta.lateral.bases_generales') }} <span>→</span></a>
                    @endif
                    <a href="{{ route('cuenta.sesiones') }}" class="cuenta__acceso">{{ __('cuenta.lateral.sesiones') }} <span>→</span></a>
                </div>

                @if (count($d['inscripciones']) > 1)
                    <div class="cuenta__subtitulo">{{ __('cuenta.lateral.tus_inscripciones') }}</div>
                    <div class="cuenta__accesos">
                        @foreach ($d['inscripciones'] as $i)
                            <a href="{{ $i['url'] }}" @class(['cuenta__acceso', 'es-actual' => $i['actual']])>{{ $i['folio'] }} · {{ $i['titulo'] }} <span>{{ $i['estado'] }}</span></a>
                        @endforeach
                    </div>
                @endif

                <div class="cuenta__contacto">
                    <div class="cuenta__contacto-titulo">{{ __('cuenta.lateral.contacto') }}</div>
                    <p>{{ __('cuenta.lateral.contacto_texto', ['horas' => Sitio::horasRevision()]) }}</p>
                    <div class="cuenta__contacto-enlaces">
                        <a href="mailto:{{ Sitio::correo() }}">{{ Sitio::correo() }}</a>
                        <a href="{{ Sitio::telefonoEnlace() }}">{{ Sitio::telefono() }}</a>
                    </div>
                </div>
            </div>
        </div>

        <x-tramite.pie :ancho="1180" />
    </div>
</x-layouts.base>
