{{--
    Usuarios internos del panel (QA del 20/09, punto 2). Sin diseño de Ángel: se arma con los componentes del panel.
    Administradores y martilleros; los postores están en «03 Postores».
--}}
<x-layouts.admin seccion="usuarios" titulo="Usuarios" clase-cuerpo="placeholder-claro">
    <div x-data="adminUsuarios(@js(['usuarios' => $usuarios]))" @keydown.escape.window="editando = null; form = false">
        <div class="admin-encabezado">
            <div class="admin-encabezado__texto">
                <div class="admin-encabezado__kicker">USUARIOS INTERNOS</div>
                <h1 class="admin-encabezado__titulo admin-encabezado__titulo--con-bajada">Quién entra al panel</h1>
                <div class="admin-encabezado__bajada">Administradores y martilleros. Las cuentas no se borran: se desactivan, porque quedan remates y bitácora a su nombre. Los postores se administran en «Postores».</div>
            </div>
            <div class="admin-encabezado__acciones">
                <button type="button" class="admin-boton-toggle" :class="{ 'es-abierto': form }" @click="form = !form; editando = null"
                    x-text="form ? 'Cerrar formulario' : 'Crear usuario'">Crear usuario</button>
            </div>
        </div>

        <x-admin.avisos />

        @if (session('clave_temporal'))
            @php($temporal = session('clave_temporal'))
            <div class="admin-pruebas admin-pruebas--credenciales">
                <div class="admin-pruebas__texto">
                    <strong>Clave temporal de {{ $temporal['email'] }}</strong>, que <strong>solo se muestra ahora</strong>:
                    <code>{{ $temporal['clave'] }}</code>. Entrégasela por un canal seguro: al ingresar por
                    <code>/admin/ingresar</code> se le pedirá cambiarla.
                </div>
            </div>
        @endif

        @if ($errors->any())
            <div class="admin-gestion__bloque"><x-admin.errores /></div>
        @endif

        <template x-if="form">
            <div class="admin-form">
                <form class="admin-form__interior" method="POST" action="{{ route('admin.usuarios.store') }}">
                    @csrf
                    <div class="admin-form__cabeza">
                        <h2 class="admin-form__titulo">Crear usuario interno</h2>
                        <button type="button" class="admin-form__cerrar" @click="form = false">CERRAR ✕</button>
                    </div>
                    <div class="admin-form__grilla">
                        <label class="admin-form__campo"><span class="admin-form__etiqueta">NOMBRE</span>
                            <input name="name" value="{{ old('name') }}" class="admin-form__input" maxlength="120" required>
                        </label>
                        <label class="admin-form__campo"><span class="admin-form__etiqueta">CORREO</span>
                            <input type="email" name="email" value="{{ old('email') }}" class="admin-form__input" required>
                            <span class="admin-form__ayuda">Con este correo ingresa por /admin/ingresar.</span>
                        </label>
                        <label class="admin-form__campo"><span class="admin-form__etiqueta">ROL</span>
                            <select name="rol" class="admin-form__input" required>
                                <option value="{{ \App\Models\User::ROL_MARTILLERO }}" @selected(old('rol') === \App\Models\User::ROL_MARTILLERO)>Martillero/a</option>
                                <option value="{{ \App\Models\User::ROL_ADMIN }}" @selected(old('rol') === \App\Models\User::ROL_ADMIN)>Administración</option>
                            </select>
                            <span class="admin-form__ayuda">El martillero ve sus remates y su panel en vivo. La administración ve todo, incluidos los datos de los postores.</span>
                        </label>
                    </div>
                    <div class="admin-form__botones">
                        <button type="submit" class="admin-form__publicar">Crear usuario</button>
                    </div>
                    <div class="admin-form__aviso">La clave se genera sola y se muestra una sola vez; el usuario la cambia en su primer ingreso.</div>
                </form>
            </div>
        </template>

        <div class="admin-subastas__tabla">
            <table class="admin-tabla">
                <thead>
                    <tr><th>USUARIO</th><th>ROL</th><th>ESTADO</th><th>REMATES</th><th>ÚLTIMO INGRESO</th><th class="col-acciones">ACCIONES</th></tr>
                </thead>
                <tbody>
                    <template x-for="u in usuarios" :key="u.id">
                        <tr>
                            <td class="es-primera">
                                <div class="admin-tabla__principal"><span x-text="u.nombre"></span> <span class="admin-tabla__secundario" x-show="u.esTu">(tú)</span></div>
                                <div class="admin-tabla__secundario" x-text="u.email"></div>
                            </td>
                            <td x-text="u.rolTexto"></td>
                            <td>
                                <span class="badge-admin" :class="u.activo ? 'badge-admin--vivo' : 'badge-admin--cerrado'" x-text="u.estadoTexto.toUpperCase()"></span>
                                <div class="admin-tabla__secundario" x-show="u.bloqueado">Bloqueado por intentos fallidos</div>
                                <div class="admin-tabla__secundario" x-show="u.debeCambiarClave">Debe cambiar su clave</div>
                            </td>
                            <td class="es-num" x-text="u.remates"></td>
                            <td x-text="u.ultimoIngreso || 'Nunca'"></td>
                            <td class="col-acciones">
                                <div class="admin-acciones">
                                    <button type="button" class="admin-accion" @click="editando = editando === u.id ? null : u.id">Editar</button>
                                    <form method="POST" :action="u.urls.estado" class="formulario-en-linea"
                                        @submit="if (u.activo && !confirm('¿Quitarle el acceso al panel a ' + u.nombre + '?')) $event.preventDefault()">
                                        @csrf
                                        <input type="hidden" name="activar" :value="u.activo ? 0 : 1">
                                        <button type="submit" class="admin-accion" :class="u.activo ? 'admin-accion--peligro' : ''"
                                            x-text="u.activo ? 'Desactivar' : 'Activar'"></button>
                                    </form>
                                    <button type="button" class="admin-accion" @click="restablecer(u)" :disabled="u.esTu || enviando">Restablecer clave</button>
                                    <button type="button" class="admin-accion" @click="desbloquear(u)" x-show="u.bloqueado">Desbloquear</button>
                                </div>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
            <template x-if="usuarios.length === 0">
                <div class="admin-postores__vacio">No hay usuarios internos todavía.</div>
            </template>
        </div>

        {{-- Edición: una caja por usuario, con los mismos campos del alta. --}}
        <template x-for="u in usuarios" :key="'editar-' + u.id">
            <template x-if="editando === u.id">
                <div class="admin-form">
                    <form class="admin-form__interior" method="POST" :action="u.urls.actualizar">
                        @csrf
                        @method('PUT')
                        <div class="admin-form__cabeza">
                            <h2 class="admin-form__titulo">Editar <span x-text="u.nombre"></span></h2>
                            <button type="button" class="admin-form__cerrar" @click="editando = null">CERRAR ✕</button>
                        </div>
                        <div class="admin-form__grilla">
                            <label class="admin-form__campo"><span class="admin-form__etiqueta">NOMBRE</span>
                                <input name="name" :value="u.nombre" class="admin-form__input" maxlength="120" required>
                            </label>
                            <label class="admin-form__campo"><span class="admin-form__etiqueta">CORREO</span>
                                <input type="email" name="email" :value="u.email" class="admin-form__input" required>
                            </label>
                            <label class="admin-form__campo"><span class="admin-form__etiqueta">ROL</span>
                                <select name="rol" class="admin-form__input" :disabled="u.esTu" required>
                                    <option value="{{ \App\Models\User::ROL_MARTILLERO }}" :selected="u.rol === 'martillero'">Martillero/a</option>
                                    <option value="{{ \App\Models\User::ROL_ADMIN }}" :selected="u.rol === 'admin'">Administración</option>
                                </select>
                                <span class="admin-form__ayuda" x-show="u.esTu">Tu propio rol lo cambia otro administrador.</span>
                            </label>
                        </div>
                        <div class="admin-form__botones">
                            <button type="submit" class="admin-form__publicar">Guardar cambios</button>
                            <button type="button" class="admin-form__borrador" @click="cerrarSesiones(u)">Cerrar sus sesiones</button>
                        </div>
                    </form>
                </div>
            </template>
        </template>
    </div>
</x-layouts.admin>
