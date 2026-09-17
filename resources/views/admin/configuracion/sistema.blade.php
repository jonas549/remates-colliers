@php
    use App\Models\Configuracion;
@endphp
<x-layouts.admin seccion="configuracion" subseccion="sistema" titulo="Configuración · Sistema" clase-cuerpo="placeholder-claro">
    <div class="admin-encabezado">
        <div class="admin-encabezado__texto">
            <div class="admin-encabezado__kicker">CONFIGURACIÓN</div>
            <h1 class="admin-encabezado__titulo admin-encabezado__titulo--con-bajada">Sistema</h1>
            <div class="admin-encabezado__bajada">{{ Configuracion::SECCIONES['sistema']['bajada'] }} Se mide desde la web, que es el PHP que atiende las pujas: la consola puede dar otro resultado.</div>
        </div>
    </div>

    <x-admin.avisos />

    <div class="admin-gestion">
        <div class="admin-gestion__bloque">
            <div class="admin-seccion__cabeza"><h2 class="admin-seccion__titulo">Estado del servidor</h2></div>
            <div class="admin-datos">
                @foreach ($sistema as $k => $v)
                    <div class="admin-ficha__dato"><span class="admin-ficha__k">{{ $k }}</span><span class="admin-ficha__v">{{ $v }}</span></div>
                @endforeach
            </div>
            <p class="admin-gestion__nota">Para el diagnóstico completo, en el servidor: <code>php artisan colliers:diagnostico</code>.</p>
        </div>
    </div>
</x-layouts.admin>
