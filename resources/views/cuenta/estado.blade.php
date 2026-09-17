@php
    use App\Support\Sitio;

    $d = $detalle;
    $r = $d['remate'];
    $g = $d['garantia'];
    $b = $d['banco'];
@endphp
<x-layouts.base titulo="Mi cuenta">
    <div class="cuenta">

        <x-tramite.cabecera etiqueta="MI CUENTA" :ancho="1180" class="tramite-cabecera--cuenta">
            <a href="{{ route('remates.index') }}" class="cuenta__nav-remates">Remates</a>
            <span class="cuenta__nav-usuario">{{ auth()->user()?->name }}</span>
            <form method="POST" action="{{ route('logout') }}" class="formulario-en-linea">
                @csrf
                <button type="submit" class="boton-enlace cuenta__nav-salir">Cerrar sesión</button>
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
                <div class="cuenta__bloque-titulo">
                    <div class="cuenta__kicker">{{ $r ? 'GARANTÍA DEL REMATE' : 'INSCRIPCIÓN EN UN REMATE' }}</div>
                </div>

                @if ($r)
                    <div class="cuenta__remate">{{ $r['titulo'] }}</div>
                    <div class="cuenta__remate-meta">{{ $r['meta'] }}</div>

                    <div class="cuenta__datos">
                        <div class="cuenta__dato">
                            <div class="cuenta__dato-etiqueta">MONTO</div>
                            <div class="cuenta__dato-valor">{{ $g['monto'] }}</div>
                        </div>
                        <div class="cuenta__dato">
                            <div class="cuenta__dato-etiqueta">PLAZO</div>
                            <div class="cuenta__dato-valor">{{ $g['plazo'] }}</div>
                        </div>
                        <div class="cuenta__dato">
                            <div class="cuenta__dato-etiqueta">ESTADO</div>
                            <div class="cuenta__dato-estado cuenta__dato-estado--{{ $estado['tonoGarantia'] }}">{{ $estado['garantia'] }}</div>
                        </div>
                    </div>

                    @unless ($estado['habilitado'])
                        <div>
                            <div class="cuenta__subtitulo">CÓMO CONSTITUIRLA</div>
                            <div class="cuenta__lista">
                                @if ($b)
                                    <div class="cuenta__item">
                                        <span class="cuenta__item-marca">01</span>
                                        <div class="cuenta__item-texto">Vale a la vista a nombre de <strong>{{ $b['banco_titular'] }}</strong>@if ($b['vale_vista_direccion']), entregado en {{ $b['vale_vista_direccion'] }}@endif.</div>
                                    </div>
                                    <div class="cuenta__item">
                                        <span class="cuenta__item-marca">02</span>
                                        <div class="cuenta__item-texto">O transferencia a la {{ mb_strtolower($b['banco_tipo_cuenta']) }} {{ $b['banco_nombre'] }} N.º {{ $b['banco_numero_cuenta'] }}@if ($b['banco_rut_titular']), RUT {{ $b['banco_rut_titular'] }}@endif, indicando el número de remate.</div>
                                    </div>
                                @else
                                    <div class="cuenta__item">
                                        <span class="cuenta__item-marca">01</span>
                                        <div class="cuenta__item-texto">Vale a la vista o transferencia por <strong>{{ $g['monto'] }}</strong>. Escríbenos a <a href="mailto:{{ Sitio::correo() }}">{{ Sitio::correo() }}</a> para recibir los datos de la cuenta y del vale.</div>
                                    </div>
                                @endif
                                <div class="cuenta__item">
                                    <span class="cuenta__item-marca">{{ $b ? '03' : '02' }}</span>
                                    <div class="cuenta__item-texto">Sube aquí el comprobante, o envíalo a <a href="mailto:{{ Sitio::correo() }}">{{ Sitio::correo() }}</a> con tu nombre y el remate al que postulas.</div>
                                </div>
                            </div>

                            @if ($g['subir'])
                                <form method="POST" action="{{ $g['subir'] }}" enctype="multipart/form-data" class="cuenta__form" x-data="{ abierto: {{ $errors->any() ? 'true' : 'false' }} }">
                                    @csrf
                                    <div class="cuenta__botones">
                                        <button type="button" class="cuenta__boton" @click="abierto = !abierto" x-show="!abierto">Subir comprobante</button>
                                        <a href="{{ $r['url'] }}" class="cuenta__boton-secundario" x-show="!abierto">Ver antecedentes del remate</a>
                                    </div>
                                    <div class="cuenta__form-campos" x-show="abierto" x-cloak>
                                        <label class="cuenta__form-campo"><span>MEDIO</span>
                                            <select name="medio" required>
                                                <option value="transferencia" @selected(old('medio') === 'transferencia')>Transferencia</option>
                                                <option value="vale_vista" @selected(old('medio') === 'vale_vista')>Vale a la vista</option>
                                            </select>
                                        </label>
                                        <label class="cuenta__form-campo"><span>COMPROBANTE (PDF, JPG O PNG · HASTA 10 MB)</span>
                                            <input type="file" name="comprobante" accept=".pdf,.jpg,.jpeg,.png" required>
                                        </label>
                                        <div class="cuenta__botones">
                                            <button type="submit" class="cuenta__boton">Enviar comprobante</button>
                                            <button type="button" class="cuenta__boton-secundario" @click="abierto = false">Cancelar</button>
                                        </div>
                                    </div>
                                </form>
                            @else
                                <div class="cuenta__botones">
                                    @if ($g['comprobante'])
                                        <a href="{{ $g['comprobante'] }}" class="cuenta__boton-secundario">Ver comprobante enviado</a>
                                    @endif
                                    <a href="{{ $r['url'] }}" class="cuenta__boton-secundario">Ver antecedentes del remate</a>
                                </div>
                            @endif

                            <p class="cuenta__nota">La revisión es manual y no hay pagos en línea. Una vez aprobada quedas habilitado para pujar en ese remate; el destino de la garantía al terminar el remate se rige por sus bases.@if ($g['recibido']) Comprobante recibido el {{ $g['recibido'] }}.@endif</p>
                        </div>
                    @else
                        <div>
                            <div class="cuenta__subtitulo cuenta__subtitulo--ok">GARANTÍA RECIBIDA Y APROBADA</div>
                            <div class="cuenta__item">
                                <span class="cuenta__item-marca cuenta__item-marca--ok">✓</span>
                                <div class="cuenta__item-texto">{{ $g['medio'] ? ucfirst($g['medio']) : 'Garantía' }} por <strong>{{ $g['monto'] }}</strong>@if ($b) a nombre de {{ $b['banco_titular'] }}@endif{{ $g['recibido'] ? ', recibido el ' . $g['recibido'] : ', recibida por Colliers' }}.</div>
                            </div>
                            <div class="cuenta__item">
                                <span class="cuenta__item-marca cuenta__item-marca--ok">✓</span>
                                <div class="cuenta__item-texto">Aprobada por Colliers el <strong>{{ $g['aprobada'] }}</strong>. Quedas habilitado para pujar en este remate.</div>
                            </div>
                            <div class="cuenta__item">
                                <span class="cuenta__item-marca">→</span>
                                <div class="cuenta__item-texto">{!! $r['enVivo'] ? 'El remate está <strong>en vivo</strong>: entra a la sala de pujas.' : 'La sala de pujas se abre el <strong>' . e($r['abre']) . '</strong>, junto con la transmisión del remate.' !!}</div>
                            </div>

                            <div class="cuenta__botones">
                                @if ($r['sala'])
                                    <a href="{{ $r['sala'] }}" class="cuenta__boton">Entrar a la sala de pujas</a>
                                @endif
                                @if ($g['comprobante'])
                                    <a href="{{ $g['comprobante'] }}" class="cuenta__boton-secundario">Ver comprobante</a>
                                @endif
                            </div>

                            <p class="cuenta__nota">Si te adjudicas la propiedad, Colliers te contactará para la firma y el pago del saldo. El destino de la garantía se rige por las bases del remate.</p>
                        </div>
                    @endunless
                @elseif ($d['abiertos'])
                    <div class="cuenta__lista">
                        @foreach ($d['abiertos'] as $abierto)
                            <div class="cuenta__item cuenta__item--remate">
                                <div class="cuenta__item-texto">
                                    <a href="{{ $abierto['url'] }}"><strong>{{ $abierto['titulo'] }}</strong></a><br>
                                    {{ $abierto['folio'] }} · {{ $abierto['fecha'] }} · garantía {{ $abierto['garantia'] }}
                                </div>
                                <form method="POST" action="{{ $abierto['inscribir'] }}" class="formulario-en-linea">
                                    @csrf
                                    <button type="submit" class="cuenta__boton-secundario">Inscribirme</button>
                                </form>
                            </div>
                        @endforeach
                    </div>
                    <p class="cuenta__nota">Al inscribirte se calcula la garantía de ese remate y aquí verás cómo constituirla.</p>
                @else
                    <p class="cuenta__lateral-texto">{{ in_array($estado['clave'], ['cuenta-revision'], true) ? 'Cuando Colliers apruebe tu cuenta podrás inscribirte en los remates publicados.' : 'No hay remates abiertos a inscripción en este momento. Te avisaremos por correo cuando se publique uno nuevo.' }}</p>
                @endif
            </div>

            <div class="cuenta__columna">
                <div class="cuenta__bloque-titulo cuenta__bloque-titulo--lateral">
                    <div class="cuenta__kicker">{{ $estado['tituloLateral'] }}</div>
                </div>
                <p class="cuenta__lateral-texto">{{ $estado['textoLateral'] }}</p>
                <div class="cuenta__accesos">
                    <a href="{{ route('remates.index') }}" class="cuenta__acceso">Ver remates publicados <span>→</span></a>
                    @if ($r && $r['sala'])
                        <a href="{{ $r['sala'] }}" class="cuenta__acceso">Entrar a la sala de pujas <span>→</span></a>
                    @endif
                    @if ($d['basesGenerales'])
                        <a href="{{ $d['basesGenerales'] }}" class="cuenta__acceso" target="_blank" rel="noopener">Bases generales de los remates <span>→</span></a>
                    @endif
                    <a href="{{ route('cuenta.sesiones') }}" class="cuenta__acceso">Sesiones activas y contraseña <span>→</span></a>
                </div>

                @if (count($d['inscripciones']) > 1)
                    <div class="cuenta__subtitulo">TUS INSCRIPCIONES</div>
                    <div class="cuenta__accesos">
                        @foreach ($d['inscripciones'] as $i)
                            <a href="{{ $i['url'] }}" @class(['cuenta__acceso', 'es-actual' => $i['actual']])>{{ $i['folio'] }} · {{ $i['titulo'] }} <span>{{ $i['estado'] }}</span></a>
                        @endforeach
                    </div>
                @endif

                <div class="cuenta__contacto">
                    <div class="cuenta__contacto-titulo">CONTACTO</div>
                    <p>Si tu solicitud lleva más de {{ Sitio::horasRevision() }} horas hábiles sin respuesta, escríbenos indicando tu RUT y el remate.</p>
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
