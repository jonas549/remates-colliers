@php
    use App\Support\Formato;

    $previa = $previa ?? $vista;
@endphp
<x-layouts.admin seccion="configuracion" :titulo="'Plantilla · ' . $catalogo['nombre']" clase-cuerpo="placeholder-claro">
    <div class="admin-gestion__migas"><a href="{{ route('admin.configuracion.seccion', 'plantillas') }}">← Plantillas de correo</a></div>

    <div class="admin-encabezado">
        <div class="admin-encabezado__texto">
            <div class="admin-encabezado__kicker">PLANTILLA DE CORREO</div>
            <h1 class="admin-encabezado__titulo admin-encabezado__titulo--con-bajada">{{ $catalogo['nombre'] }}</h1>
            <div class="admin-encabezado__bajada">Se envía {{ mb_strtolower($catalogo['cuando']) }} · Destinatario: {{ mb_strtolower($catalogo['destinatario']) }}.</div>
        </div>
        @if ($editada)
            <div class="admin-encabezado__acciones">
                <form method="POST" action="{{ route('admin.configuracion.plantilla.restaurar', $clave) }}" class="formulario-en-linea" onsubmit="return confirm('¿Volver al texto original? Se pierde lo editado.')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="admin-form__borrador">Restaurar la original</button>
                </form>
            </div>
        @endif
    </div>

    @include('admin.configuracion._submenu', ['actual' => 'plantillas'])

    <x-admin.avisos />

    <div class="admin-gestion">
        <div class="admin-gestion__columnas">
            <form method="POST" action="{{ route('admin.configuracion.plantilla.update', $clave) }}" class="admin-form admin-form--plano" id="plantilla">
                @csrf
                @method('PUT')
                <div class="admin-form__interior">
                    <x-admin.errores />
                    <div class="admin-form__grilla">
                        <label class="admin-form__campo admin-form__campo--ancho {{ $errors->has('asunto') ? 'es-invalido' : '' }}">
                            <span class="admin-form__etiqueta">ASUNTO</span>
                            <input name="asunto" value="{{ old('asunto', $plantilla['asunto']) }}" class="admin-form__input" maxlength="150" required>
                            <span class="admin-form__ayuda">Se envía como «{asunto} · Remates Colliers».</span>
                        </label>
                        <label class="admin-form__campo admin-form__campo--ancho {{ $errors->has('cuerpo') ? 'es-invalido' : '' }}">
                            <span class="admin-form__etiqueta">CUERPO</span>
                            <textarea name="cuerpo" class="admin-form__textarea admin-form__textarea--alta" maxlength="4000" required>{{ old('cuerpo', $plantilla['cuerpo']) }}</textarea>
                            <span class="admin-form__ayuda">Un párrafo por línea en blanco. El saludo («Hola {nombre}») y la firma los pone el sistema.</span>
                        </label>
                        @if ($catalogo['boton'] !== null)
                            <label class="admin-form__campo {{ $errors->has('boton') ? 'es-invalido' : '' }}">
                                <span class="admin-form__etiqueta">TEXTO DEL BOTÓN</span>
                                <input name="boton" value="{{ old('boton', $plantilla['boton']) }}" class="admin-form__input" maxlength="60">
                                <span class="admin-form__ayuda">El enlace lo pone el sistema: cambia según el remate, la cuenta o la garantía.</span>
                            </label>
                        @endif
                    </div>
                    <div class="admin-form__botones">
                        <button type="submit" class="admin-form__publicar">Guardar plantilla</button>
                        <button type="submit" class="admin-form__borrador" formaction="{{ route('admin.configuracion.plantilla.previa', $clave) }}">Ver la vista previa</button>
                    </div>
                </div>
            </form>

            <div class="admin-gestion__lateral">
                <div class="admin-gestion__bloque">
                    <div class="admin-seccion__cabeza"><h2 class="admin-seccion__titulo">Variables disponibles</h2></div>
                    <div class="admin-datos">
                        @foreach ($variables as $nombre => $detalle)
                            <div class="admin-ficha__dato"><span class="admin-ficha__k">&#123;&#123; {{ $nombre }} &#125;&#125;</span><span class="admin-ficha__v">{{ $detalle }}</span></div>
                        @endforeach
                    </div>
                    <p class="admin-gestion__nota">Se escriben entre llaves. Una variable que no exista se reemplaza por vacío.</p>
                </div>

                <div class="admin-gestion__bloque">
                    <div class="admin-seccion__cabeza"><h2 class="admin-seccion__titulo">Vista previa</h2></div>
                    <p class="admin-gestion__nota">Con datos de ejemplo. «Ver la vista previa» la actualiza con lo que escribiste, sin guardar.</p>
                    <div class="admin-previa">
                        <div class="admin-previa__asunto">{{ $previa['asunto'] }} · Remates Colliers</div>
                        <div class="admin-previa__cuerpo">
                            <p>Hola María Paz González</p>
                            @foreach ($previa['parrafos'] as $parrafo)
                                <p>{{ $parrafo }}</p>
                            @endforeach
                            @if ($previa['boton'])
                                <p><span class="admin-previa__boton">{{ $previa['boton'] }}</span></p>
                            @endif
                            <p class="admin-previa__firma">Colliers Chile · {{ \App\Support\Sitio::correo() }}</p>
                        </div>
                    </div>
                </div>

                @if ($editada)
                    <p class="admin-gestion__nota">Editada por {{ $editada->actualizadoPor?->name ?? 'administración' }} · {{ Formato::fechaCorta($editada->updated_at) }}.</p>
                @endif
            </div>
        </div>
    </div>
</x-layouts.admin>
