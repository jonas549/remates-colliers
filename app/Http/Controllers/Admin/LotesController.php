<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Lote;
use App\Models\LoteImagen;
use App\Models\Remate;
use App\Remates\FormularioRemate;
use App\Remates\GestionRemates;
use App\Support\Imagenes;
use App\Support\RegionesChile;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use RuntimeException;

/** Lotes de un remate (Bloque I): datos del activo, fotos y horarios de visita. Siempre dentro del remate de la ruta. */
class LotesController extends Controller
{
    public function __construct(private readonly GestionRemates $gestion) {}

    public function create(Remate $remate): View
    {
        return $this->formulario($remate, new Lote(['remate_id' => $remate->id]));
    }

    public function edit(Remate $remate, Lote $lote): View
    {
        return $this->formulario($remate, $this->delRemate($remate, $lote)->load(['imagenes', 'visitas']));
    }

    public function store(Request $request, Remate $remate): RedirectResponse
    {
        return $this->guardar($request, $remate, null);
    }

    public function update(Request $request, Remate $remate, Lote $lote): RedirectResponse
    {
        return $this->guardar($request, $remate, $this->delRemate($remate, $lote));
    }

    public function mover(Request $request, Remate $remate, Lote $lote): RedirectResponse
    {
        $direccion = $request->validate(['direccion' => ['required', 'in:subir,bajar']])['direccion'];

        try {
            $this->gestion->moverLote($remate, $this->delRemate($remate, $lote), $direccion);
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('estado', 'Orden de los lotes actualizado; horarios reprogramados.');
    }

    public function subirImagenes(Request $request, Remate $remate, Lote $lote): RedirectResponse
    {
        $this->delRemate($remate, $lote);
        $request->validate(
            ['imagenes' => ['required', 'array', 'max:20'], 'imagenes.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:15360']],
            ['imagenes.*.max' => 'Cada foto puede pesar hasta 15 MB.'],
            ['imagenes' => 'fotos', 'imagenes.*' => 'foto'],
        );

        $orden = (int) $lote->imagenes()->max('orden');
        $subidas = 0;
        $problemas = [];
        foreach ($request->file('imagenes') as $archivo) {
            try {
                $ruta = Imagenes::guardar($archivo, "remates/{$remate->id}/lotes/{$lote->id}");
            } catch (RuntimeException $e) {
                $problemas[] = $e->getMessage();
                continue;
            }
            $lote->imagenes()->create(['ruta' => $ruta, 'orden' => ++$orden, 'texto_alternativo' => $lote->direccion]);
            $subidas++;
        }

        $respuesta = back()->with('estado', $subidas === 1 ? 'Foto agregada.' : "{$subidas} fotos agregadas.");

        return $problemas ? $respuesta->with('error', implode(' ', $problemas)) : $respuesta;
    }

    /** La primera foto es la principal del listado y del detalle. */
    public function portada(Remate $remate, Lote $lote, LoteImagen $imagen): RedirectResponse
    {
        abort_unless($imagen->lote_id === $this->delRemate($remate, $lote)->id, 404);
        $lote->imagenes()->where('id', '!=', $imagen->id)->orderBy('orden')->get()
            ->each(fn (LoteImagen $i, int $n) => $i->update(['orden' => $n + 2]));
        $imagen->update(['orden' => 1]);

        return back()->with('estado', 'Foto principal actualizada.');
    }

    public function borrarImagen(Remate $remate, Lote $lote, LoteImagen $imagen): RedirectResponse
    {
        abort_unless($imagen->lote_id === $this->delRemate($remate, $lote)->id, 404);
        if ($imagen->archivoPropio()) {
            Storage::disk('public')->delete($imagen->ruta);
        }
        $imagen->delete();

        return back()->with('estado', 'Foto eliminada.');
    }

    public function agregarVisita(Request $request, Remate $remate, Lote $lote): RedirectResponse
    {
        $this->delRemate($remate, $lote);
        $datos = $request->validate([
            'inicia_en' => ['required', 'date'],
            'termina_en' => ['required', 'date', 'after:inicia_en'],
            'notas' => ['nullable', 'string', 'max:255'],
        ], [], ['inicia_en' => 'inicio de la visita', 'termina_en' => 'término de la visita']);

        $lote->visitas()->create([
            'inicia_en' => FormularioRemate::utc($datos['inicia_en']),
            'termina_en' => FormularioRemate::utc($datos['termina_en']),
            'notas' => $datos['notas'] ?? null,
        ]);

        return back()->with('estado', 'Horario de visita agregado.');
    }

    public function borrarVisita(Remate $remate, Lote $lote, int $visita): RedirectResponse
    {
        $this->delRemate($remate, $lote)->visitas()->whereKey($visita)->firstOrFail()->delete();

        return back()->with('estado', 'Horario de visita eliminado.');
    }

    private function guardar(Request $request, Remate $remate, ?Lote $lote): RedirectResponse
    {
        FormularioRemate::normalizar($request);
        $validado = $request->validate(FormularioRemate::reglasLote(), [], FormularioRemate::atributos());

        try {
            $lote = $this->gestion->guardarLote($remate, $lote, FormularioRemate::datosLote($validado));
        } catch (DomainException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('admin.lotes.edit', [$remate, $lote])->with('estado', "Lote {$lote->orden} guardado.");
    }

    private function formulario(Remate $remate, Lote $lote): View
    {
        return view('admin.remates.lote', [
            'remate' => $remate,
            'lote' => $lote,
            'editable' => $remate->condicionesEditables(),
            'regiones' => RegionesChile::ORDEN,
            'tipos' => Lote::TIPOS_PROPIEDAD,
            'ocupaciones' => Lote::OCUPACIONES,
        ]);
    }

    /** Un lote de otro remate no existe en esta ruta. */
    private function delRemate(Remate $remate, Lote $lote): Lote
    {
        abort_unless($lote->remate_id === $remate->id, 404);

        return $lote;
    }
}
