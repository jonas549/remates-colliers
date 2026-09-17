<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Middleware\HoraRecepcion;
use App\Models\Configuracion;
use App\Models\Lote;
use App\Models\Remate;
use App\Models\User;
use App\Remates\FormularioRemate;
use App\Remates\GestionRemates;
use App\Remates\PresentacionRemate;
use App\Subastas\Liquidador;
use App\Support\RegionesChile;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Subastas del panel (Bloque I): listado y creación con el diseño de «Admin Subastas», ficha de edición, publicación,
 * cancelación, cierre anticipado desde el listado y «Crear remate nuevo».
 */
class RematesController extends Controller
{
    public function __construct(private readonly GestionRemates $gestion) {}

    public function index(): View
    {
        $ahora = CarbonImmutable::now('UTC');
        $prioridad = [Remate::VISTA_EN_VIVO => 0, Remate::VISTA_PROXIMO => 1, Remate::VISTA_BORRADOR => 2];
        // En vivo primero, luego próximas por fecha, borradores y al final lo cerrado (lo más reciente arriba).
        $remates = Remate::with(['lotes', 'martillero', 'garantias'])->where('es_demostracion', false)->get()
            ->sortBy(fn (Remate $r) => [$prioridad[$r->estadoVisible($ahora)] ?? 3,
                ($prioridad[$r->estadoVisible($ahora)] ?? 3) === 3 ? -($r->abreEn()?->getTimestamp() ?? 0) : ($r->abreEn()?->getTimestamp() ?? PHP_INT_MAX)]);

        return view('admin.subastas', [
            'subastas' => $remates->map(fn (Remate $r) => PresentacionRemate::fila($r, $ahora) + [
                'urls' => [
                    'ficha' => route('admin.remates.show', $r),
                    'enVivo' => route('admin.remates.en-vivo', $r),
                    'cerrar' => route('admin.remates.cerrar-ahora', $r),
                    'republicar' => route('admin.remates.republicar', $r),
                ],
            ])->values()->all(),
            'formulario' => $this->datosFormulario(),
        ]);
    }

    /** Formulario del diseño: crea el remate con su primer lote. «Publicar» lo publica si no falta nada. */
    public function store(Request $request): RedirectResponse
    {
        FormularioRemate::normalizar($request);
        $validado = $request->validate(FormularioRemate::reglasRemate() + FormularioRemate::reglasLote(), [], FormularioRemate::atributos());

        // La duración y la descripción del formulario del diseño son del remate, no del lote.
        $datosLote = FormularioRemate::datosLote(collect($validado)->only(array_keys(FormularioRemate::reglasLote()))->except(['descripcion', 'duracion_minutos'])->all());
        $remate = $this->gestion->crear(FormularioRemate::datosRemate($validado), $datosLote);

        if ($request->input('accion') !== 'publicar') {
            return redirect()->route('admin.remates.show', $remate)->with('estado', "Remate {$remate->folio} guardado como borrador.");
        }
        try {
            $this->gestion->publicar($remate);
        } catch (DomainException $e) {
            return redirect()->route('admin.remates.show', $remate)
                ->with('error', "Remate {$remate->folio} guardado como borrador. Para publicarlo: " . $e->getMessage());
        }

        return redirect()->route('admin.remates.show', $remate)->with('estado', "Remate {$remate->folio} publicado.");
    }

    public function show(Remate $remate): View
    {
        $remate->load(['lotes.imagenes', 'martillero', 'documentos.lote', 'garantias', 'remateOrigen', 'republicaciones']);
        $ahora = CarbonImmutable::now('UTC');

        return view('admin.remates.ficha', [
            'remate' => $remate,
            'fila' => PresentacionRemate::fila($remate, $ahora),
            'vista' => $remate->estadoVisible($ahora),
            'editable' => $remate->condicionesEditables($ahora),
            'faltan' => $remate->estado === Remate::ESTADO_BORRADOR ? $this->gestion->faltantesParaPublicar($remate) : [],
            'formulario' => $this->datosFormulario(),
        ]);
    }

