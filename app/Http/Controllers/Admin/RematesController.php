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
use App\Remates\RemateDePrueba;
use App\Subastas\Liquidador;
use App\Support\Formato;
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

    public function index(Request $request): View
    {
        $ahora = CarbonImmutable::now('UTC');
        $prioridad = [Remate::VISTA_EN_VIVO => 0, Remate::VISTA_PROXIMO => 1, Remate::VISTA_BORRADOR => 2];
        // El martillero solo ve los remates a su cargo: ofrecerle el panel en vivo de otro terminaba en un 403.
        $user = $request->user();
        $esMartillero = $user->rol === User::ROL_MARTILLERO;
        // En vivo primero, luego próximas por fecha, borradores y al final lo cerrado (lo más reciente arriba).
        $remates = Remate::with(['lotes', 'martillero', 'garantias'])->where('es_demostracion', false)
            ->when($esMartillero, fn ($q) => $q->where('martillero_id', $user->id))->get()
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
            // Fuera de producción, las subastas de prueba van en una franja aparte: no ensucian el listado real.
            'pruebas' => app()->isProduction() || $esMartillero ? [] : Remate::with('lotes')->where('es_demostracion', true)
                ->orderByDesc('id')->get()
                ->map(fn (Remate $r) => [
                    'folio' => $r->folio,
                    'inicio' => Formato::fechaCorta($r->inicio_en),
                    'lotes' => $r->lotes->count(),
                    'ficha' => route('admin.remates.show', $r),
                    'enVivo' => route('admin.remates.en-vivo', $r),
                    'borrar' => route('admin.remates.borrar-prueba', $r),
                ])->all(),
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

    /**
     * «Crear subasta de prueba» (QA del 20/09, punto 3): un remate publicado, con lotes, con postores habilitados y
     * con el inicio a unos minutos de ahora, para probar sin llenar quince campos. Queda marcado como prueba: fuera
     * del sitio público y de los reportes. No existe en producción.
     */
    public function crearPrueba(Request $request, RemateDePrueba $prueba): RedirectResponse
    {
        try {
            $creado = $prueba->crear(
                postores: (int) $request->integer('postores', 3),
                lotes: (int) $request->integer('lotes', 2),
                inicioMinutos: (int) $request->integer('inicio', 12),
            );
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        // La clave se muestra UNA vez, en la ficha del remate recién creado, junto a cada postor (21/09).
        return redirect()->to(route('admin.remates.show', $creado['remate']) . '#postores-prueba')
            ->with('estado', "Subasta de prueba {$creado['remate']->folio} creada y publicada.")
            ->with('clave_prueba', ['remate' => $creado['remate']->id, 'clave' => $creado['clave']]);
    }

    /** Clave común nueva para los postores de un remate de prueba, si la que se mostró al crearlo se perdió. */
    public function nuevaClavePrueba(Remate $remate, RemateDePrueba $prueba): RedirectResponse
    {
        try {
            $clave = $prueba->nuevaClave($remate);
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->to(route('admin.remates.show', $remate) . '#postores-prueba')
            ->with('estado', "Clave nueva para los postores de prueba de {$remate->folio}. La anterior ya no sirve.")
            ->with('clave_prueba', ['remate' => $remate->id, 'clave' => $clave]);
    }

    public function borrarPrueba(Remate $remate, RemateDePrueba $prueba): RedirectResponse
    {
        try {
            $folio = $remate->folio;
            $prueba->borrar($remate);
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('admin.subastas')->with('estado', "Subasta de prueba {$folio} borrada con sus postores.");
    }

    public function show(Request $request, Remate $remate): View
    {
        $remate->load(['lotes.imagenes', 'martillero', 'documentos.lote', 'garantias', 'remateOrigen', 'republicaciones']);
        $ahora = CarbonImmutable::now('UTC');
        // El martillero abre la ficha de SUS remates en modo lectura: necesita el horario, las fotos y los documentos
        // para preparar el remate, pero crear y editar es de administración.
        $user = $request->user();
        $puedeEditar = $user->esAdmin();
        abort_unless($puedeEditar || $remate->martillero_id === $user->id, 403,
            'El remate ' . $remate->folio . ' está a cargo de ' . ($remate->martillero?->name ?? 'otro martillero')
                . '. En «Subastas» aparecen los remates asignados a ti.');

        return view('admin.remates.ficha', [
            'remate' => $remate,
            // Postores de prueba con su clave (solo recién creada o regenerada: no se guarda en claro).
            'postoresPrueba' => $remate->es_demostracion && $puedeEditar ? app(RemateDePrueba::class)->postores($remate) : [],
            'clavePrueba' => (session('clave_prueba')['remate'] ?? null) === $remate->id ? session('clave_prueba')['clave'] : null,
            'puedeEditar' => $puedeEditar,
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
            // Para el resumen en palabras del calendario (qué pasa si el cierre de garantías se deja vacío).
            'horasGarantia' => (int) Configuracion::valor('garantias_cierre_horas_antes'),
        ];
    }
}
