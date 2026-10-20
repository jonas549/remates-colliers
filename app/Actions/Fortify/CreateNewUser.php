<?php

namespace App\Actions\Fortify;

use App\Models\Empresa;
use App\Models\Postor;
use App\Models\User;
use App\Support\Rut;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\CreatesNewUsers;
use Throwable;

/**
 * Registro de postor (formulario del diseño, ~20 campos). La lista definitiva de campos está pendiente con el
 * cliente: lo que no tenga columna propia va a `postores.datos_extra`.
 *
 * - RUT con dígito verificador, único por índice ciego (una persona = una cuenta).
 * - Persona jurídica: supuesto vigente «una empresa = una cuenta», validado aquí y no en la base.
 * - Documentos en el disco privado (`local`), nunca en public; solo los descargan su dueño y la administración.
 * - La cuenta nace `registrado`; al verificar el correo pasa a `en_revision` (cola de aprobación de Colliers).
 */
class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    public const DOCUMENTOS = ['ci-frente', 'ci-dorso', 'domicilio', 'poder'];

    public const MAX_DOCUMENTO_KB = 5120;

    public function create(array $input): User
    {
        $juridica = ($input['tipo'] ?? null) === Postor::TIPO_JURIDICA;
        $mandatario = $juridica && ($input['calidad'] ?? null) === 'Mandatario con poder';
        $archivo = ['file', 'mimes:jpg,jpeg,png,pdf', 'max:' . self::MAX_DOCUMENTO_KB];

        $datos = Validator::make($input, [
            'tipo' => ['required', Rule::in([Postor::TIPO_NATURAL, Postor::TIPO_JURIDICA])],
            'nombres' => ['required', 'string', 'max:120'],
            'apellidos' => ['required', 'string', 'max:120'],
            'rut' => ['required', 'string', 'max:20', $this->reglaRut(), $this->reglaRutLibre()],
            'fecha_nacimiento' => ['nullable', 'date', 'before:-18 years'],
            'nacionalidad' => ['nullable', 'string', 'max:60'],
            'estado_civil' => ['nullable', 'string', 'max:30'],
            'razon_social' => [Rule::requiredIf($juridica), 'nullable', 'string', 'max:200'],
            'rut_empresa' => [Rule::requiredIf($juridica), 'nullable', 'string', 'max:20', $this->reglaRut(), $this->reglaEmpresaLibre()],
            'giro' => [Rule::requiredIf($juridica), 'nullable', 'string', 'max:200'],
            'calidad' => [Rule::requiredIf($juridica), 'nullable', Rule::in(['Representante legal', 'Mandatario con poder'])],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique(User::class, 'email')],
            'telefono' => ['required', 'string', 'max:30', 'regex:/^[0-9+()\s-]{8,30}$/'],
            'direccion' => ['required', 'string', 'max:200'],
            'comuna' => ['required', 'string', 'max:80'],
            'region' => ['required', 'string', 'max:80'],
            'origen' => ['nullable', 'string', 'max:50'],
            'documentos' => ['required', 'array'],
            'documentos.ci-frente' => ['required', ...$archivo],
            'documentos.ci-dorso' => ['required', ...$archivo],
            'documentos.domicilio' => ['required', ...$archivo],
            // Poder: obligatorio para persona jurídica (actúa por la empresa); opcional para persona natural.
            'documentos.poder' => [Rule::requiredIf($juridica || $mandatario), 'nullable', ...$archivo],
            'password' => $this->passwordRules(),
            'acepta' => ['accepted'],
        ], [
            'rut_empresa.required' => __('acceso.validacion.rut_empresa_requerido'),
            'documentos.*.required' => __('acceso.validacion.documento_requerido'),
            'documentos.*.mimes' => __('acceso.validacion.documento_tipo'),
            'documentos.*.max' => __('acceso.validacion.documento_tamano'),
            'telefono.regex' => __('acceso.validacion.telefono'),
            'fecha_nacimiento.before' => __('acceso.validacion.mayor_de_edad'),
            'acepta.accepted' => __('acceso.validacion.acepta'),
        ], [
            'rut_empresa' => __('acceso.validacion.atributos.rut_empresa'),
            'razon_social' => __('acceso.validacion.atributos.razon_social'),
            'calidad' => __('acceso.validacion.atributos.calidad'),
            'documentos.ci-frente' => __('acceso.validacion.atributos.ci-frente'),
            'documentos.ci-dorso' => __('acceso.validacion.atributos.ci-dorso'),
            'documentos.domicilio' => __('acceso.validacion.atributos.domicilio'),
            'documentos.poder' => __('acceso.validacion.atributos.poder'),
        ])->validate();

        $guardados = [];

        try {
            return DB::transaction(function () use ($datos, $juridica, &$guardados) {
                $user = User::create([
                    'name' => trim($datos['nombres'] . ' ' . $datos['apellidos']),
                    'email' => mb_strtolower($datos['email']),
                    'password' => $datos['password'],
                    'rol' => User::ROL_POSTOR,
                    'estado' => User::ESTADO_ACTIVO,
                ]);

                $empresa = $juridica ? Empresa::create([
                    'rut' => $datos['rut_empresa'], 'razon_social' => $datos['razon_social'], 'giro' => $datos['giro'],
                ]) : null;

                $postor = Postor::create([
                    'user_id' => $user->id,
                    'tipo' => $datos['tipo'],
                    'nombres' => $datos['nombres'],
                    'apellidos' => $datos['apellidos'],
                    'rut' => $datos['rut'],
                    'fecha_nacimiento' => $datos['fecha_nacimiento'] ?? null,
                    'nacionalidad' => $datos['nacionalidad'] ?? null,
                    'estado_civil' => $datos['estado_civil'] ?? null,
                    'telefono' => $datos['telefono'],
                    'direccion' => $datos['direccion'],
                    'comuna' => $datos['comuna'],
                    'region' => $datos['region'],
                    'empresa_id' => $empresa?->id,
                    'calidad' => $juridica ? $datos['calidad'] : null,
                    'origen' => $datos['origen'] ?? null,
                    'acepta_terminos_en' => CarbonImmutable::now('UTC'),
                ]);

                foreach (self::DOCUMENTOS as $tipo) {
                    $archivo = $datos['documentos'][$tipo] ?? null;
                    if (! $archivo instanceof UploadedFile) {
                        continue;
                    }
                    $ruta = $archivo->storeAs("postores/{$postor->id}", $tipo . '-' . bin2hex(random_bytes(8)) . '.' . strtolower($archivo->getClientOriginalExtension()), 'local');
                    $guardados[] = $ruta;
                    $postor->documentos()->create([
                        'tipo' => $tipo,
                        'ruta' => $ruta,
                        'nombre_original' => mb_substr($archivo->getClientOriginalName(), 0, 255),
                        'mime' => $archivo->getMimeType(),
                        'tamano_bytes' => $archivo->getSize(),
                    ]);
                }

                return $user;
            });
        } catch (Throwable $e) {
            // La transacción se revirtió: no dejar archivos huérfanos.
            Storage::disk('local')->delete($guardados);
            throw $e;
        }
    }

    private function reglaRut(): \Closure
    {
        return function (string $atributo, mixed $valor, \Closure $fallar) {
            if ($valor !== null && $valor !== '' && ! Rut::esValido((string) $valor)) {
                $fallar(__('acceso.validacion.rut_invalido'));
            }
        };
    }

    private function reglaRutLibre(): \Closure
    {
        return function (string $atributo, mixed $valor, \Closure $fallar) {
            if (Rut::esValido((string) $valor) && Postor::porRut((string) $valor)->exists()) {
                $fallar(__('acceso.validacion.rut_existente'));
            }
        };
    }

    /** Supuesto vigente (16/09): una empresa = una cuenta. */
    private function reglaEmpresaLibre(): \Closure
    {
        return function (string $atributo, mixed $valor, \Closure $fallar) {
            if (Rut::esValido((string) $valor) && Empresa::porRut((string) $valor)->whereHas('postores')->exists()) {
                $fallar(__('acceso.validacion.empresa_existente', ['correo' => \App\Support\Sitio::correo()]));
            }
        };
    }
}
