<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Garantia;
use App\Models\Postor;
use App\Models\PostorDocumento;
use App\Postores\RevisionPostores;
use App\Support\Formato;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Postores del panel (Bloques G y H, diseño «Admin Postores»): una fila por inscripción (postor + remate) y una por cada
 * postor sin inscripciones. Aprobar y rechazar actúan sobre la CUENTA si todavía no está aprobada, y sobre la GARANTÍA
 * si la cuenta ya lo está. Las acciones responden JSON para actualizar la fila sin recargar (también desde el celular).
 */
class PostoresController extends Controller
{
    public const CUENTA = [
        Postor::ESTADO_REGISTRADO => 'Sin confirmar correo',
        Postor::ESTADO_EN_REVISION => 'En revisión',
        Postor::ESTADO_APROBADO => 'Aprobado',
        Postor::ESTADO_RECHAZADO => 'Rechazado',
        Postor::ESTADO_BLOQUEADO => 'Bloqueado',
    ];

    public const GARANTIA = [
        Garantia::ESTADO_PENDIENTE => 'Pendiente',
        Garantia::ESTADO_EN_REVISION => 'En revisión',
        Garantia::ESTADO_APROBADA => 'Aprobada',
        Garantia::ESTADO_RECHAZADA => 'Rechazada',
    ];

    public function __construct(private readonly RevisionPostores $revision) {}

    public function index(): View
    {
        return view('admin.postores', ['postores' => $this->filas()->values()->all()]);
    }

    public function aprobarCuenta(Request $request, Postor $postor): JsonResponse
    {
        return $this->ejecutar(fn () => $this->revision->aprobarCuenta($postor, $request->user()), 'Cuenta aprobada. El postor recibirá un correo.');
    }

    public function rechazarCuenta(Request $request, Postor $postor): JsonResponse
    {
        $motivo = $this->motivo($request);

        return $this->ejecutar(fn () => $this->revision->rechazarCuenta($postor, $request->user(), $motivo), 'Cuenta rechazada. El postor recibirá un correo con el motivo.');
    }

    public function bloquear(Request $request, Postor $postor): JsonResponse
    {
        $motivo = $this->motivo($request);

        return $this->ejecutar(fn () => $this->revision->bloquear($postor, $request->user(), $motivo), 'Cuenta bloqueada: no puede pujar ni inscribirse.');
    }

    public function desbloquear(Request $request, Postor $postor): JsonResponse
    {
        return $this->ejecutar(fn () => $this->revision->desbloquear($postor, $request->user()), 'Cuenta desbloqueada.');
    }

    public function aprobarGarantia(Request $request, Garantia $garantia): JsonResponse
    {
        return $this->ejecutar(fn () => $this->revision->aprobarGarantia($garantia, $request->user()), 'Garantía aprobada. El postor queda habilitado para pujar en este remate.');
    }

    public function rechazarGarantia(Request $request, Garantia $garantia): JsonResponse
    {
        $motivo = $this->motivo($request);

        return $this->ejecutar(fn () => $this->revision->rechazarGarantia($garantia, $request->user(), $motivo), 'Garantía rechazada. El postor puede corregir y volver a enviar el comprobante.');
    }

    public function comprobante(Garantia $garantia): StreamedResponse
    {
        abort_if(blank($garantia->comprobante_ruta), 404);

        return Storage::disk('local')->download($garantia->comprobante_ruta, $garantia->comprobante_nombre ?: 'comprobante');
    }

