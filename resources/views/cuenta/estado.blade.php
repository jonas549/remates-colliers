<x-layouts.base titulo="Mi cuenta">
    <div class="cuenta">

        <x-tramite.cabecera etiqueta="MI CUENTA" :ancho="1180" class="tramite-cabecera--cuenta">
            <a href="{{ route('remates.index') }}" class="cuenta__nav-remates">Remates</a>
            <span class="cuenta__nav-usuario">María Paz González</span>
            <a href="{{ route('login') }}" class="cuenta__nav-salir">Cerrar sesión</a>
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
                    <div class="cuenta__kicker">GARANTÍA DEL REMATE</div>
                </div>

                <div class="cuenta__remate">Los Militares 5620, Depto. 703</div>
                <div class="cuenta__remate-meta">R-2026-118 · Las Condes · 09-09-2026, 12:00</div>

                <div class="cuenta__datos">
                    <div class="cuenta__dato">
                        <div class="cuenta__dato-etiqueta">MONTO</div>
                        <div class="cuenta__dato-valor">$6.000.000</div>
                    </div>
                    <div class="cuenta__dato">
                        <div class="cuenta__dato-etiqueta">PLAZO</div>
                        <div class="cuenta__dato-valor">07-09, 18:00</div>
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
                            <div class="cuenta__item">
                                <span class="cuenta__item-marca">01</span>
                                <div class="cuenta__item-texto">Vale a la vista a nombre de <strong>Colliers International Chile S.A.</strong>, entregado en Av. Apoquindo 4499, piso 8, Las Condes.</div>
                            </div>
                            <div class="cuenta__item">
                                <span class="cuenta__item-marca">02</span>
                                <div class="cuenta__item-texto">O transferencia a la cuenta corriente Banco de Chile N.º 000-12345-67, RUT 96.123.456-7, indicando el número de remate.</div>
                            </div>
                            <div class="cuenta__item">
                                <span class="cuenta__item-marca">03</span>
                                <div class="cuenta__item-texto">Envía el comprobante a <a href="mailto:remates@colliers.cl">remates@colliers.cl</a> con tu nombre y el remate al que postulas.</div>
                            </div>
                        </div>

                        <div class="cuenta__botones">
                            <a href="#" class="cuenta__boton">Subir comprobante</a>
                            <a href="#" class="cuenta__boton-secundario">Descargar instrucciones</a>
                        </div>

                        <p class="cuenta__nota">La revisión es manual y no hay pagos en línea. Una vez aprobada quedas habilitado para pujar en ese remate; la garantía se devuelve si no resultas adjudicatario.</p>
                    </div>
                @else
                    <div>
                        <div class="cuenta__subtitulo cuenta__subtitulo--ok">GARANTÍA RECIBIDA Y APROBADA</div>
                        <div class="cuenta__item">
                            <span class="cuenta__item-marca cuenta__item-marca--ok">✓</span>
                            <div class="cuenta__item-texto">Vale a la vista por <strong>$6.000.000</strong> a nombre de Colliers International Chile S.A., recibido el 29-08-2026.</div>
                        </div>
                        <div class="cuenta__item">
                            <span class="cuenta__item-marca cuenta__item-marca--ok">✓</span>
                            <div class="cuenta__item-texto">Aprobada por Colliers el <strong>30-08-2026, 11:24</strong>. Quedas habilitado para pujar en este remate.</div>
                        </div>
                        <div class="cuenta__item">
                            <span class="cuenta__item-marca">→</span>
                            <div class="cuenta__item-texto">La sala de pujas se abre el <strong>09-09-2026 a las 12:00</strong>, junto con la transmisión del remate.</div>
                        </div>

                        <div class="cuenta__botones">
                            <a href="{{ route('sala.show', 'apoquindo') }}" class="cuenta__boton">Entrar a la sala de pujas</a>
                            <a href="#" class="cuenta__boton-secundario">Ver comprobante</a>
                        </div>

                        <p class="cuenta__nota">Si no resultas adjudicatario, la garantía se devuelve dentro de los días hábiles siguientes al remate. Si te adjudicas la propiedad, se imputa al precio final.</p>
                    </div>
                @endunless
            </div>

            <div class="cuenta__columna">
                <div class="cuenta__bloque-titulo cuenta__bloque-titulo--lateral">
                    <div class="cuenta__kicker">{{ $estado['tituloLateral'] }}</div>
                </div>
                <p class="cuenta__lateral-texto">{{ $estado['textoLateral'] }}</p>
                <div class="cuenta__accesos">
                    <a href="{{ route('remates.index') }}" class="cuenta__acceso">Ver remates publicados <span>→</span></a>
                    <a href="{{ route('sala.show', 'apoquindo') }}" class="cuenta__acceso">Entrar a la sala de pujas <span>→</span></a>
                    <a href="#" class="cuenta__acceso">Bases generales de los remates <span>→</span></a>
                </div>

                <div class="cuenta__contacto">
                    <div class="cuenta__contacto-titulo">CONTACTO</div>
                    <p>Si tu solicitud lleva más de 24 horas hábiles sin respuesta, escríbenos indicando tu RUT y el remate.</p>
                    <div class="cuenta__contacto-enlaces">
                        <a href="mailto:remates@colliers.cl">remates@colliers.cl</a>
                        <a href="tel:+56227603535">+56 2 2760 3535</a>
                    </div>
                </div>
            </div>
        </div>

        <x-tramite.pie :ancho="1180" />
    </div>
</x-layouts.base>
