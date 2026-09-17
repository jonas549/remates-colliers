<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Reportes\ExportacionesReporte;
use App\Reportes\ReporteRemates;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;
use Maatwebsite\Excel\ExcelServiceProvider;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** Reportes post-evento con datos reales (diseño «Admin Reportes»). Administradores y martilleros: no hay datos personales. */
class ReportesController extends Controller
{
    public function index(Request $request): View
    {
        $reporte = $this->reporte($request);

        return view('admin.reportes', [
            'reporte' => $reporte,
            'opciones' => ReporteRemates::opciones(),
            'exportables' => ExportacionesReporte::HOJAS,
        ]);
    }

    /** `hoja` = libro (todas, solo XLSX) o una de ExportacionesReporte::HOJAS; `formato` = xlsx | csv. */
    public function exportar(Request $request, string $hoja, string $formato): BinaryFileResponse
    {
        abort_unless(($hoja === 'libro' && $formato === 'xlsx') || (isset(ExportacionesReporte::HOJAS[$hoja]) && in_array($formato, ['xlsx', 'csv'], true)), 404);
        $reporte = $this->reporte($request);

        // El paquete no se autodescubre: se registra solo aquí para no cargar sus archivos en cada petición (sin OPcache).
        app()->register(ExcelServiceProvider::class);
        $exportacion = new ExportacionesReporte($reporte);
        $nombre = 'reporte-' . ($hoja === 'libro' ? 'remates' : $hoja) . '-' . $reporte->periodo() . '.' . $formato;

        return Excel::download($hoja === 'libro' ? $exportacion : $exportacion->hoja($hoja), $nombre,
            $formato === 'csv' ? \Maatwebsite\Excel\Excel::CSV : \Maatwebsite\Excel\Excel::XLSX);
    }

    private function reporte(Request $request): ReporteRemates
    {
        $periodo = (string) $request->query('periodo', '');
        try {
            return new ReporteRemates($periodo !== '' ? $periodo : ReporteRemates::periodoPorDefecto());
        } catch (InvalidArgumentException) {
            abort(404);
        }
    }
}
