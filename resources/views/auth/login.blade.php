<x-layouts.base titulo="Ingresar" clase-cuerpo="placeholder-claro">
    <div class="acceso">

        <div class="acceso__columna">
            <div class="acceso__cabecera">
                <a href="{{ route('remates.index') }}"><img src="{{ asset('img/colliers-logo.png') }}" alt="Colliers" class="acceso__logo"></a>
            </div>

            <div class="acceso__contenido" x-data="acceso">
                <div class="acceso__kicker">
                    <span class="acceso__kicker-num">01</span>
                    <span class="acceso__kicker-linea"></span>
                    <span class="acceso__kicker-texto">ACCESO DE POSTORES</span>
                </div>

                <h1 class="acceso__titulo">Ingresa a tu cuenta</h1>
                <p class="acceso__intro">Sigue los remates publicados, revisa el estado de tus garantías y entra a la sala de pujas cuando comience la transmisión.</p>

                <template x-if="error">
                    <div class="acceso__error">Usuario o contraseña incorrectos. Te quedan 4 intentos antes de que bloqueemos la cuenta por seguridad.</div>
                </template>

                <form method="GET" action="{{ route('cuenta.estado') }}" @submit="enviar">
                    <div class="acceso__campos">
                        <label class="acceso__campo">
                            <span class="acceso__etiqueta">CORREO O RUT</span>
                            <input name="usuario" x-model="usuario" @input="error = false" placeholder="maria@correo.cl" class="acceso__input" autocomplete="username">
                        </label>
                        <label class="acceso__campo acceso__campo--clave">
                            <span class="acceso__etiqueta">CONTRASEÑA</span>
                            <input name="clave" :type="ver ? 'text' : 'password'" type="password" x-model="clave" @input="error = false" placeholder="Tu contraseña" class="acceso__input" autocomplete="current-password">
                            <button type="button" class="acceso__ver-clave" @click="alternarClave" x-text="ver ? 'OCULTAR' : 'MOSTRAR'">MOSTRAR</button>
                        </label>
                    </div>

                    <div class="acceso__opciones">
                        <label class="acceso__recordar">
                            <input type="checkbox" name="recordar" x-model="recordar" checked>
                            Mantener sesión iniciada
                        </label>
                        <a href="#" class="acceso__olvide">Olvidé mi contraseña</a>
                    </div>

                    <button type="submit" class="acceso__entrar" :class="{ 'es-listo': listo }">Ingresar</button>
                </form>

                <div class="acceso__separador">
                    <span class="acceso__separador-linea"></span>
                    <span class="acceso__separador-texto">O REGÍSTRATE</span>
                    <span class="acceso__separador-linea"></span>
                </div>

                <a href="{{ route('registro') }}" class="acceso__registro">Crear cuenta de postor</a>
                <p class="acceso__nota">El registro queda sujeto a aprobación de Colliers. La garantía se constituye por vale a la vista o transferencia, fuera de la plataforma.</p>

                <div class="acceso__contacto">
                    <a href="mailto:remates@colliers.cl">remates@colliers.cl</a>
                    <a href="tel:+56227603535">+56 2 2760 3535</a>
                    <a href="{{ route('admin.dashboard') }}" class="acceso__contacto-admin">Acceso administradores</a>
                </div>
            </div>
        </div>

        <div class="acceso__foto">
            <x-imagen-slot :src="asset('img/demo/' . $proximo['foto'])" />
            <div class="acceso__foto-velo"></div>
            <div class="acceso__foto-contenido">
                <div class="acceso__foto-chip">PRÓXIMO REMATE</div>
                <div>
                    <div class="acceso__foto-titulo">{{ $proximo['direccion'] }}</div>
                    <div class="acceso__foto-datos">
                        <div class="acceso__foto-dato">
                            <div class="acceso__foto-dato-etiqueta">PRECIO BASE</div>
                            <div class="acceso__foto-dato-valor">{{ \App\Demo\RematesDemo::clp($proximo['precio']) }}</div>
                        </div>
                        <div class="acceso__foto-dato">
                            <div class="acceso__foto-dato-etiqueta">SUPERFICIE</div>
                            <div class="acceso__foto-dato-valor">{{ $proximo['sup'] }} m²</div>
                        </div>
                        <div class="acceso__foto-dato">
                            <div class="acceso__foto-dato-etiqueta">REMATE</div>
                            <div class="acceso__foto-dato-valor">{{ $proximo['fechaCorta'] }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-layouts.base>
