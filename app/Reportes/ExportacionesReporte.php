<?php

namespace App\Reportes;

use App\Support\Formato;
use Carbon\CarbonInterface;
use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * Exportables del reporte (Bloque O). Montos en pesos como números enteros; porcentajes con un decimal; fechas en hora
 * de Chile. Sin identidades de postores.
 */
class ExportacionesReporte implements Export, WithMultipleSheets
{
    /** clave => [nombre visible, descripción]. `libro` reúne todas las hojas (solo XLSX). */
    public const HOJAS = [
        'desempeno' => ['Desempeño comercial', 'Base, final, sobreprecio y resultado por remate'],
        'participacion' => ['Participación de postores', 'Inscritos, garantías aprobadas, activos y pujas por remate'],
        'pujas' => ['Detalle de pujas', 'Cada postura con hora, monto y postor anonimizado'],
        'garantias' => ['Garantías del período', 'Inscritas, en revisión, aprobadas y rechazadas por remate'],
    ];

    private const PESOS = '#,##0';

    public function __construct(private readonly ReporteRemates $reporte) {}

    public function sheets(): array
    {
        return array_map(fn (string $clave) => $this->hoja($clave), ['resumen', 'desempeno', 'categorias', 'participacion', 'pujas', 'garantias']);
    }

    public function hoja(string $clave): HojaReporte
    {
        $r = $this->reporte;
        $pct = fn (?float $v) => $v === null ? null : round($v * 100, 1);
        $fecha = fn (?CarbonInterface $f, string $formato = 'd-m-Y H:i') => $f?->setTimezone(Formato::ZONA)->format($formato);

        return match ($clave) {
            'resumen' => new HojaReporte('Resumen', ['Indicador', 'Valor'], function () use ($r, $pct) {
                $t = $r->totales();
                yield ['Período', ucfirst($r->etiqueta())];
                yield ['Volumen adjudicado (CLP)', $t['volumen']];
                yield ['Lotes cerrados', $t['lotes']];
                yield ['Lotes adjudicados', $t['vendidos']];
                yield ['Tasa de venta (%)', $pct($t['tasa'])];
                yield ['Sobreprecio medio (%)', $pct($t['sobreprecio'])];
                yield ['Pujas totales', $t['pujas']];
                yield ['Postores registrados (cuentas aprobadas)', $t['registrados']];
                yield ['Postores con garantía aprobada', $t['con_garantia']];
                yield ['Postores activos', $t['activos']];
                yield ['Adjudicatarios', $t['adjudicatarios']];
                yield ['Garantías rechazadas', $t['garantias_rechazadas']];
            }),
            'desempeno' => new HojaReporte('Desempeño comercial', [
                'Folio', 'Lote', 'Dirección', 'Comuna', 'Región', 'Categoría', 'Cierre', 'Precio base (CLP)', 'Precio final (CLP)',
                'Sobreprecio (%)', 'Pujas', 'Postores', 'Minutos primera a última puja', 'Cierre por', 'Resultado',
            ], function () use ($r, $pct, $fecha) {
                foreach ($r->desempeno() as $f) {
                    yield [$f['folio'], $f['lote'] ?? 1, $f['direccion'], $f['comuna'], $f['region'], $f['categoria'], $fecha($f['cerrado_en']),
                        $f['base'], $f['final'], $pct($f['sobreprecio']), $f['pujas'], $f['postores'],
                        $f['minutos'] === null ? null : round($f['minutos'], 1), $f['motivo'], $f['resultado']];
                }
            }, ['H' => self::PESOS, 'I' => self::PESOS]),
            'categorias' => new HojaReporte('Por categoría', ['Categoría', 'Lotes', 'Adjudicados', 'Volumen (CLP)', 'Sobreprecio medio (%)', 'Tasa de venta (%)'],
                function () use ($r, $pct) {
                    foreach ($r->categorias() as $c) {
                        yield [$c['categoria'], $c['lotes'], $c['vendidos'], $c['volumen'], $pct($c['sobreprecio']), $pct($c['tasa'])];
                    }
                }, ['D' => self::PESOS]),
            'participacion' => new HojaReporte('Participación', ['Folio', 'Remate', 'Lotes', 'Postores inscritos', 'Garantías aprobadas', 'Postores activos', 'Pujas', 'Lotes adjudicados'],
                function () use ($r) {
                    foreach ($r->participacionPorRemate() as $p) {
                        yield array_values($p);
                    }
                }),
            'pujas' => new HojaReporte('Pujas', ['Folio', 'Lote', 'Dirección', 'Recibida (hora de Chile)', 'Monto (CLP)', 'Postor'],
                function () use ($r, $fecha) {
                    foreach ($r->pujas() as $p) {
                        yield [$p['folio'], $p['lote'], $p['direccion'], $fecha($p['recibida_en'], 'd-m-Y H:i:s.v'), $p['monto'], $p['postor']];
                    }
                }, ['E' => self::PESOS]),
            'garantias' => new HojaReporte('Garantías', ['Folio', 'Remate', 'Inscritas', 'Pendientes', 'En revisión', 'Aprobadas', 'Rechazadas', 'Monto aprobado (CLP)'],
                function () use ($r) {
                    foreach ($r->garantiasPorRemate() as $g) {
                        yield array_values($g);
                    }
                }, ['H' => self::PESOS]),
        };
    }
}
