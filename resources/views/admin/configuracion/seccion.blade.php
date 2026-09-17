@php
    use App\Models\Configuracion;
    use App\Support\Formato;

    $datos = Configuracion::SECCIONES[$seccion];
@endphp
<x-layouts.admin seccion="configuracion" :titulo="'Configuración · ' . $datos['titulo']" clase-cuerpo="placeholder-claro">
    <div class="admin-encabezado">
        <div class="admin-encabezado__texto">
            <div class="admin-encabezado__kicker">CONFIGURACIÓN</div>
            <h1 class="admin-encabezado__titulo admin-encabezado__titulo--con-bajada">{{ $datos['titulo'] }}</h1>
            <div class="admin-encabezado__bajada">{{ $datos['bajada'] }} Lo que cambies rige desde la próxima acción, sin desplegar.</div>
        </div>
    </div>

    @include('admin.configuracion._submenu', ['actual' => $seccion])

    <x-admin.avisos />

    <div class="admin-gestion">
        <form method="POST" action="{{ route('admin.configuracion.update', $seccion) }}" class="admin-form admin-form--plano">
            @csrf
            @method('PUT')
            <div class="admin-form__interior">
                <x-admin.errores />
                @include('admin.configuracion._campos')
                <div class="admin-form__botones">
                    <button type="submit" class="admin-form__publicar">Guardar cambios</button>
                </div>
            </div>
        </form>

        @if ($seccion === 'correo')
            <div class="admin-gestion__bloque" x-data="{ prueba: false }">
                <div class="admin-seccion__cabeza"><h2 class="admin-seccion__titulo">Probar el correo saliente</h2></div>
                <p class="admin-gestion__nota">Guarda primero los cambios: las dos pruebas usan lo que está guardado. «Probar conexión» no envía nada; solo comprueba que el servidor responde y acepta las credenciales.</p>
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
