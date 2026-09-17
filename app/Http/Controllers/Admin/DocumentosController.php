<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Documento;
use App\Models\Remate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Documentos descargables de un remate o de uno de sus lotes (Bloque I). Se guardan en el disco privado: la descarga
 * pública pasa por la aplicación (Bloque N), que respeta la marca «público» (el resto solo lo ven postores con garantía
 * aprobada, como dice el diseño).
 */
class DocumentosController extends Controller
{
    public function store(Request $request, Remate $remate): RedirectResponse
    {
        $datos = $request->validate([
            'titulo' => ['required', 'string', 'max:200'],
            'archivo' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx', 'max:20480'],
            'lote_id' => ['nullable', Rule::exists('lotes', 'id')->where('remate_id', $remate->id)],
            'publico' => ['nullable', 'boolean'],
        ], ['archivo.max' => 'El archivo puede pesar hasta 20 MB.'], ['titulo' => 'título', 'archivo' => 'archivo', 'lote_id' => 'lote']);

        $archivo = $request->file('archivo');
        $ruta = $archivo->storeAs("documentos/remates/{$remate->id}", Str::uuid() . '.' . ($archivo->guessExtension() ?: 'pdf'), 'local');
        Documento::create([
            'remate_id' => $remate->id,
            'lote_id' => $datos['lote_id'] ?? null,
            'titulo' => $datos['titulo'],
            'ruta' => $ruta,
            'nombre_original' => mb_substr($archivo->getClientOriginalName(), 0, 255),
            'mime' => $archivo->getMimeType(),
            'tamano_bytes' => $archivo->getSize(),
            'orden' => (int) Documento::where('remate_id', $remate->id)->max('orden') + 1,
            'publico' => $request->boolean('publico'),
        ]);

        return back()->with('estado', 'Documento agregado.');
    }

    public function descargar(Remate $remate, Documento $documento): StreamedResponse
    {
        abort_unless($documento->remate_id === $remate->id, 404);

        return Storage::disk('local')->download($documento->ruta, $documento->nombre_original);
    }

    public function destroy(Remate $remate, Documento $documento): RedirectResponse
    {
        abort_unless($documento->remate_id === $remate->id, 404);
        // Un remate republicado comparte el archivo: se borra solo cuando nadie más lo usa.
        if (! Documento::where('ruta', $documento->ruta)->whereKeyNot($documento->id)->exists()) {
            Storage::disk('local')->delete($documento->ruta);
        }
        $documento->delete();

        return back()->with('estado', 'Documento eliminado.');
    }
}
