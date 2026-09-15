@php
    $documentos = [
        ['id' => 'ci-frente', 'nombre' => 'Cédula de identidad — frente', 'detalle' => 'JPG o PDF, máximo 5 MB'],
        ['id' => 'ci-dorso', 'nombre' => 'Cédula de identidad — dorso', 'detalle' => 'JPG o PDF, máximo 5 MB'],
        ['id' => 'domicilio', 'nombre' => 'Comprobante de domicilio', 'detalle' => 'Cuenta de servicios de los últimos 3 meses'],
        ['id' => 'poder', 'nombre' => 'Poder o mandato', 'detalle' => 'Solo si actúas en representación de un tercero o una empresa'],
    ];
    $pasos = [
        ['titulo' => 'Revisamos tus antecedentes', 'texto' => 'Un ejecutivo valida los documentos de forma manual.'],
        ['titulo' => 'Te avisamos por correo', 'texto' => 'Recibirás la aprobación o el rechazo con el detalle de lo que falte.'],
        ['titulo' => 'Constituyes la garantía', 'texto' => 'Vale a la vista o transferencia por cada remate en el que participes.'],
        ['titulo' => 'Pujas en la transmisión', 'texto' => 'Con la garantía aprobada quedas habilitado para ofertar en pesos.'],
    ];
