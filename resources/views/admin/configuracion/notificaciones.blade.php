@php
    use App\Correo\Avisos;
    use App\Correo\Plantillas;
    use App\Models\Configuracion;
    use App\Support\Formato;
@endphp
<x-layouts.admin seccion="configuracion" titulo="Configuración · Notificaciones" clase-cuerpo="placeholder-claro">
    <div class="admin-encabezado">
        <div class="admin-encabezado__texto">
            <div class="admin-encabezado__kicker">CONFIGURACIÓN</div>
            <h1 class="admin-encabezado__titulo admin-encabezado__titulo--con-bajada">Notificaciones</h1>
            <div class="admin-encabezado__bajada">{{ Configuracion::SECCIONES['notificaciones']['bajada'] }} Los correos salen por la cola, que el cron procesa cada minuto. El texto de cada uno se edita en «Plantillas de correo».</div>
        </div>
    </div>

    @include('admin.configuracion._submenu', ['actual' => 'notificaciones'])

    <x-admin.avisos />

    <div class="admin-gestion">
        <form method="POST" action="{{ route('admin.configuracion.update', 'notificaciones') }}" class="admin-form admin-form--plano">
            @csrf
            @method('PUT')
            <div class="admin-form__interior">
                <x-admin.errores />
                @include('admin.configuracion._campos')

                <div class="admin-form__seccion admin-form__seccion--siguiente">
                    <span class="admin-form__num">02</span>
                    <span class="admin-form__subtitulo">Qué se envía</span>
                </div>
                <div class="admin-tabla-scroll">
                    <table class="admin-tabla">
                        <thead>
                            <tr><th>CORREO</th><th>CUÁNDO</th><th>A QUIÉN</th><th>SE ENVÍA</th></tr>
                        </thead>
                        <tbody>
                            @foreach (Plantillas::CATALOGO as $clave => $p)
                                <tr>
                                    <td class="es-primera">
                                        <div class="admin-tabla__principal">{{ $p['nombre'] }}</div>
                                        <div class="admin-tabla__secundario"><a href="{{ route('admin.configuracion.plantilla', $clave) }}">Editar el texto</a></div>
                                    </td>
                                    <td>{{ $p['cuando'] }}</td>
                                    <td>{{ $p['destinatario'] }}</td>
                                    <td>
                                        @if ($p['activable'])
                                            <label class="admin-form__check admin-form__check--fila">
                                                <input type="hidden" name="avisos[{{ $clave }}]" value="0">
                                                <input type="checkbox" name="avisos[{{ $clave }}]" value="1" @checked(Avisos::activo($clave))>
                                                <span>Sí</span>
                                            </label>
                                        @else
                                            <span class="admin-tabla__secundario">Siempre (correo de la cuenta)</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="admin-form__botones">
                    <button type="submit" class="admin-form__publicar">Guardar cambios</button>
                </div>
            </div>
        </form>

        <div class="admin-gestion__bloque">
            <div class="admin-seccion__cabeza"><h2 class="admin-seccion__titulo">Últimos envíos</h2></div>
            <div class="admin-tabla-scroll">
                <table class="admin-tabla">
                    <thead>
                        <tr><th>CUÁNDO</th><th>CORREO</th><th>DESTINATARIO</th><th>ESTADO</th></tr>
                    </thead>
                    <tbody>
                        @forelse ($envios as $envio)
                            <tr>
                                <td class="es-primera">{{ Formato::fechaCorta($envio->created_at) }}</td>
                                <td>{{ $envio->asunto ?: $envio->tipo }}</td>
                                <td>{{ $envio->destinatario }}</td>
                                <td>
                                    <span class="badge-admin badge-admin--{{ ['enviada' => 'adjudicada', 'fallida' => 'no-adjudicado'][$envio->estado] ?? 'revision' }}">{{ mb_strtoupper($envio->estado) }}</span>
                                    @if ($envio->error)<div class="admin-tabla__secundario">{{ mb_substr($envio->error, 0, 160) }}</div>@endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4">Todavía no se ha enviado ningún correo.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <p class="admin-gestion__nota">Últimos 20 registros de la bitácora. Un correo queda «pendiente» hasta que la cola lo procesa.</p>
        </div>
    </div>
</x-layouts.admin>
