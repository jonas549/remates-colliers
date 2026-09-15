{{-- Clave de acceso al sandbox. No pertenece al diseño del prototipo: usa sus tokens y componentes. --}}
<x-layouts.base titulo="Acceso restringido" clase-cuerpo="placeholder-claro">
    <div class="acceso-sandbox">
        <x-tramite.cabecera etiqueta="REMATES" />

        <div class="acceso-sandbox__cuerpo">
            <div class="acceso-sandbox__caja">
                <div class="acceso-sandbox__kicker">SITIO EN PREPARACIÓN</div>
                <h1 class="acceso-sandbox__titulo">Acceso restringido</h1>
                <p class="acceso-sandbox__texto">Este sitio es una versión de prueba de la plataforma de remates de Colliers. Ingresa la clave de acceso que te entregó el equipo del proyecto.</p>

                <form method="POST" action="{{ route('acceso.ingresar') }}">
                    @csrf
                    <label class="acceso-sandbox__campo">
                        <span class="acceso-sandbox__etiqueta">CLAVE DE ACCESO</span>
                        <input type="password" name="clave" class="acceso-sandbox__input" autocomplete="current-password" autofocus required>
                    </label>
                    @error('clave')
                        <div class="acceso-sandbox__error" role="alert">{{ $message }}</div>
                    @enderror
                    <button type="submit" class="acceso-sandbox__boton">Ingresar</button>
                </form>
            </div>
        </div>

        <x-tramite.pie />
    </div>
</x-layouts.base>
