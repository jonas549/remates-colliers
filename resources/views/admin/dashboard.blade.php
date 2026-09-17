<x-layouts.admin seccion="dashboard" titulo="Resumen general">
    <div class="admin-encabezado">
        <div>
            <div class="admin-encabezado__kicker">PANEL DE CONTROL</div>
            <h1 class="admin-encabezado__titulo">Resumen general</h1>
        </div>
        <div class="admin-fecha">{{ $datos['fecha'] }}</div>
        <div class="admin-encabezado__acciones">
            <a href="{{ route('admin.subastas') }}" class="admin-boton">Crear subasta</a>
            <a href="{{ route('admin.reportes') }}" class="admin-boton-secundario">Exportar reportes</a>
        </div>
    </div>

    <div class="admin-kpis">
        @foreach ($datos['kpis'] as [$etiqueta, $valor, $nota, $alerta])
            <div @class(['admin-kpi', 'es-alerta' => $alerta])>
                <div class="admin-kpi__etiqueta">{{ $etiqueta }}</div>
                <div class="admin-kpi__valor">{{ $valor }}</div>
                <div class="admin-kpi__nota">{{ $nota }}</div>
            </div>
        @endforeach
    </div>

    <div class="admin-dashboard">

        <div class="admin-seccion admin-seccion--ancha">
            <div class="admin-seccion__cabeza">
                <h2 class="admin-seccion__titulo">Subastas en curso y próximas</h2>
                <a href="{{ route('admin.subastas') }}" class="admin-seccion__enlace">Gestionar subastas →</a>
            </div>
            <div class="admin-tabla-scroll">
                <table class="admin-tabla admin-tabla--dashboard">
                    <thead>
                        <tr>
                            <th>REMATE</th>
                            <th>ESTADO</th>
                            <th>PRECIO BASE</th>
                            <th>PUJA ACTUAL</th>
                            <th>POSTORES</th>
                            <th>CIERRE</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($datos['subastas'] as $s)
                            <tr>
                                <td>
                                    <div class="admin-tabla__principal">{{ $s['direccion'] }}</div>
                                    <div class="admin-tabla__secundario"><span>{{ $s['folio'] }}</span> · <span>{{ $s['comuna'] }}</span></div>
                                </td>
                                <td><span class="badge-admin badge-admin--{{ $s['tono'] }}">{{ $s['estado'] }}</span></td>
                                <td class="es-num">{{ $s['base'] }}</td>
                                <td class="es-fuerte">{{ $s['actual'] }}</td>
                                <td>{{ $s['postores'] }}</td>
                                <td>{{ $s['cierre'] }}</td>
                                <td><a href="{{ $s['url'] }}">Ver</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="7">No hay subastas en curso ni próximas. <a href="{{ route('admin.subastas') }}">Crear una</a>.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="admin-seccion">
            <div class="admin-seccion__cabeza">
                <h2 class="admin-seccion__titulo">Pendientes de aprobación</h2>
                <a href="{{ route('admin.postores') }}" class="admin-seccion__enlace">Ver todos →</a>
            </div>
            @forelse ($datos['pendientes'] as [$nombre, $detalle, $estado, $tono])
                <div class="admin-pendiente">
                    <div style="min-width: 0">
                        <div class="admin-pendiente__nombre">{{ $nombre }}</div>
                        <div class="admin-pendiente__detalle">{{ $detalle }}</div>
                    </div>
                    <span class="badge-admin badge-admin--{{ $tono }}">{{ $estado }}</span>
                </div>
            @empty
                <div class="admin-seccion__nota">No hay cuentas ni garantías esperando revisión.</div>
            @endforelse
            <div class="admin-seccion__nota">Las garantías se revisan manualmente contra el vale a la vista o la transferencia recibida. No hay pasarela de pago.</div>
        </div>

        <div class="admin-seccion">
            <div class="admin-seccion__cabeza">
                <h2 class="admin-seccion__titulo">Actividad reciente</h2>
            </div>
            @forelse ($datos['actividad'] as [$texto, $hora, $color])
                <div class="admin-actividad">
                    <div class="admin-actividad__punto" style="background: {{ $color }}"></div>
                    <div style="min-width: 0">
                        <div class="admin-actividad__texto">{{ $texto }}</div>
                        <div class="admin-actividad__hora">{{ $hora }}</div>
                    </div>
                </div>
            @empty
                <div class="admin-seccion__nota">Todavía no hay actividad.</div>
            @endforelse
        </div>
    </div>
</x-layouts.admin>
