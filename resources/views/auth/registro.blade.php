@php
    $documentos = collect(\App\Actions\Fortify\CreateNewUser::DOCUMENTOS)->map(fn ($id) => [
        'id' => $id, 'nombre' => __("acceso.registro.documentos.{$id}.nombre"), 'detalle' => __("acceso.registro.documentos.{$id}.detalle"),
    ])->all();
    // Adjuntos que sobrevivieron a un intento fallido: el navegador no puede prellenar un <input type=file>,
    // así que los guarda el servidor (App\Http\Middleware\DocumentosDelRegistro) y aquí se muestran como ya cargados.
    $guardados = \App\Http\Middleware\DocumentosDelRegistro::guardados(request());
    $pasos = __('acceso.registro.pasos');
@endphp
<x-layouts.base :titulo="__('acceso.registro.pagina')" clase-cuerpo="placeholder-claro">
    <div class="registro">

        <x-tramite.cabecera :etiqueta="__('acceso.registro.etiqueta_cabecera')">
            <span class="tramite-cabecera__pregunta">{{ __('acceso.registro.ya_tienes_cuenta') }}</span>
            <a href="{{ route('login') }}" class="tramite-cabecera__boton">{{ __('acceso.comun.ingresar') }}</a>
        </x-tramite.cabecera>

        <div class="registro__hero">
            <div class="registro__hero-interior">
                <div>
                    <div class="registro__kicker">{{ __('acceso.registro.kicker') }}</div>
                    <h1 class="registro__titulo">{{ __('acceso.registro.titulo') }}</h1>
                </div>
                <p class="registro__bajada">{{ __('acceso.registro.bajada') }}</p>
            </div>
        </div>

        <div class="registro__cuerpo">

            <form class="registro__formulario" method="POST" action="{{ route('register.store') }}" enctype="multipart/form-data" x-data="registro(@js(old('tipo', 'natural')), @js(old('rut', '')), @js((bool) old('acepta')), @js(collect($guardados)->map(fn ($d) => $d['nombre'])->all()))">
                @csrf
                @if ($errors->any())
                    {{-- Al fallar la validación: qué falta, arriba y a la vista; el foco va al primer campo con error. --}}
                    <div class="registro__errores" role="alert" tabindex="-1" x-init="$nextTick(() => { $el.focus(); const malo = document.querySelector('.es-invalido')?.closest('label, .registro__documento'); malo?.scrollIntoView({ block: 'center' }); malo?.querySelector('input, select, textarea')?.focus(); })">
                        <strong>{{ __('acceso.registro.errores_titulo') }}</strong> {{ __('acceso.registro.errores_texto', ['documentos' => $guardados ? __('acceso.registro.errores_documentos') : '']) }}
                        <ul>
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
                <div class="registro__tipo">
                    <button type="button" class="registro__tipo-opcion" :class="{ 'es-activa': !juridica }" @click="tipo = 'natural'">{{ __('acceso.registro.persona_natural') }}</button>
                    <button type="button" class="registro__tipo-opcion" :class="{ 'es-activa': juridica }" @click="tipo = 'juridica'">{{ __('acceso.registro.persona_juridica') }}</button>
                </div>
                <input type="hidden" name="tipo" :value="tipo">

                <h2 class="registro__seccion"><span class="registro__seccion-num">01</span> {{ __('acceso.registro.seccion_personales') }}</h2>
                <div class="registro__grilla">
                    <label class="registro__campo"><span class="registro__etiqueta">{{ __('acceso.registro.nombres') }}</span><input name="nombres" placeholder="{{ __('acceso.registro.nombres_ejemplo') }}" value="{{ old('nombres') }}" class="registro__input" autocomplete="given-name">@error('nombres')<span class="registro__ayuda es-invalido">{{ $message }}</span>@enderror</label>
                    <label class="registro__campo"><span class="registro__etiqueta">{{ __('acceso.registro.apellidos') }}</span><input name="apellidos" placeholder="{{ __('acceso.registro.apellidos_ejemplo') }}" value="{{ old('apellidos') }}" class="registro__input" autocomplete="family-name">@error('apellidos')<span class="registro__ayuda es-invalido">{{ $message }}</span>@enderror</label>
                    <label class="registro__campo"><span class="registro__etiqueta">{{ __('acceso.registro.rut') }}</span><input name="rut" x-model="rut" placeholder="12.345.678-9" class="registro__input" :class="{ 'es-invalido': estadoRut === false }">@error('rut')<span class="registro__ayuda es-invalido">{{ $message }}</span>@else<span class="registro__ayuda" :class="{ 'es-invalido': estadoRut === false }" x-text="mensajeRut">{{ __('acceso.registro.formato_rut') }}</span>@enderror</label>
                    <label class="registro__campo"><span class="registro__etiqueta">{{ __('acceso.registro.fecha_nacimiento') }}</span><input type="date" name="fecha_nacimiento" value="{{ old('fecha_nacimiento') }}" class="registro__input">@error('fecha_nacimiento')<span class="registro__ayuda es-invalido">{{ $message }}</span>@enderror</label>
                    <label class="registro__campo"><span class="registro__etiqueta">{{ __('acceso.registro.nacionalidad') }}</span><input name="nacionalidad" placeholder="{{ __('acceso.registro.nacionalidad_ejemplo') }}" value="{{ old('nacionalidad') }}" class="registro__input">@error('nacionalidad')<span class="registro__ayuda es-invalido">{{ $message }}</span>@enderror</label>
                    <label class="registro__campo"><span class="registro__etiqueta">{{ __('acceso.registro.estado_civil') }}</span>
                        <select name="estado_civil" class="registro__input">
                            {{-- El valor se guarda en español (dato); solo la etiqueta va en el idioma de la página. --}}
                            @foreach (__('acceso.registro.estados_civiles') as $valor => $etiqueta)
                                <option value="{{ $valor }}" @selected(old('estado_civil') === $valor)>{{ $etiqueta }}</option>
                            @endforeach
                        </select>@error('estado_civil')<span class="registro__ayuda es-invalido">{{ $message }}</span>@enderror
                    </label>
                </div>

                <template x-if="juridica">
                    <div>
                        <h2 class="registro__seccion"><span class="registro__seccion-num">02</span> {{ __('acceso.registro.seccion_empresa') }}</h2>
                        <div class="registro__grilla">
                            <label class="registro__campo"><span class="registro__etiqueta">{{ __('acceso.registro.razon_social') }}</span><input name="razon_social" placeholder="{{ __('acceso.registro.razon_social_ejemplo') }}" value="{{ old('razon_social') }}" class="registro__input">@error('razon_social')<span class="registro__ayuda es-invalido">{{ $message }}</span>@enderror</label>
                            <label class="registro__campo"><span class="registro__etiqueta">{{ __('acceso.registro.rut_empresa') }}</span><input name="rut_empresa" placeholder="76.543.210-K" value="{{ old('rut_empresa') }}" class="registro__input">@error('rut_empresa')<span class="registro__ayuda es-invalido">{{ $message }}</span>@enderror</label>
                            <label class="registro__campo"><span class="registro__etiqueta">{{ __('acceso.registro.giro') }}</span><input name="giro" placeholder="{{ __('acceso.registro.giro_ejemplo') }}" value="{{ old('giro') }}" class="registro__input">@error('giro')<span class="registro__ayuda es-invalido">{{ $message }}</span>@enderror</label>
                            <label class="registro__campo"><span class="registro__etiqueta">{{ __('acceso.registro.calidad') }}</span>
                                <select name="calidad" class="registro__input">
                                    {{-- El valor se guarda en español (dato); solo la etiqueta va en el idioma de la página. --}}
                            @foreach (__('acceso.registro.calidades') as $valor => $etiqueta)
                                <option value="{{ $valor }}" @selected(old('calidad') === $valor)>{{ $etiqueta }}</option>
                            @endforeach
                                </select>@error('calidad')<span class="registro__ayuda es-invalido">{{ $message }}</span>@enderror
                            </label>
                        </div>
                    </div>
                </template>

                <h2 class="registro__seccion"><span class="registro__seccion-num" x-text="numero(2)">02</span> {{ __('acceso.registro.seccion_contacto') }}</h2>
                <div class="registro__grilla">
                    <label class="registro__campo"><span class="registro__etiqueta">{{ __('acceso.comun.correo_electronico') }}</span><input type="email" name="email" placeholder="{{ __('acceso.comun.correo_ejemplo') }}" value="{{ old('email') }}" class="registro__input" autocomplete="email">@error('email')<span class="registro__ayuda es-invalido">{{ $message }}</span>@enderror</label>
                    <label class="registro__campo"><span class="registro__etiqueta">{{ __('acceso.registro.telefono') }}</span><input name="telefono" placeholder="+56 9 1234 5678" value="{{ old('telefono') }}" class="registro__input" autocomplete="tel">@error('telefono')<span class="registro__ayuda es-invalido">{{ $message }}</span>@enderror</label>
                    <label class="registro__campo"><span class="registro__etiqueta">{{ __('acceso.registro.direccion') }}</span><input name="direccion" placeholder="{{ __('acceso.registro.direccion_ejemplo') }}" value="{{ old('direccion') }}" class="registro__input" autocomplete="street-address">@error('direccion')<span class="registro__ayuda es-invalido">{{ $message }}</span>@enderror</label>
                    <label class="registro__campo"><span class="registro__etiqueta">{{ __('acceso.registro.comuna') }}</span><input name="comuna" placeholder="Providencia" value="{{ old('comuna') }}" class="registro__input">@error('comuna')<span class="registro__ayuda es-invalido">{{ $message }}</span>@enderror</label>
                    <label class="registro__campo"><span class="registro__etiqueta">{{ __('acceso.registro.region') }}</span>
                        <select name="region" class="registro__input">
                            {{-- Las 16 regiones de norte a sur y «Otra / extranjero» (QA 22/10). Sin región elegida de antemano:
                                 con Arica primera, una opción por defecto habría dejado datos equivocados. Los nombres de las
                                 regiones no se traducen (decisión del 20/10); el valor guardado es el nombre corto en español. --}}
                            <option value="" disabled @selected(! old('region'))>{{ __('acceso.registro.region_elige') }}</option>
                            @foreach (\App\Support\RegionesChile::delRegistro() as $valor)
                                <option value="{{ $valor }}" @selected(old('region') === $valor)>{{ $valor === \App\Support\RegionesChile::OTRA ? __('acceso.registro.region_otra') : $valor }}</option>
                            @endforeach
                        </select>@error('region')<span class="registro__ayuda es-invalido">{{ $message }}</span>@enderror
                    </label>
                    <label class="registro__campo"><span class="registro__etiqueta">{{ __('acceso.registro.origen') }}</span>
                        <select name="origen" class="registro__input">
                            {{-- El valor se guarda en español (dato); solo la etiqueta va en el idioma de la página. --}}
                            @foreach (__('acceso.registro.origenes') as $valor => $etiqueta)
                                <option value="{{ $valor }}" @selected(old('origen') === $valor)>{{ $etiqueta }}</option>
                            @endforeach
                        </select>@error('origen')<span class="registro__ayuda es-invalido">{{ $message }}</span>@enderror
                    </label>
                </div>

                <h2 class="registro__seccion"><span class="registro__seccion-num" x-text="numero(3)">03</span> {{ __('acceso.registro.seccion_documentos') }}</h2>
                <div class="registro__documentos">
                    @foreach ($documentos as $doc)
                        <div class="registro__documento">
                            <div class="registro__documento-marca" :class="{ 'es-cargado': cargados['{{ $doc['id'] }}'] }" x-text="cargados['{{ $doc['id'] }}'] ? '✓' : '+'">{{ isset($guardados[$doc['id']]) ? '✓' : '+' }}</div>
                            <div class="registro__documento-texto">
                                <div class="registro__documento-nombre">{{ $doc['nombre'] }}</div>
                                <div class="registro__documento-detalle" x-text="detalle('{{ $doc['id'] }}', @js($doc['detalle']))">{{ isset($guardados[$doc['id']]) ? __('acceso.registro.ya_adjuntado', ['nombre' => $guardados[$doc['id']]['nombre']]) : $doc['detalle'] }}</div>
                                @error('documentos.' . $doc['id'])<span class="registro__ayuda es-invalido">{{ $message }}</span>@enderror
                            </div>
                            <input type="file" name="documentos[{{ $doc['id'] }}]" accept=".jpg,.jpeg,.png,.pdf" class="registro__documento-archivo" x-ref="archivo-{{ $doc['id'] }}" @change="archivoElegido('{{ $doc['id'] }}', $event)">
                            <button type="button" class="registro__documento-boton" :class="{ 'es-cargado': cargados['{{ $doc['id'] }}'] }" @click="elegirArchivo('{{ $doc['id'] }}')" x-text="cargados['{{ $doc['id'] }}'] ? @js(__('acceso.registro.reemplazar')) : @js(__('acceso.registro.adjuntar'))">{{ isset($guardados[$doc['id']]) ? __('acceso.registro.reemplazar') : __('acceso.registro.adjuntar') }}</button>
                        </div>
                    @endforeach
                </div>

                <h2 class="registro__seccion"><span class="registro__seccion-num" x-text="numero(4)">04</span> {{ __('acceso.registro.seccion_clave') }}</h2>
                <div class="registro__grilla">
                    <label class="registro__campo"><span class="registro__etiqueta">{{ __('acceso.comun.contrasena') }}</span><input type="password" name="password" placeholder="{{ __('acceso.comun.minimo_8') }}" class="registro__input" autocomplete="new-password">@error('password')<span class="registro__ayuda es-invalido">{{ $message }}</span>@enderror</label>
                    <label class="registro__campo"><span class="registro__etiqueta">{{ __('acceso.comun.repetir_contrasena') }}</span><input type="password" name="password_confirmation" placeholder="{{ __('acceso.comun.repite_contrasena') }}" class="registro__input" autocomplete="new-password"></label>
                </div>

                <div class="registro__aviso">
                    <div class="registro__aviso-titulo">{{ __('acceso.registro.aviso_titulo') }}</div>
                    <p>{{ __('acceso.registro.aviso_texto') }}</p>
                </div>

                <label class="registro__declaracion">
                    <input type="checkbox" name="acepta" value="1" x-model="acepta">
                    {{ __('acceso.registro.declaracion') }}
                </label>
                @error('acepta')<span class="registro__ayuda es-invalido">{{ $message }}</span>@enderror

                {{-- Captcha (22/10): reCAPTCHA v2, solo con sus claves en el .env; sin ellas el registro funciona igual. --}}
                @if (\App\Support\Recaptcha::activo())
                    <div class="registro__captcha">
                        <div class="g-recaptcha" data-sitekey="{{ \App\Support\Recaptcha::claveSitio() }}" data-theme="light"></div>
                        @error('captcha')<span class="registro__ayuda es-invalido">{{ $message }}</span>@enderror
                    </div>
                    <script src="{{ \App\Support\Recaptcha::script() }}" async defer></script>
                @endif

                <div class="registro__acciones">
                    <button type="submit" class="registro__enviar" :class="{ 'es-listo': acepta }">{{ __('acceso.registro.enviar') }}</button>
                    <a href="{{ route('login') }}" class="registro__ya-tengo">{{ __('acceso.registro.ya_tengo_cuenta') }}</a>
                </div>
            </form>

            <div class="registro__lateral">
                <div class="registro__lateral-bloque">
                    <div class="registro__lateral-titulo">{{ __('acceso.registro.que_sigue') }}</div>
                    @foreach ($pasos as $i => $paso)
                        <div class="registro__paso">
                            <div class="registro__paso-num">{{ str_pad($i + 1, 2, '0', STR_PAD_LEFT) }}</div>
                            <div class="registro__paso-cuerpo">
                                <div class="registro__paso-titulo">{{ $paso['titulo'] }}</div>
                                <div class="registro__paso-texto">{{ $paso['texto'] }}</div>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="registro__garantia">
                    <div class="registro__garantia-titulo">{{ __('acceso.registro.garantia_titulo') }}</div>
                    <p>{{ __('acceso.registro.garantia_texto') }}</p>
                </div>

                <div class="registro__dudas">
                    <div class="registro__dudas-titulo">{{ __('acceso.registro.dudas') }}</div>
                    <div class="registro__dudas-enlaces">
                        <a href="mailto:{{ \App\Support\Sitio::correo() }}">{{ \App\Support\Sitio::correo() }}</a>
                        <a href="{{ \App\Support\Sitio::telefonoEnlace() }}">{{ \App\Support\Sitio::telefono() }}</a>
                    </div>
                </div>
            </div>
        </div>

        <x-tramite.pie />
    </div>
</x-layouts.base>
