<?php

namespace App\Http\Controllers\Admin;

use App\Correo\Plantillas;
use App\Http\Controllers\Controller;
use App\Models\PlantillaCorreo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Configuración → Plantillas de correo (Bloque V, 17/09). El original vive en el código; aquí solo se guardan las
 * plantillas editadas, y «Restaurar la original» borra la fila.
 */
class PlantillasCorreoController extends Controller
{
    public function edit(Request $request, string $clave): View
    {
        abort_unless(isset(Plantillas::CATALOGO[$clave]), 404);
        $vigente = Plantillas::vigente($clave);

        return view('admin.configuracion.plantilla', [
            'clave' => $clave,
            'catalogo' => Plantillas::CATALOGO[$clave],
            'plantilla' => $vigente,
            'variables' => Plantillas::variables($clave),
            'editada' => PlantillaCorreo::where('clave', $clave)->with('actualizadoPor')->first(),
            // La vista previa usa lo recién escrito si viene de «Ver la vista previa»; si no, lo guardado.
            'vista' => Plantillas::render($clave, Plantillas::ejemplo($clave)),
            'previa' => $request->session()->get('previa'),
        ]);
    }

    public function update(Request $request, string $clave): RedirectResponse
    {
        abort_unless(isset(Plantillas::CATALOGO[$clave]), 404);
        $datos = $this->validar($request, $clave);

        PlantillaCorreo::updateOrCreate(['clave' => $clave], $datos + ['actualizado_por_id' => $request->user()->id]);

        return redirect()->route('admin.configuracion.plantilla', $clave)
            ->with('estado', 'Plantilla guardada. Los correos que salgan desde ahora usan este texto.');
    }

    /** Vista previa con datos de ejemplo, sin guardar. */
    public function previsualizar(Request $request, string $clave): RedirectResponse
    {
        abort_unless(isset(Plantillas::CATALOGO[$clave]), 404);
        $datos = $this->validar($request, $clave);
        $valores = Plantillas::ejemplo($clave);
        $reemplazar = fn (?string $texto) => $texto === null ? null : preg_replace_callback(
            '/\{\{\s*([a-z_]+)\s*\}\}/i', fn (array $m) => (string) ($valores[$m[1]] ?? ''), $texto);

        return back()->withInput()->with('previa', [
            'asunto' => (string) $reemplazar($datos['asunto']),
            'parrafos' => collect(preg_split('/\R{2,}/', trim((string) $reemplazar($datos['cuerpo']))))->map(fn ($p) => trim($p))->filter()->values()->all(),
            'boton' => $datos['boton'] ? (string) $reemplazar($datos['boton']) : null,
        ]);
    }

    /** Vuelve al texto original del código: se borra la versión editada. */
    public function destroy(string $clave): RedirectResponse
    {
        abort_unless(isset(Plantillas::CATALOGO[$clave]), 404);
        PlantillaCorreo::where('clave', $clave)->delete();

        return redirect()->route('admin.configuracion.plantilla', $clave)->with('estado', 'Plantilla restaurada a la original.');
    }

    /** @return array{asunto: string, cuerpo: string, boton: ?string} */
    private function validar(Request $request, string $clave): array
    {
        $reglas = [
            'asunto' => ['required', 'string', 'max:150'],
            'cuerpo' => ['required', 'string', 'max:4000'],
            'boton' => [Plantillas::CATALOGO[$clave]['boton'] === null ? 'nullable' : 'required', 'nullable', 'string', 'max:60'],
        ];
        $datos = $request->validate($reglas, [], ['asunto' => 'asunto', 'cuerpo' => 'cuerpo', 'boton' => 'texto del botón']);

        return ['asunto' => $datos['asunto'], 'cuerpo' => $datos['cuerpo'], 'boton' => $datos['boton'] ?? null];
    }
}