    /** «Exportar listado» del diseño: CSV con separador «;» (Excel en español lo abre directo). */
    public function exportar(): StreamedResponse
    {
        $filas = $this->filas();

        return response()->streamDownload(function () use ($filas) {
            $salida = fopen('php://output', 'w');
            fwrite($salida, "\xEF\xBB\xBF");
            fputcsv($salida, ['Postor', 'RUT', 'Tipo', 'Correo', 'Teléfono', 'Remate', 'Folio', 'Garantía (CLP)', 'Medio', 'Cuenta', 'Garantía', 'Actualizado'], ';');
            foreach ($filas as $f) {
                fputcsv($salida, [$f['nombre'], $f['rut'], $f['tipo'], $f['correo'], $f['telefono'], $f['remate'], $f['folio'], $f['monto'], $f['medio'], $f['cuenta'], $f['garantia'], $f['actualizado']], ';');
            }
            fclose($salida);
        }, 'postores-' . now('America/Santiago')->format('Y-m-d') . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function motivo(Request $request): string
    {
        return $request->validate(['motivo' => ['required', 'string', 'max:1000']], ['motivo.required' => 'Indica el motivo: el postor lo recibe por correo.'])['motivo'];
    }

    private function ejecutar(callable $accion, string $mensaje): JsonResponse
    {
        try {
            $accion();
        } catch (DomainException $e) {
            return response()->json(['ok' => false, 'mensaje' => $e->getMessage()], 422);
        }

        return response()->json(['ok' => true, 'mensaje' => $mensaje, 'postores' => $this->filas()->values()->all()]);
    }

    /** @return Collection<int, array> */
    private function filas(): Collection
    {
        $postores = Postor::with(['user', 'empresa', 'documentos', 'user.garantias' => fn ($q) => $q->with('remate')->latest('id')])
            ->latest('updated_at')->get();

        return $postores->flatMap(function (Postor $p) {
            $garantias = $p->user->garantias->filter(fn (Garantia $g) => ! $g->remate->es_demostracion);
            $base = $this->datosPostor($p);

            if ($garantias->isEmpty()) {
                return [$base + [
                    'id' => 'p' . $p->id, 'remate' => '—', 'folio' => '—', 'monto' => 0, 'medio' => 'Sin inscripción',
                    'garantia' => 'Sin inscripción', 'garantiaId' => null, 'comprobante' => null,
                    'actualizado' => $this->actualizado($p, null),
                ]];
            }

            // array_replace y no «+»: las URL de la garantía se suman a las de la cuenta (con «+» ganaría la clave de $base).
            return $garantias->map(fn (Garantia $g) => array_replace($base, [
                'id' => 'g' . $g->id,
                'remate' => $g->remate->titulo,
                'folio' => $g->remate->folio,
                'monto' => $g->monto,
                'medio' => ['vale_vista' => 'Vale a la vista', 'transferencia' => 'Transferencia'][$g->medio] ?? 'Sin comprobante',
                'garantia' => self::GARANTIA[$g->estado] ?? $g->estado,
                'garantiaId' => $g->id,
                'motivoGarantia' => $g->motivo_rechazo,
                'comprobante' => $g->tieneComprobante() ? route('admin.garantias.comprobante', $g) : null,
                'actualizado' => $this->actualizado($p, $g),
                'urls' => $base['urls'] + [
                    'aprobarGarantia' => route('admin.garantias.aprobar', $g),
                    'rechazarGarantia' => route('admin.garantias.rechazar', $g),
                ],
            ]))->all();
        });
    }

    private function datosPostor(Postor $p): array
    {
        return [
            'postorId' => $p->id,
            'nombre' => $p->empresa?->razon_social ?? $p->nombreCompleto(),
            'representante' => $p->empresa ? $p->nombreCompleto() . ($p->calidad ? ' · ' . $p->calidad : '') : null,
            'rut' => $p->empresa?->rut ?? $p->rut,
            'rutPersona' => $p->rut,
            'tipo' => $p->tipo === Postor::TIPO_JURIDICA ? 'Persona jurídica' : 'Persona natural',
            'correo' => $p->user->email,
            'telefono' => (string) $p->telefono,
            'domicilio' => collect([$p->direccion, $p->comuna, $p->region])->filter()->join(', '),
            'registro' => Formato::fecha($p->created_at),
            'cuenta' => self::CUENTA[$p->estado] ?? $p->estado,
            'cuentaEstado' => $p->estado,
            'motivoCuenta' => $p->motivo_rechazo,
            'documentos' => $p->documentos->map(fn (PostorDocumento $d) => [
                'nombre' => ['ci-frente' => 'Cédula (frente)', 'ci-dorso' => 'Cédula (dorso)', 'domicilio' => 'Comprobante de domicilio', 'poder' => 'Poder'][$d->tipo] ?? $d->tipo,
                'url' => route('admin.postores.documento', [$p, $d->id]),
            ])->values()->all(),
            'urls' => [
                'aprobarCuenta' => route('admin.postores.aprobar', $p),
                'rechazarCuenta' => route('admin.postores.rechazar', $p),
                'bloquear' => route('admin.postores.bloquear', $p),
                'desbloquear' => route('admin.postores.desbloquear', $p),
            ],
        ];
    }

    private function actualizado(Postor $p, ?Garantia $g): string
    {
        return match (true) {
            $p->estado !== Postor::ESTADO_APROBADO && $p->revisado_en !== null => ucfirst(mb_strtolower(self::CUENTA[$p->estado])) . ' el ' . Formato::fechaCorta($p->revisado_en),
            $p->estado !== Postor::ESTADO_APROBADO => 'Registro del ' . Formato::fechaCorta($p->created_at),
            $g === null => 'Cuenta aprobada, sin inscripciones',
            $g->estado === Garantia::ESTADO_APROBADA => 'Aprobada el ' . Formato::fechaCorta($g->revisado_en),
            $g->estado === Garantia::ESTADO_RECHAZADA => ($g->motivo_rechazo ? mb_strimwidth($g->motivo_rechazo, 0, 40, '…') . ' · ' : '') . Formato::fechaCorta($g->revisado_en),
            $g->comprobante_subido_en !== null => 'Comprobante del ' . Formato::fechaCorta($g->comprobante_subido_en),
            default => 'Inscrito el ' . Formato::fechaCorta($g->created_at),
        };
    }
}
