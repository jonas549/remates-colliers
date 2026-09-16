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
            'rut_empresa.required' => 'Ingresa el RUT de la empresa.',
            'documentos.*.required' => 'Adjunta este documento.',
            'documentos.*.mimes' => 'El documento debe ser JPG, PNG o PDF.',
            'documentos.*.max' => 'El documento no puede superar los 5 MB.',
            'telefono.regex' => 'Ingresa un teléfono válido, por ejemplo +56 9 1234 5678.',
            'fecha_nacimiento.before' => 'Debes ser mayor de edad.',
            'acepta.accepted' => 'Debes aceptar las bases y los términos para registrarte.',
        ], [
            'rut_empresa' => 'RUT de la empresa',
            'razon_social' => 'razón social',
            'calidad' => 'calidad en que actúa',
            'documentos.ci-frente' => 'cédula (frente)',
            'documentos.ci-dorso' => 'cédula (dorso)',
            'documentos.domicilio' => 'comprobante de domicilio',
            'documentos.poder' => 'poder o mandato',
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
                $fallar('El RUT no es válido: revisa el dígito verificador.');
            }
        };
    }

    private function reglaRutLibre(): \Closure
    {
        return function (string $atributo, mixed $valor, \Closure $fallar) {
            if (Rut::esValido((string) $valor) && Postor::porRut((string) $valor)->exists()) {
                $fallar('Ya existe una cuenta con este RUT. Si es tuya, ingresa o recupera tu contraseña.');
            }
        };
    }

    /** Supuesto vigente (16/09): una empresa = una cuenta. */
    private function reglaEmpresaLibre(): \Closure
    {
        return function (string $atributo, mixed $valor, \Closure $fallar) {
            if (Rut::esValido((string) $valor) && Empresa::porRut((string) $valor)->whereHas('postores')->exists()) {
                $fallar('Esta empresa ya tiene una cuenta registrada. Escribe a remates@colliers.cl si necesitas otro representante.');
            }
        };
    }
}
