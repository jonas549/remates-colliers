@php
    use App\Support\Formato;

    /** @var \App\Reportes\ReporteRemates $reporte */
    $millones = fn (int $n) => '$' . number_format(round($n / 1000000), 0, ',', '.') . 'M';
    $porcentaje = fn (?float $v, bool $signo = false) => $v === null ? '—' : ($signo ? '+' : '') . round($v * 100) . '%';
    $minutos = fn (?float $m) => $m === null ? '—' : ($m < 1 ? '< 1 min' : round($m) . ' min');

    $t = $reporte->totales();
    $filas = $reporte->desempeno();
    $porLote = $t['lotes'] !== $t['remates'];
    $promedioPujas = $t['lotes'] ? round($t['pujas'] / $t['lotes']) : 0;

    $kpis = [
        ['VOLUMEN ADJUDICADO', $millones($t['volumen']), $t['vendidos'] === 1 ? '1 propiedad vendida' : "{$t['vendidos']} propiedades vendidas"],
        ['TASA DE VENTA', $porcentaje($t['tasa']), "{$t['vendidos']} de {$t['lotes']} concretados"],
        ['SOBREPRECIO MEDIO', $porcentaje($t['sobreprecio'], true), 'Final sobre precio base'],
        ['POSTORES REGISTRADOS', (string) $t['registrados'], "{$t['con_garantia']} con garantía aprobada"],
        ['POSTORES ACTIVOS', (string) $t['activos'], 'Ingresaron al menos una puja'],
        ['PUJAS TOTALES', (string) $t['pujas'], "Promedio de {$promedioPujas} por " . ($porLote ? 'lote' : 'remate')],
    ];

    // Barras relativas a los postores registrados, como el diseño.
    $base = max($t['registrados'], 1);
    $participacion = [
        ['Postores registrados', $t['registrados'], 'Cuentas aprobadas por Colliers'],
        ['Con garantía aprobada', $t['con_garantia'], 'Habilitados para pujar en algún remate del período'],
        ['Postores activos', $t['activos'], 'Ingresaron al menos una postura'],
        ['Adjudicatarios', $t['adjudicatarios'], 'Ganaron al menos un ' . ($porLote ? 'lote' : 'remate')],
        ['Garantías rechazadas', $t['garantias_rechazadas'], 'Comprobantes no aceptados en los remates del período'],
    ];

    $dinamica = $reporte->dinamica();
    $maximoMinutos = max(array_merge([1], array_column($dinamica, 'minutos')));
    $categorias = $reporte->categorias();
    $consulta = ['periodo' => $reporte->periodo()];