    public function update(Request $request, Remate $remate): RedirectResponse
    {
        FormularioRemate::normalizar($request);
        $validado = $request->validate(FormularioRemate::reglasRemate(), [], FormularioRemate::atributos());

        try {
            $this->gestion->actualizar($remate, FormularioRemate::datosRemate($validado));
        } catch (DomainException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return back()->with('estado', 'Datos del remate guardados.');
    }

    public function publicar(Remate $remate): RedirectResponse
    {
        try {
            $this->gestion->publicar($remate);
        } catch (DomainException $e) {
            return back()->with('error', 'No se puede publicar: ' . $e->getMessage());
        }

        return back()->with('estado', "Remate {$remate->folio} publicado. Ya aparece en el sitio y acepta inscripciones.");
    }

    public function cancelar(Request $request, Remate $remate): RedirectResponse
    {
        $motivo = $request->validate(['motivo' => ['required', 'string', 'max:500']], [], ['motivo' => 'motivo'])['motivo'];
        try {
            $this->gestion->cancelar($remate, $motivo);
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('estado', "Remate {$remate->folio} cancelado.");
    }

    /**
     * «Cerrar ahora» del listado: en vivo cierra anticipadamente el lote que se está rematando (la mejor puja se adjudica
     * pasado el margen); próximo o borrador, lo cancela.
     */
    public function cerrarAhora(Request $request, Remate $remate, Liquidador $liquidador): RedirectResponse
    {
        $motivo = $request->validate(['motivo' => ['required', 'string', 'max:500']], [], ['motivo' => 'motivo'])['motivo'];
        $ahora = HoraRecepcion::de($request);

        if ($remate->estadoVisible($ahora) !== Remate::VISTA_EN_VIVO) {
            return $this->cancelar($request, $remate);
        }

        $lote = $remate->lotes()->whereNotIn('estado', Lote::ESTADOS_TERMINALES)
            ->where('abre_en', '<=', $ahora->format('Y-m-d H:i:s'))->orderBy('orden')->first();
        if ($lote === null) {
            return back()->with('error', 'No hay un lote abierto en este momento: espera a que abra o a que termine de liquidarse.');
        }
        try {
            $lote = $liquidador->cerrarAnticipadamente($lote, $request->user(), $ahora);
            $lote->forceFill(['nota_cierre' => mb_substr(trim($motivo), 0, 500)])->save();
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('estado', "Lote {$lote->orden} de {$remate->folio} cerrado. La adjudicación se materializa en {$liquidador->margenSegundos()} s.");
    }

    public function republicar(Remate $remate): RedirectResponse
    {
        try {
            $nuevo = $this->gestion->republicar($remate);
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('admin.remates.show', $nuevo)
            ->with('estado', "Remate nuevo {$nuevo->folio} creado en borrador a partir de {$remate->folio}. Fija la fecha y publícalo.");
    }

    /** Opciones de los formularios y valores globales que se muestran como referencia. */
    private function datosFormulario(): array
    {
        return [
            'martilleros' => User::where('rol', User::ROL_MARTILLERO)->where('estado', User::ESTADO_ACTIVO)->orderBy('name')->pluck('name', 'id')->all(),
            'regiones' => RegionesChile::ORDEN,
            'tipos' => Lote::TIPOS_PROPIEDAD,
            'ocupaciones' => Lote::OCUPACIONES,
            'incremento' => (int) Configuracion::valor('incremento_minimo'),
            'porcentaje' => (string) Configuracion::valor('porcentaje_garantia'),
            'duracion' => (int) Configuracion::valor('duracion_lote_minutos'),
            'uf' => (float) (Configuracion::valor('uf_valor') ?? 0),
        ];
    }
}
