@php
    use App\Models\Configuracion;
    use App\Models\NotificacionLog;
    use App\Support\Formato;

    $tono = fn (string $estado) => [
        'aceptada' => 'adjudicada', 'fallida' => 'no-adjudicado', 'registrada' => 'cerrado', 'sin_verificar' => 'anticipado',
    ][$estado] ?? 'revision';
    // Lo que se ve sin abrir la fila: nunca afirma más de lo que el transporte respondió.
    $resumen = fn (NotificacionLog $c) => match ($c->estado) {
        'aceptada' => $c->respuesta ? mb_substr($c->respuesta, 0, 60) : 'El servidor lo aceptó (sin respuesta guardada)',
        'registrada' => 'No salió: quedó en el archivo de registro',
        'fallida' => mb_substr((string) ($c->respuesta ?: $c->error), 0, 60),
        'pendiente' => 'En la cola: la procesa el cron',
        default => 'Sin verificar: anterior a la corrección',
    };
@endphp
<x-layouts.admin seccion="configuracion" subseccion="correos" titulo="Configuración · Registro de correos" clase-cuerpo="placeholder-claro">
    <div class="admin-encabezado">
        <div class="admin-encabezado__texto">
            <div class="admin-encabezado__kicker">CONFIGURACIÓN</div>
            <h1 class="admin-encabezado__titulo admin-encabezado__titulo--con-bajada">Registro de correos</h1>
            <div class="admin-encabezado__bajada">{{ Configuracion::SECCIONES['correos']['bajada'] }} «Aceptada» significa que el servidor de salida recibió el mensaje: no garantiza que haya llegado a la bandeja. «Solo registrada» es un correo que quedó en el archivo de registro y no salió a Internet.</div>
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
                                @foreach (NotificacionLog::ESTADOS as $valor => $texto)
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
                        <tr><th>CUÁNDO</th><th>CORREO</th><th>DESTINATARIO</th><th>ESTADO</th><th>QUÉ RESPONDIÓ EL SERVIDOR</th></tr>
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
                                <td>
                                    <span class="badge-admin badge-admin--{{ $tono($c->estado) }}">{{ NotificacionLog::ETIQUETAS[$c->estado] ?? mb_strtoupper($c->estado) }}</span>
                                </td>
                                <td>
                                    {{-- Cada fila se abre: transporte, respuesta literal, Message-ID, remitente y el error entero. --}}
                                    <details class="admin-error-correo">
                                        <summary>{{ $resumen($c) }}</summary>
                                        <div class="admin-detalle-correo">
                                            @foreach ([
                                                'Estado' => NotificacionLog::ESTADOS[$c->estado] ?? $c->estado,
                                                'Transporte' => $c->transporte ?: 'sin registrar (anterior a la corrección)',
                                                'Respuesta del servidor' => $c->respuesta ?: ($c->estado === 'registrada' ? 'ninguna: el correo quedó en el archivo de registro' : 'no registrada'),
                                                'Message-ID' => $c->message_id ?: '—',
                                                'Remitente' => $c->remitente ?: '—',
                                                'Destinatario' => $c->destinatario,
                                                'Asunto' => $c->asunto ?: '—',
                                                'Aviso' => $c->tipo,
                                                'Aceptado a las' => $c->enviada_en?->setTimezone(Formato::ZONA)->format('d-m-Y H:i:s') ?: '—',
                                            ] as $etiqueta => $valor)
                                                <div class="admin-ficha__dato"><span class="admin-ficha__k">{{ $etiqueta }}</span><span class="admin-ficha__v">{{ $valor }}</span></div>
                                            @endforeach
                                            @if ($c->error)
                                                <pre class="admin-error-correo__texto">{{ $c->error }}</pre>
                                            @endif
                                        </div>
                                    </details>
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
                Abre cualquier fila para ver el transporte, la respuesta literal del servidor, el Message-ID y, si falló, el
                error completo. Un correo queda «pendiente» hasta que el cron procesa la cola (cada minuto).
                «Aceptada» es lo máximo que el sistema puede afirmar: la entrega depende del servidor de destino, así que
                con el Message-ID se rastrea en los registros del proveedor. Las filas «sin verificar» son anteriores al
                17/09, cuando el sistema anotaba «enviada» sin guardar la respuesta del transporte.
            </p>
        </div>
    </div>
</x-layouts.admin>
