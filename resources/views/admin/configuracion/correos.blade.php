@php
    use App\Correo\Plantillas;
    use App\Models\Configuracion;
    use App\Support\Formato;
@endphp
<x-layouts.admin seccion="configuracion" subseccion="correos" titulo="Configuración · Registro de correos" clase-cuerpo="placeholder-claro">
    <div class="admin-encabezado">
        <div class="admin-encabezado__texto">
            <div class="admin-encabezado__kicker">CONFIGURACIÓN</div>
            <h1 class="admin-encabezado__titulo admin-encabezado__titulo--con-bajada">Registro de correos</h1>
            <div class="admin-encabezado__bajada">{{ Configuracion::SECCIONES['correos']['bajada'] }} «Enviado» significa que el servidor de salida aceptó el mensaje, no que haya llegado a la bandeja.</div>
        </div>
        <div class="admin-encabezado__acciones">
            <a href="{{ route('admin.configuracion.correos.exportar', request()->query()) }}" class="admin-form__borrador">Exportar CSV</a>
        </div>
    </div>

    <x-admin.avisos />

    <div class="admin-gestion">
        <div class="admin-gestion__bloque">
            <div class="admin-resumen-correos">
                @foreach ($totales as $etiqueta => $cantidad)
                    <div class="admin-resumen-correos__dato"><span class="admin-resumen-correos__valor">{{ $cantidad }}</span> <span class="admin-resumen-correos__etiqueta">{{ $etiqueta }}</span></div>
                @endforeach
            </div>

            <form method="GET" action="{{ route('admin.configuracion.seccion', 'correos') }}" class="admin-form admin-form--plano">
                <div class="admin-form__interior">
                    <div class="admin-form__grilla">
                        <label class="admin-form__campo"><span class="admin-form__etiqueta">BUSCAR</span>
                            <input name="q" value="{{ request('q') }}" placeholder="Destinatario o asunto" class="admin-form__input">
                        </label>
                        <label class="admin-form__campo"><span class="admin-form__etiqueta">ESTADO</span>
                            <select name="estado" class="admin-form__input">
                                <option value="">Todos</option>
                                @foreach (['enviada' => 'Enviados (aceptados por el servidor)', 'fallida' => 'Fallidos', 'pendiente' => 'Pendientes en la cola'] as $valor => $texto)
                                    <option value="{{ $valor }}" @selected(request('estado') === $valor)>{{ $texto }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="admin-form__campo"><span class="admin-form__etiqueta">CORREO</span>
                            <select name="tipo" class="admin-form__input">
                                <option value="">Todos</option>
                                @foreach ($tipos as $tipo)
                                    <option value="{{ $tipo }}" @selected(request('tipo') === $tipo)>{{ $tipo }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="admin-form__campo"><span class="admin-form__etiqueta">DESDE</span>
                            <input type="date" name="desde" value="{{ request('desde') }}" class="admin-form__input">
                        </label>
                        <label class="admin-form__campo"><span class="admin-form__etiqueta">HASTA</span>
                            <input type="date" name="hasta" value="{{ request('hasta') }}" class="admin-form__input">
                        </label>
                    </div>
                    <div class="admin-form__botones admin-form__botones--compactos">
                        <button type="submit" class="admin-form__publicar">Filtrar</button>
                        <a href="{{ route('admin.configuracion.seccion', 'correos') }}" class="admin-form__borrador">Limpiar</a>
                    </div>
                </div>
            </form>

            <div class="admin-tabla-scroll">
                <table class="admin-tabla">
                    <thead>
                        <tr><th>CUÁNDO</th><th>CORREO</th><th>DESTINATARIO</th><th>ESTADO</th><th>DETALLE</th></tr>
                    </thead>
                    <tbody>
                        @forelse ($correos as $c)
                            <tr>
                                <td class="es-primera">
                                    <div class="admin-tabla__principal">{{ Formato::fechaCorta($c->created_at) }}</div>
                                    <div class="admin-tabla__secundario">{{ $c->created_at?->setTimezone(Formato::ZONA)->format('H:i:s') }}</div>
                                </td>
                                <td>
                                    <div class="admin-tabla__principal">{{ $c->asunto ?: '—' }}</div>
                                    <div class="admin-tabla__secundario">{{ $c->tipo }}</div>
                                </td>
                                <td>{{ $c->destinatario }}</td>
                                <td><span class="badge-admin badge-admin--{{ ['enviada' => 'adjudicada', 'fallida' => 'no-adjudicado'][$c->estado] ?? 'revision' }}">{{ mb_strtoupper($c->estado) }}</span></td>
                                <td>
                                    @if ($c->error)
                                        <details class="admin-error-correo">
                                            <summary>Ver el error</summary>
                                            <pre class="admin-error-correo__texto">{{ $c->error }}</pre>
                                        </details>
                                    @elseif ($c->enviada_en)
                                        <span class="admin-tabla__secundario">Aceptado por el servidor a las {{ $c->enviada_en->setTimezone(Formato::ZONA)->format('H:i:s') }}</span>
                                    @else
                                        <span class="admin-tabla__secundario">En la cola: la procesa el cron</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5">Sin correos con esos filtros.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{ $correos->withQueryString()->links('vendor.pagination.colliers') }}
            <p class="admin-gestion__nota">
                Un correo queda «pendiente» hasta que el cron procesa la cola (cada minuto). «Fallido» trae el error del
                servidor completo. Si un correo figura como enviado y el destinatario dice que no llegó, revisa el filtro de
                spam y los registros del servidor de correo: desde aquí solo se ve lo que respondió el servidor de salida.
            </p>
        </div>
    </div>
</x-layouts.admin>
