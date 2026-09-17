@php
    use App\Correo\Plantillas;
    use App\Models\Configuracion;
    use App\Models\PlantillaCorreo;

    $editadas = PlantillaCorreo::pluck('updated_at', 'clave');
@endphp
<x-layouts.admin seccion="configuracion" titulo="Configuración · Plantillas de correo" clase-cuerpo="placeholder-claro">
    <div class="admin-encabezado">
        <div class="admin-encabezado__texto">
            <div class="admin-encabezado__kicker">CONFIGURACIÓN</div>
            <h1 class="admin-encabezado__titulo admin-encabezado__titulo--con-bajada">Plantillas de correo</h1>
            <div class="admin-encabezado__bajada">{{ Configuracion::SECCIONES['plantillas']['bajada'] }} Cada una se edita con sus variables y una vista previa; el botón «Restaurar la original» vuelve al texto de fábrica.</div>
        </div>
    </div>

    @include('admin.configuracion._submenu', ['actual' => 'plantillas'])

    <x-admin.avisos />

    <div class="admin-gestion">
        <div class="admin-gestion__bloque">
            <div class="admin-tabla-scroll">
                <table class="admin-tabla">
                    <thead>
                        <tr><th>CORREO</th><th>CUÁNDO SE ENVÍA</th><th>A QUIÉN</th><th>TEXTO</th><th></th></tr>
                    </thead>
                    <tbody>
                        @foreach (Plantillas::CATALOGO as $clave => $p)
                            <tr>
                                <td class="es-primera">
                                    <div class="admin-tabla__principal">{{ $p['nombre'] }}</div>
                                    <div class="admin-tabla__secundario">{{ Plantillas::vigente($clave)['asunto'] }}</div>
                                </td>
                                <td>{{ $p['cuando'] }}</td>
                                <td>{{ $p['destinatario'] }}</td>
                                <td>
                                    @if (isset($editadas[$clave]))
                                        <span class="badge-admin badge-admin--revision">EDITADA</span>
                                    @else
                                        <span class="badge-admin badge-admin--cerrado">ORIGINAL</span>
                                    @endif
                                </td>
                                <td><a href="{{ route('admin.configuracion.plantilla', $clave) }}" class="admin-accion">Editar</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <p class="admin-gestion__nota">Los correos salen por la cola: el cron los procesa cada minuto. Qué se envía y a quién se decide en «Notificaciones».</p>
        </div>
    </div>
</x-layouts.admin>