@endphp
<x-layouts.base titulo="Registro de postor" clase-cuerpo="placeholder-claro">
    <div class="registro">

        <x-tramite.cabecera etiqueta="REMATES">
            <span class="tramite-cabecera__pregunta">¿Ya tienes cuenta?</span>
            <a href="{{ route('login') }}" class="tramite-cabecera__boton">Ingresar</a>
        </x-tramite.cabecera>

        <div class="registro__hero">
            <div class="registro__hero-interior">
                <div>
                    <div class="registro__kicker">REGISTRO DE POSTOR</div>
                    <h1 class="registro__titulo">Crea tu cuenta para participar en los remates</h1>
                </div>
                <p class="registro__bajada">Completa tus antecedentes y adjunta los documentos requeridos. La cuenta te habilita a seguir los remates; para pujar necesitas además una garantía aprobada por cada propiedad.</p>
            </div>
        </div>

        <div class="registro__cuerpo">

            <form class="registro__formulario" method="GET" action="{{ route('cuenta.estado') }}" x-data="registro">
                <div class="registro__tipo">
                    <button type="button" class="registro__tipo-opcion" :class="{ 'es-activa': !juridica }" @click="tipo = 'natural'">Persona natural</button>
                    <button type="button" class="registro__tipo-opcion" :class="{ 'es-activa': juridica }" @click="tipo = 'juridica'">Persona jurídica</button>
                </div>
                <input type="hidden" name="tipo" :value="tipo">

                <h2 class="registro__seccion"><span class="registro__seccion-num">01</span> Datos personales</h2>
                <div class="registro__grilla">
                    <label class="registro__campo"><span class="registro__etiqueta">NOMBRES</span><input name="nombres" placeholder="María Paz" class="registro__input" autocomplete="given-name"></label>
                    <label class="registro__campo"><span class="registro__etiqueta">APELLIDOS</span><input name="apellidos" placeholder="González Soto" class="registro__input" autocomplete="family-name"></label>
                    <label class="registro__campo"><span class="registro__etiqueta">RUT</span><input name="rut" x-model="rut" placeholder="12.345.678-9" class="registro__input" :class="{ 'es-invalido': estadoRut === false }"><span class="registro__ayuda" :class="{ 'es-invalido': estadoRut === false }" x-text="mensajeRut">Formato 12.345.678-9</span></label>
                    <label class="registro__campo"><span class="registro__etiqueta">FECHA DE NACIMIENTO</span><input type="date" name="fecha_nacimiento" class="registro__input"></label>
                    <label class="registro__campo"><span class="registro__etiqueta">NACIONALIDAD</span><input name="nacionalidad" placeholder="Chilena" class="registro__input"></label>
                    <label class="registro__campo"><span class="registro__etiqueta">ESTADO CIVIL</span>
                        <select name="estado_civil" class="registro__input">
                            <option>Soltero(a)</option>
                            <option>Casado(a)</option>
                            <option>Divorciado(a)</option>
                            <option>Viudo(a)</option>
                        </select>
                    </label>
                </div>

                <template x-if="juridica">
                    <div>
                        <h2 class="registro__seccion"><span class="registro__seccion-num">02</span> Datos de la empresa</h2>
                        <div class="registro__grilla">
                            <label class="registro__campo"><span class="registro__etiqueta">RAZÓN SOCIAL</span><input name="razon_social" placeholder="Inversiones Andes SpA" class="registro__input"></label>
                            <label class="registro__campo"><span class="registro__etiqueta">RUT DE LA EMPRESA</span><input name="rut_empresa" placeholder="76.543.210-K" class="registro__input"></label>
                            <label class="registro__campo"><span class="registro__etiqueta">GIRO</span><input name="giro" placeholder="Inversiones inmobiliarias" class="registro__input"></label>
                            <label class="registro__campo"><span class="registro__etiqueta">CALIDAD EN QUE ACTÚA</span>
                                <select name="calidad" class="registro__input">
                                    <option>Representante legal</option>
                                    <option>Mandatario con poder</option>
                                </select>
                            </label>
                        </div>
                    </div>
                </template>

                <h2 class="registro__seccion"><span class="registro__seccion-num" x-text="numero(2)">02</span> Contacto</h2>
                <div class="registro__grilla">
                    <label class="registro__campo"><span class="registro__etiqueta">CORREO ELECTRÓNICO</span><input type="email" name="email" placeholder="maria@correo.cl" class="registro__input" autocomplete="email"></label>
                    <label class="registro__campo"><span class="registro__etiqueta">TELÉFONO</span><input name="telefono" placeholder="+56 9 1234 5678" class="registro__input" autocomplete="tel"></label>
                    <label class="registro__campo"><span class="registro__etiqueta">DIRECCIÓN</span><input name="direccion" placeholder="Av. Providencia 1234, depto. 501" class="registro__input" autocomplete="street-address"></label>
                    <label class="registro__campo"><span class="registro__etiqueta">COMUNA</span><input name="comuna" placeholder="Providencia" class="registro__input"></label>
                    <label class="registro__campo"><span class="registro__etiqueta">REGIÓN</span>
                        <select name="region" class="registro__input">
                            <option>Metropolitana</option>
                            <option>Valparaíso</option>
                            <option>Biobío</option>
                            <option>La Araucanía</option>
                            <option>Otra</option>
                        </select>
                    </label>
                    <label class="registro__campo"><span class="registro__etiqueta">¿CÓMO NOS CONOCISTE?</span>
                        <select name="origen" class="registro__input">
                            <option>Sitio de Colliers</option>
                            <option>Publicación en prensa</option>
                            <option>Redes sociales</option>
                            <option>Recomendación</option>
                        </select>
                    </label>
                </div>

                <h2 class="registro__seccion"><span class="registro__seccion-num" x-text="numero(3)">03</span> Documentos requeridos</h2>
                <div class="registro__documentos">
                    @foreach ($documentos as $doc)
                        <div class="registro__documento">
                            <div class="registro__documento-marca" :class="{ 'es-cargado': cargados['{{ $doc['id'] }}'] }" x-text="cargados['{{ $doc['id'] }}'] ? '✓' : '+'">+</div>
                            <div class="registro__documento-texto">
                                <div class="registro__documento-nombre">{{ $doc['nombre'] }}</div>
                                <div class="registro__documento-detalle" x-text="cargados['{{ $doc['id'] }}'] ? 'Archivo cargado · pendiente de revisión' : @js($doc['detalle'])">{{ $doc['detalle'] }}</div>
                            </div>
                            <input type="file" name="documentos[{{ $doc['id'] }}]" accept=".jpg,.jpeg,.png,.pdf" class="registro__documento-archivo" x-ref="archivo-{{ $doc['id'] }}" @change="archivoElegido('{{ $doc['id'] }}', $event)">
                            <button type="button" class="registro__documento-boton" :class="{ 'es-cargado': cargados['{{ $doc['id'] }}'] }" @click="elegirArchivo('{{ $doc['id'] }}')" x-text="cargados['{{ $doc['id'] }}'] ? 'Reemplazar' : 'Adjuntar'">Adjuntar</button>
                        </div>
                    @endforeach
                </div>

                <h2 class="registro__seccion"><span class="registro__seccion-num" x-text="numero(4)">04</span> Clave de acceso</h2>
                <div class="registro__grilla">
                    <label class="registro__campo"><span class="registro__etiqueta">CONTRASEÑA</span><input type="password" name="password" placeholder="Mínimo 8 caracteres" class="registro__input" autocomplete="new-password"></label>
                    <label class="registro__campo"><span class="registro__etiqueta">REPETIR CONTRASEÑA</span><input type="password" name="password_confirmation" placeholder="Repite la contraseña" class="registro__input" autocomplete="new-password"></label>
                </div>

                <div class="registro__aviso">
                    <div class="registro__aviso-titulo">SUJETO A APROBACIÓN</div>
                    <p>Colliers revisa los antecedentes de forma manual y te informará el resultado por correo electrónico, normalmente dentro de 24 horas hábiles. Mientras tanto puedes revisar los remates publicados, pero no podrás pujar.</p>
                </div>

                <label class="registro__declaracion">
                    <input type="checkbox" name="acepta" x-model="acepta">
                    Declaro que los datos entregados son verídicos y acepto las bases generales de los remates, los términos de uso y la política de privacidad de Colliers.
                </label>

                <div class="registro__acciones">
                    <button type="submit" class="registro__enviar" :class="{ 'es-listo': acepta }">Enviar solicitud de registro</button>
                    <a href="{{ route('login') }}" class="registro__ya-tengo">Ya tengo cuenta</a>
                </div>
            </form>

            <div class="registro__lateral">
                <div class="registro__lateral-bloque">
                    <div class="registro__lateral-titulo">QUÉ SIGUE DESPUÉS</div>
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
                    <div class="registro__garantia-titulo">GARANTÍA FUERA DE LA PLATAFORMA</div>
                    <p>Se constituye por vale a la vista o transferencia por cada remate. Colliers revisa el comprobante y marca la inscripción como aprobada o rechazada. No hay pagos en línea en este sitio.</p>
                </div>

                <div class="registro__dudas">
                    <div class="registro__dudas-titulo">¿DUDAS CON EL REGISTRO?</div>
                    <div class="registro__dudas-enlaces">
                        <a href="mailto:remates@colliers.cl">remates@colliers.cl</a>
                        <a href="tel:+56227603535">+56 2 2760 3535</a>
                    </div>
                </div>
            </div>
        </div>

        <x-tramite.pie />
    </div>
</x-layouts.base>
