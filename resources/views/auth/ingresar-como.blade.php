{{-- Pantalla sin diseño propio: reutiliza el diseño del Login (decisión del 16/09). --}}
<x-acceso.marco
    pagina="Ingresar como otro usuario"
    kicker="YA TIENES UNA SESIÓN ABIERTA"
    titulo="Ingresar como {{ $usuario }}"
    :intro="'En este navegador tienes abierta la sesión de ' . $actual->name . ' (' . $actual->email . '). El navegador guarda una sola sesión: para entrar como ' . $usuario . ' hay que cerrar la actual.'"
    :proximo="$proximo"
>
    <form method="POST" action="{{ route('ingresar.como.cambiar') }}">
        @csrf
        <input type="hidden" name="usuario" value="{{ $usuario }}">
        <button type="submit" class="acceso__entrar es-listo">Cerrar esta sesión e ingresar como {{ $usuario }}</button>
    </form>

    <p class="acceso__nota">
        ¿Quieres mantener abierta la sesión de {{ $actual->name }}? Copia este enlace y ábrelo en una ventana privada o en
        otro navegador: ahí se abre el acceso con el correo ya escrito.
    </p>
    <div class="acceso__enlace-copiar">
        <code class="acceso__enlace" data-enlace>{{ $enlace }}</code>
        <button type="button" class="boton-copiar" x-data="copiar(@js($enlace))" @click="copiar"
                x-text="copiado ? 'Copiado ✓' : 'Copiar enlace'">Copiar enlace</button>
    </div>

    <div class="acceso__contacto">
        <a href="{{ $actual->esAdministracion() ? route('admin.dashboard') : route('cuenta.estado') }}">Volver sin cambiar de sesión</a>
    </div>
</x-acceso.marco>
