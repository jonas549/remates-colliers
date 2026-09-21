{{--
    Postores de una subasta de prueba (21/09). Quien prueba es una persona: la clave va al lado de cada correo, con
    botón de copiar, y cada postor tiene su enlace para ingresar con el correo ya escrito. La clave no se guarda en
    claro: se ve solo recién creada o regenerada.
--}}
<section class="admin-pruebas admin-pruebas--credenciales" id="postores-prueba" data-postores-prueba>
    <h2 class="admin-pruebas__titulo">Postores de prueba</h2>

    @if ($clave)
        <div class="admin-pruebas__alerta" role="alert">
            <strong>La clave solo se muestra esta vez.</strong> Cópiala antes de salir de esta página: no queda guardada en
            ninguna parte. Si la pierdes, vuelve a esta ficha y usa «Generar clave nueva».
        </div>
    @else
        <p class="admin-pruebas__texto">
            La clave se mostró una sola vez, al crear la subasta. Si no la tienes, genera una nueva: vale para los
            {{ count($postores) }} postores de este remate y la anterior deja de servir.
        </p>
    @endif

    <ol class="admin-pruebas__pasos">
        <li>Usa «Ingresar como este postor»: abre el acceso en otra pestaña con el correo ya escrito.</li>
        <li>Pega la clave y entra. En «Mi cuenta» aparece este remate con la garantía aprobada.</li>
        <li>Desde «Mi cuenta», «Entrar a la sala de pujas» lleva a la sala para pujar.</li>
    </ol>
    <p class="admin-pruebas__texto">
        El navegador guarda una sola sesión: si entras como postor aquí mismo, se cierra la de administración. Para ver el
        panel y la sala a la vez, abre el enlace en una ventana privada o en otro navegador.
    </p>

    {{-- Martillero a cargo (21/09): se prueba igual que los postores, desde esta misma pantalla. --}}
    <h3 class="admin-pruebas__subtitulo">Martillero a cargo</h3>
    @if ($remate->martillero)
        <div class="admin-pruebas__postores admin-pruebas__postores--martillero">
            <div class="admin-pruebas__fila" data-martillero-prueba-fila>
                <span class="admin-pruebas__dato admin-pruebas__dato--postor">
                    <span class="admin-pruebas__etiqueta">MARTILLERO</span>
                    <strong data-martillero-nombre>{{ $remate->martillero->name }}</strong>
                </span>
                <span class="admin-pruebas__dato">
                    <span class="admin-pruebas__etiqueta">CORREO</span>
                    <code class="admin-pruebas__valor" data-martillero-correo>{{ $remate->martillero->email }}</code>
                    <button type="button" class="boton-copiar" x-data="copiar(@js($remate->martillero->email))" @click="copiar"
                            x-text="copiado ? 'Copiado ✓' : 'Copiar'" aria-label="Copiar el correo del martillero">Copiar</button>
                </span>
                <span class="admin-pruebas__dato">
                    <span class="admin-pruebas__etiqueta">CLAVE</span>
                    <span class="admin-pruebas__sin-clave">La suya. Si no la tiene: <a href="{{ route('admin.usuarios') }}">Restablecer clave en «06 Usuarios»</a></span>
                </span>
                <span class="admin-pruebas__dato admin-pruebas__dato--accion">
                    <a href="{{ route('ingresar.como', ['usuario' => $remate->martillero->email, 'portal' => 'admin']) }}" target="_blank" rel="noopener"
                       class="admin-boton-borde">Ingresar como este martillero</a>
                </span>
            </div>
        </div>
        <p class="admin-pruebas__texto">Al entrar, el martillero ve este remate en su inicio, con «Abrir panel en vivo».</p>
    @else
        <p class="admin-pruebas__texto" data-sin-martillero-ficha>
            Esta subasta no tiene martillero. Asígnalo en «Datos y condiciones», más abajo:
            <a href="#datos-y-condiciones">ir a Martillero →</a>
        </p>
    @endif

    <h3 class="admin-pruebas__subtitulo">Postores</h3>
    <div class="admin-pruebas__postores">
        <div class="admin-pruebas__fila admin-pruebas__fila--cabeza" aria-hidden="true">
            <span>POSTOR</span><span>CORREO</span><span>CLAVE</span><span></span>
        </div>
        @foreach ($postores as $postor)
            <div class="admin-pruebas__fila" data-postor-prueba>
                <span class="admin-pruebas__dato admin-pruebas__dato--postor">
                    <span class="admin-pruebas__etiqueta">POSTOR</span>
                    <strong>Postor {{ $postor['numero'] }}</strong>
                    <span class="admin-pruebas__rut">RUT {{ \App\Support\Rut::formatear($postor['rut']) }}</span>
                </span>
                <span class="admin-pruebas__dato">
                    <span class="admin-pruebas__etiqueta">CORREO</span>
                    <code class="admin-pruebas__valor" data-correo>{{ $postor['email'] }}</code>
                    <button type="button" class="boton-copiar" x-data="copiar(@js($postor['email']))" @click="copiar"
                            x-text="copiado ? 'Copiado ✓' : 'Copiar'" aria-label="Copiar el correo del postor {{ $postor['numero'] }}">Copiar</button>
                </span>
                <span class="admin-pruebas__dato">
                    <span class="admin-pruebas__etiqueta">CLAVE</span>
                    @if ($clave)
                        <code class="admin-pruebas__valor admin-pruebas__valor--clave" data-clave>{{ $clave }}</code>
                        <button type="button" class="boton-copiar" x-data="copiar(@js($clave))" @click="copiar"
                                x-text="copiado ? 'Copiado ✓' : 'Copiar'" aria-label="Copiar la clave del postor {{ $postor['numero'] }}">Copiar</button>
                    @else
                        <span class="admin-pruebas__sin-clave">Ya no se muestra</span>
                    @endif
                </span>
                <span class="admin-pruebas__dato admin-pruebas__dato--accion">
                    <a href="{{ route('ingresar.como', ['usuario' => $postor['email']]) }}" target="_blank" rel="noopener"
                       class="admin-boton-borde">Ingresar como este postor</a>
                </span>
            </div>
        @endforeach
    </div>

    <form method="POST" action="{{ route('admin.remates.prueba-clave', $remate) }}" class="admin-pruebas__acciones"
          onsubmit="return confirm('¿Generar una clave nueva? La actual deja de servir y se cierran las sesiones de estos postores.')">
        @csrf
        <button type="submit" class="{{ $clave ? 'admin-accion' : 'admin-boton-borde' }}">Generar clave nueva</button>
    </form>
</section>