@endphp
<x-layouts.admin seccion="reportes" titulo="Reportes">
    <div>
        <div class="admin-encabezado">
            <div>
                <div class="admin-encabezado__kicker">REPORTES POST-EVENTO</div>
                <h1 class="admin-encabezado__titulo admin-encabezado__titulo--con-bajada">Desempeño de los remates</h1>
                <div class="admin-encabezado__bajada">Remates realizados · {{ $reporte->etiqueta() }} · montos en pesos</div>
            </div>
            <div class="admin-encabezado__acciones" style="gap: 10px">
                <form method="GET" action="{{ route('admin.reportes') }}" class="formulario-en-linea">
                    <select name="periodo" onchange="this.form.submit()" class="admin-reportes__periodo" aria-label="Período">
                        @foreach ($opciones as $grupo => $valores)
                            <optgroup label="{{ $grupo }}">
                                @foreach ($valores as $clave => $nombre)
                                    <option value="{{ $clave }}" @selected($clave === $reporte->periodo())>{{ $nombre }}</option>
                                @endforeach
                            </optgroup>
                        @endforeach
                    </select>
                    <noscript><button type="submit" class="admin-reportes__formato">Ver</button></noscript>
                </form>
                <a href="{{ route('admin.reportes.exportar', ['desempeno', 'csv'] + $consulta) }}" class="admin-reportes__formato" title="Desempeño comercial en CSV">CSV</a>
                <a href="{{ route('admin.reportes.exportar', ['libro', 'xlsx'] + $consulta) }}" class="admin-reportes__formato" title="Libro con todas las hojas">XLSX</a>
                <button type="button" class="admin-reportes__pdf">Descargar PDF</button>
            </div>
        </div>

        <div class="admin-reportes__kpis-fondo">
            <div class="admin-reportes__kpis">
                @foreach ($kpis as [$etiqueta, $valor, $nota])
                    <div class="admin-reportes__kpi">
                        <div class="admin-reportes__kpi-etiqueta">{{ $etiqueta }}</div>
                        <div class="admin-reportes__kpi-valor">{{ $valor }}</div>
                        <div class="admin-reportes__kpi-nota">{{ $nota }}</div>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="admin-reportes">
            <div class="admin-seccion__cabeza">
                <h2 class="admin-seccion__titulo">Desempeño comercial por remate</h2>
                <span class="admin-reportes__nota-titulo">Precio base contra precio final, en pesos</span>
            </div>
            <div class="admin-tabla-scroll">
                <table class="admin-tabla admin-tabla--reportes">
                    <thead>
                        <tr>
                            <th>REMATE</th>
                            <th>CATEGORÍA</th>
                            <th>PRECIO BASE</th>
                            <th>PRECIO FINAL</th>
                            <th>SOBREPRECIO</th>
                            <th>PUJAS</th>
                            <th>CIERRE</th>
                            <th>RESULTADO</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($filas as $f)
                            @php($regla = ['No adjudicado' => 'regla-no', 'Cierre anticipado' => 'regla-anticipado'][$f['resultado']] ?? '')
                            <tr>
                                <td class="es-primera {{ $regla }}">
                                    <div class="admin-tabla__principal">{{ $f['direccion'] }}</div>
                                    <div class="admin-tabla__secundario"><span>{{ $f['folio'] }}{{ $f['lote'] ? ' · Lote ' . $f['lote'] : '' }}</span> · <span>{{ $f['cerrado_en']->setTimezone(Formato::ZONA)->format('d-m-Y') }}</span></div>
                                </td>
                                <td>{{ $f['categoria'] }}</td>
                                <td class="es-num">{{ Formato::clp($f['base']) }}</td>
                                <td class="es-fuerte">{{ $f['final'] !== null ? Formato::clp($f['final']) : '—' }}</td>
                                <td><span @class(['admin-reportes__delta', 'es-vacio' => $f['final'] === null])>{{ $porcentaje($f['sobreprecio'], true) }}</span></td>
                                <td class="es-num">{{ $f['pujas'] }}</td>
                                <td>{{ $minutos($f['minutos']) }}</td>
                                <td><span class="badge-admin {{ ['Adjudicado' => 'badge-admin--adjudicada', 'No adjudicado' => 'badge-admin--no-adjudicado', 'Cierre anticipado' => 'badge-admin--anticipado'][$f['resultado']] }}">{{ $f['resultado'] }}</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="8">Sin remates cerrados en {{ $reporte->etiqueta() }}.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="admin-reportes__grilla">
                <div class="admin-seccion">
                    <div class="admin-reportes__bloque-cabeza"><h2 class="admin-seccion__titulo">Participación</h2></div>
                    @foreach ($participacion as [$label, $valor, $nota])
                        <div class="admin-participacion">
                            <div class="admin-participacion__fila">
                                <span class="admin-participacion__label">{{ $label }}</span>
                                <span class="admin-participacion__valor">{{ $valor }}</span>
                            </div>
                            <div class="admin-participacion__barra"><div class="admin-participacion__relleno" style="width: {{ min(100, round($valor / $base * 100)) }}%"></div></div>
                            <div class="admin-participacion__nota">{{ $nota }}</div>
                        </div>
                    @endforeach
                </div>

                <div class="admin-seccion">
                    <div class="admin-reportes__bloque-cabeza"><h2 class="admin-seccion__titulo">Dinámica de cierre</h2></div>
                    @if ($dinamica)
                        <div class="admin-dinamica" role="img" aria-label="Minutos entre la primera y la última puja por remate">
                            @foreach ($dinamica as $d)
                                <div class="admin-dinamica__columna">
                                    <div class="admin-dinamica__valor">{{ round($d['minutos']) }}′</div>
                                    <div @class(['admin-dinamica__barra', 'es-larga' => $d['minutos'] >= $maximoMinutos * 0.85]) style="height: {{ max(2, round($d['minutos'] / $maximoMinutos * 130)) }}px"></div>
                                </div>
                            @endforeach
                        </div>
                        <div class="admin-dinamica__etiquetas">
                            @foreach ($dinamica as $d)
                                <div class="admin-dinamica__etiqueta">{{ $d['etiqueta'] }}</div>
                            @endforeach
                        </div>
                    @else
                        <p class="admin-reportes__texto">Sin pujas en los remates de este período.</p>
                    @endif
                    <p class="admin-reportes__texto">Minutos entre la primera y la última puja de cada remate{{ count($dinamica) === 12 ? ' (los 12 más recientes)' : '' }}. El cierre es automático al vencer el tiempo configurado: sin extensiones, la actividad se concentra en los minutos finales.</p>
                </div>

                <div class="admin-seccion">
                    <div class="admin-reportes__bloque-cabeza admin-reportes__bloque-cabeza--tabla"><h2 class="admin-seccion__titulo">Resumen por categoría</h2></div>
                    <div class="admin-tabla-scroll">
                        <table class="admin-tabla admin-tabla--categorias">
                            <thead>
                                <tr>
                                    <th>CATEGORÍA</th>
                                    <th>REMATES</th>
                                    <th>VOLUMEN</th>
                                    <th>SOBREPRECIO</th>
                                    <th>TASA</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($categorias as $c)
                                    <tr @class(['es-total' => $c['total']])>
                                        <td class="es-primera">{{ $c['categoria'] }}</td>
                                        <td class="es-num">{{ $c['lotes'] }}</td>
                                        <td class="es-num">{{ $millones($c['volumen']) }}</td>
                                        <td class="es-num">{{ $porcentaje($c['sobreprecio'], true) }}</td>
                                        <td class="es-num">{{ $porcentaje($c['tasa']) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <p class="admin-reportes__texto admin-reportes__texto--gris">Los remates no concretados no se reabren: se publican como remates nuevos y se contabilizan por separado en el período en que se realizan.</p>
                </div>
            </div>

            <div class="admin-exportables">
                <h2 class="admin-seccion__titulo">Exportables disponibles</h2>
                <div class="admin-exportables__grilla">
                    @foreach ($exportables as $clave => [$nombre, $detalle])
                        <div class="admin-exportable">
                            <div style="min-width: 0">
                                <div class="admin-exportable__nombre">{{ $nombre }}</div>
                                <div class="admin-exportable__detalle">{{ $detalle }}</div>
                            </div>
                            <a href="{{ route('admin.reportes.exportar', [$clave, 'xlsx'] + $consulta) }}" class="admin-exportable__descargar">Descargar</a>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</x-layouts.admin>
