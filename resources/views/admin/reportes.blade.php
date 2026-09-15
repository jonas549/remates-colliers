@php
    use App\Demo\RematesDemo;

    $clp = fn ($n) => RematesDemo::clp($n);
    $millones = fn ($n) => '$' . number_format(round($n / 1000000), 0, ',', '.') . 'M';

    $remates = [
        ['R-2026-105', 'San Martín 655, Casa A', 'Casa', '19-08-2026', 72000000, 88000000, 23, '38 min', 'Adjudicado'],
        ['R-2026-103', 'Av. Vitacura 8720, Depto. 1105', 'Departamento', '14-08-2026', 156000000, 171500000, 17, '41 min', 'Adjudicado'],
        ['R-2026-108', 'Av. Alemania 0980, Depto. 401', 'Departamento', '12-08-2026', 64000000, 0, 0, '—', 'No adjudicado'],
        ['R-2026-101', 'Los Nogales 220, Casa 4', 'Casa', '05-08-2026', 118000000, 124000000, 9, '22 min', 'Cierre anticipado'],
        ['R-2026-099', 'Santa Isabel 1450, Depto. 908', 'Departamento', '31-07-2026', 88000000, 103500000, 31, '44 min', 'Adjudicado'],
        ['R-2026-097', 'Camino Otoñal 55, Casa 9', 'Casa', '28-07-2026', 245000000, 262000000, 14, '36 min', 'Adjudicado'],
    ];
    $adjudicados = array_filter($remates, fn ($r) => $r[5] > 0);
    $volumen = array_sum(array_column($adjudicados, 5));
    $sobreprecioMedio = array_sum(array_map(fn ($r) => $r[5] / $r[4] - 1, $adjudicados)) / count($adjudicados);
    $tasa = round(count($adjudicados) / count($remates) * 100);

    $kpis = [
        ['VOLUMEN ADJUDICADO', $millones($volumen), count($adjudicados) . ' propiedades vendidas'],
        ['TASA DE VENTA', $tasa . '%', count($adjudicados) . ' de ' . count($remates) . ' concretados'],
        ['SOBREPRECIO MEDIO', '+' . round($sobreprecioMedio * 100) . '%', 'Final sobre precio base'],
        ['POSTORES REGISTRADOS', '184', '96 con garantía aprobada'],
        ['POSTORES ACTIVOS', '61', 'Ingresaron al menos una puja'],
        ['PUJAS TOTALES', '94', 'Promedio de 16 por remate'],
    ];
    $participacion = [
        ['Postores registrados', '184', 100, 'Cuentas aprobadas por Colliers'],
        ['Con garantía aprobada', '96', 52, 'Habilitados para pujar en algún remate'],
        ['Postores activos', '61', 33, 'Ingresaron al menos una postura'],
        ['Adjudicatarios', '5', 3, 'Uno por remate concretado'],
        ['Garantías rechazadas', '12', 7, 'Monto o titular incorrecto'],
    ];
    $dinamica = [['R-105', 38], ['R-103', 41], ['R-101', 22], ['R-099', 44], ['R-097', 36], ['R-094', 29]];
    $categorias = [
        ['Departamentos', '4', $millones(275000000), '+13%', '75%', false],
        ['Casas', '3', $millones(474000000), '+11%', '100%', false],
        ['Total período', '7', $millones($volumen), '+' . round($sobreprecioMedio * 100) . '%', $tasa . '%', true],
    ];
    $exportables = [
        ['Desempeño comercial', 'Base, final, sobreprecio y resultado por remate'],
        ['Participación de postores', 'Registrados, habilitados, activos y adjudicatarios'],
        ['Detalle de pujas', 'Cada postura con hora, monto y postor anonimizado'],
        ['Garantías del período', 'Aprobadas, rechazadas y devueltas por remate'],
    ];
