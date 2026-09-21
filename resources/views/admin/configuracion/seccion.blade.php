@php
    use App\Models\Configuracion;
    use App\Support\Formato;

    $datos = Configuracion::SECCIONES[$seccion];
@endphp
<x-layouts.admin seccion="configuracion" :subseccion="$seccion" :titulo="'Configuración · ' . $datos['titulo']" clase-cuerpo="placeholder-claro">
    <div class="admin-encabezado">
        <div class="admin-encabezado__texto">
            <div class="admin-encabezado__kicker">CONFIGURACIÓN</div>
            <h1 class="admin-encabezado__titulo admin-encabezado__titulo--con-bajada">{{ $datos['titulo'] }}</h1>
            <div class="admin-encabezado__bajada">{{ $datos['bajada'] }} Lo que cambies rige desde la próxima acción, sin desplegar.</div>
        </div>
    </div>

    <x-admin.avisos />

    @php
        // Margen de liquidación bajo lo seguro (21/09): la puja reintenta la conexión y tiene que alcanzar el lote
        // antes de T + margen (App\Subastas\ConexionPuja).
        $margenSeguro = \App\Subastas\ConexionPuja::margenSeguroSegundos();
        $margenActual = $seccion === 'remates' ? (int) $valores['margen_liquidacion_segundos'] : null;
    @endphp
    @if ($margenActual !== null && $margenActual < $margenSeguro)
        <div class="admin-avisos"><div class="admin-aviso admin-aviso--error" role="alert" data-aviso-margen>
            El margen de liquidación está en {{ $margenActual }} s, por debajo de los {{ $margenSeguro }} s seguros. Si la base de datos se sobrecarga durante un remate, una puja recibida a tiempo puede reintentar la conexión y no alcanzar el lote antes de que se adjudique: se rechazaría como «lote cerrado» siendo válida. Súbelo a {{ $margenSeguro }} s o más.
        </div></div>
    @endif

    <div class="admin-gestion">
        <form method="POST" action="{{ route('admin.configuracion.update', $seccion) }}" class="admin-form admin-form--plano"
              @if ($margenActual !== null)
                  x-data="{ margen: {{ (int) old('config.margen_liquidacion_segundos', $margenActual) }}, guardado: {{ $margenActual }}, seguro: {{ $margenSeguro }} }"
                  @input="if ($event.target.name === 'config[margen_liquidacion_segundos]') margen = parseInt($event.target.value, 10) || 0"
              @endif>
            @csrf
            @method('PUT')
            <div class="admin-form__interior">
                <x-admin.errores />
                @include('admin.configuracion._campos')
                @if ($margenActual !== null)
                    <label class="admin-form__campo admin-form__check admin-form__campo--ancho {{ $errors->has('config.margen_liquidacion_segundos') ? 'es-invalido' : '' }}"
                           x-show="margen < seguro && margen !== guardado" x-cloak data-confirmar-margen>
                        <input type="checkbox" name="confirmar_margen" value="1" @checked(old('confirmar_margen'))>
                        <span>Confirmo un margen de liquidación menor a {{ $margenSeguro }} s
                            <span class="admin-form__ayuda" style="display: block">Durante una sobrecarga de la base de datos, una puja recibida antes del cierre podría rechazarse: la puja reintenta la conexión hasta {{ number_format(config('colliers.conexion_puja.reintento_ms') / 1000, 1, ',', '') }} s y el lote se adjudica al terminar el margen. Úsalo solo si el servidor tiene OPcache y la latencia medida lo permite.</span>
                        </span>
                    </label>
                @endif
                <div class="admin-form__botones">
                    <button type="submit" class="admin-form__publicar">Guardar cambios</button>
                </div>
            </div>
        </form>

        @if ($seccion === 'correo')
            @php($avisoRemitente = $valores['correo_modo'] === 'smtp'
                ? \App\Correo\DiagnosticoSmtp::avisoRemitente($valores['correo_remitente'] ?: config('mail.from.address'), $valores['smtp_usuario'])
                : null)
            @if ($avisoRemitente)
                <div class="admin-avisos"><div class="admin-aviso admin-aviso--error" role="alert">{{ $avisoRemitente }}</div></div>
            @endif

            <div class="admin-gestion__bloque" x-data="{ prueba: false }">
                <div class="admin-seccion__cabeza"><h2 class="admin-seccion__titulo">Probar el correo saliente</h2></div>
                <p class="admin-gestion__nota">Guarda primero los cambios: las dos pruebas usan lo que está guardado. «Probar conexión» no envía nada; solo comprueba que el servidor responde y acepta las credenciales. El correo de prueba dice lo que respondió el servidor: que lo acepte no garantiza que llegue a la bandeja.</p>
                <div class="admin-acciones admin-acciones--bloque">
                    <form method="POST" action="{{ route('admin.configuracion.probar-conexion') }}" class="formulario-en-linea">
                        @csrf
                        <button type="submit" class="admin-form__borrador">Probar conexión</button>
                    </form>
                    <button type="button" class="admin-form__borrador" @click="prueba = !prueba" x-text="prueba ? 'Cancelar' : 'Enviar correo de prueba'">Enviar correo de prueba</button>
                </div>
                <form method="POST" action="{{ route('admin.configuracion.probar-correo') }}" class="admin-form admin-form--plano" x-show="prueba" x-cloak>
                    @csrf
                    <div class="admin-form__interior">
                        <div class="admin-form__grilla">
                            <label class="admin-form__campo {{ $errors->has('destino') ? 'es-invalido' : '' }}"><span class="admin-form__etiqueta">ENVIAR PRUEBA A</span>
                                <input type="email" name="destino" value="{{ old('destino', auth()->user()->email) }}" class="admin-form__input" required>
                                <span class="admin-form__ayuda">Se envía en el momento, sin pasar por la cola.</span>
                            </label>
                        </div>
                        <div class="admin-form__botones admin-form__botones--compactos"><button type="submit" class="admin-form__publicar">Enviar ahora</button></div>
                    </div>
                </form>
            </div>
        @endif

        @if ($seccion === 'sitio')
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
        @endif
    </div>
</x-layouts.admin>
