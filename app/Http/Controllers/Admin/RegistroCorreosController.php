<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\NotificacionLog;
use App\Support\Formato;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Configuración → Registro de correos (17/09): historial completo de lo que la plataforma intentó enviar, con filtros
 * y el error entero. Es lo primero que se mira cuando alguien dice «no me llegó el correo».
 */
class RegistroCorreosController extends Controller
{
    /** @return array<string, mixed> Datos para la vista de la sección. */
    public function datos(Request $request): array
    {
        $totales = NotificacionLog::selectRaw('estado, count(*) as total')->groupBy('estado')->pluck('total', 'estado');

        return [
            'correos' => $this->filtrar($request)->latest('id')->paginate(50),
            'tipos' => NotificacionLog::distinct()->orderBy('tipo')->pluck('tipo'),
            'totales' => [
                'en total' => $totales->sum(),
                'aceptados por el servidor' => $totales['aceptada'] ?? 0,
                'solo registrados (no salieron)' => $totales['registrada'] ?? 0,
                'fallidos' => $totales['fallida'] ?? 0,
                'pendientes en la cola' => $totales['pendiente'] ?? 0,
                'sin verificar' => $totales['sin_verificar'] ?? 0,
            ],
        ];
    }

    public function exportar(Request $request): StreamedResponse
    {
        $filas = $this->filtrar($request)->latest('id')->cursor();

        return response()->streamDownload(function () use ($filas) {
            $salida = fopen('php://output', 'w');
            fwrite($salida, "\xEF\xBB\xBF");
            fputcsv($salida, ['Fecha (hora de Chile)', 'Correo', 'Tipo', 'Remitente', 'Destinatario', 'Estado', 'Transporte',
                'Respuesta del servidor', 'Message-ID', 'Aceptado por el servidor', 'Error'], ';');
            foreach ($filas as $c) {
                fputcsv($salida, [
                    $c->created_at?->setTimezone(Formato::ZONA)->format('d-m-Y H:i:s'),
                    $c->asunto, $c->tipo, $c->remitente, $c->destinatario,
                    NotificacionLog::ESTADOS[$c->estado] ?? $c->estado,
                    $c->transporte, $c->respuesta, $c->message_id,
                    $c->enviada_en?->setTimezone(Formato::ZONA)->format('d-m-Y H:i:s'),
                    $c->error,
                ], ';');
            }
            fclose($salida);
        }, 'correos-' . now(Formato::ZONA)->format('Y-m-d') . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function filtrar(Request $request): Builder
    {
        return NotificacionLog::query()
            ->when($request->query('estado'), fn (Builder $q, string $estado) => $q->where('estado', $estado))
            ->when($request->query('tipo'), fn (Builder $q, string $tipo) => $q->where('tipo', $tipo))
            ->when($request->query('desde'), fn (Builder $q, string $desde) => $q->where('created_at', '>=', $this->limite($desde)))
            ->when($request->query('hasta'), fn (Builder $q, string $hasta) => $q->where('created_at', '<', $this->limite($hasta, true)))
            ->when($request->query('q'), fn (Builder $q, string $texto) => $q->where(
                fn (Builder $b) => $b->where('destinatario', 'like', '%' . $texto . '%')->orWhere('asunto', 'like', '%' . $texto . '%')
            ));
    }

    /** La fecha se escribe en hora de Chile y se compara contra el UTC de la base. */
    private function limite(string $fecha, bool $finDelDia = false): string
    {
        $dia = \Carbon\CarbonImmutable::parse($fecha, Formato::ZONA)->startOfDay();

        return ($finDelDia ? $dia->addDay() : $dia)->utc()->format('Y-m-d H:i:s');
    }
}