@endphp
<x-layouts.admin seccion="reportes" titulo="Reportes">
    <div x-data="{ periodo: 'mes' }">
        <div class="admin-encabezado">
            <div>
                <div class="admin-encabezado__kicker">REPORTES POST-EVENTO</div>
                <h1 class="admin-encabezado__titulo admin-encabezado__titulo--con-bajada">Desempeño de los remates</h1>
                <div class="admin-encabezado__bajada" x-text="'Remates realizados · ' + ({ mes: 'agosto 2026', trimestre: 'julio a septiembre 2026', anio: 'año 2026' })[periodo] + ' · montos en pesos'">Remates realizados · agosto 2026 · montos en pesos</div>
            </div>
            <div class="admin-encabezado__acciones" style="gap: 10px">
                <select x-model="periodo" class="admin-reportes__periodo" aria-label="Período">
                    <option value="mes">Agosto 2026</option>
                    <option value="trimestre">Trimestre en curso</option>
                    <option value="anio">Año 2026</option>
                </select>
                <button type="button" class="admin-reportes__formato">CSV</button>
                <button type="button" class="admin-reportes__formato">XLSX</button>
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
                        @foreach ($remates as [$folio, $direccion, $categoria, $fecha, $base, $final, $pujas, $duracion, $resultado])
                            @php($regla = ['No adjudicado' => 'regla-no', 'Cierre anticipado' => 'regla-anticipado'][$resultado] ?? '')
                            <tr>
                                <td class="es-primera {{ $regla }}">
                                    <div class="admin-tabla__principal">{{ $direccion }}</div>
                                    <div class="admin-tabla__secundario"><span>{{ $folio }}</span> · <span>{{ $fecha }}</span></div>
                                </td>
                                <td>{{ $categoria }}</td>
                                <td class="es-num">{{ $clp($base) }}</td>
                                <td class="es-fuerte">{{ $final ? $clp($final) : '—' }}</td>
                                <td><span @class(['admin-reportes__delta', 'es-vacio' => ! $final])>{{ $final ? '+' . round(($final / $base - 1) * 100) . '%' : '—' }}</span></td>
                                <td class="es-num">{{ $pujas ?: '0' }}</td>
                                <td>{{ $duracion }}</td>
                                <td><span class="badge-admin {{ ['Adjudicado' => 'badge-admin--adjudicada', 'No adjudicado' => 'badge-admin--no-adjudicado', 'Cierre anticipado' => 'badge-admin--anticipado'][$resultado] }}">{{ $resultado }}</span></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="admin-reportes__grilla">
                <div class="admin-seccion">
                    <div class="admin-reportes__bloque-cabeza"><h2 class="admin-seccion__titulo">Participación</h2></div>
                    @foreach ($participacion as [$label, $valor, $pct, $nota])
                        <div class="admin-participacion">
                            <div class="admin-participacion__fila">
                                <span class="admin-participacion__label">{{ $label }}</span>
                                <span class="admin-participacion__valor">{{ $valor }}</span>
                            </div>
                            <div class="admin-participacion__barra"><div class="admin-participacion__relleno" style="width: {{ $pct }}%"></div></div>
                            <div class="admin-participacion__nota">{{ $nota }}</div>
                        </div>
                    @endforeach
                </div>

                <div class="admin-seccion">
                    <div class="admin-reportes__bloque-cabeza"><h2 class="admin-seccion__titulo">Dinámica de cierre</h2></div>
                    <div class="admin-dinamica" role="img" aria-label="Minutos entre la primera y la última puja por remate">
                        @foreach ($dinamica as [$etiqueta, $minutos])
                            <div class="admin-dinamica__columna">
                                <div class="admin-dinamica__valor">{{ $minutos }}′</div>
                                <div @class(['admin-dinamica__barra', 'es-larga' => $minutos >= 40]) style="height: {{ round($minutos / 45 * 130) }}px"></div>
                            </div>
                        @endforeach
                    </div>
                    <div class="admin-dinamica__etiquetas">
                        @foreach ($dinamica as [$etiqueta])
                            <div class="admin-dinamica__etiqueta">{{ $etiqueta }}</div>
                        @endforeach
                    </div>
                    <p class="admin-reportes__texto">Minutos entre la primera y la última puja de cada remate. El cierre es automático al vencer el tiempo configurado: sin extensiones, la actividad se concentra en los minutos finales.</p>
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
                                @foreach ($categorias as [$nombre, $numero, $vol, $sobre, $tasaCat, $total])
                                    <tr @class(['es-total' => $total])>
                                        <td class="es-primera">{{ $nombre }}</td>
                                        <td class="es-num">{{ $numero }}</td>
                                        <td class="es-num">{{ $vol }}</td>
                                        <td class="es-num">{{ $sobre }}</td>
                                        <td class="es-num">{{ $tasaCat }}</td>
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
                    @foreach ($exportables as [$nombre, $detalle])
                        <div class="admin-exportable">
                            <div style="min-width: 0">
                                <div class="admin-exportable__nombre">{{ $nombre }}</div>
                                <div class="admin-exportable__detalle">{{ $detalle }}</div>
                            </div>
                            <button type="button">Descargar</button>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</x-layouts.admin>
