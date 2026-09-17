<?php

namespace App\Remates;

use App\Models\Configuracion;
use App\Models\Lote;
use App\Models\User;
use App\Support\RegionesChile;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Reglas y conversión de los formularios del panel de remates. Las fechas llegan en hora de Santiago
 * (`datetime-local`) y se guardan en UTC; las duraciones llegan en minutos y se guardan en segundos; los montos, enteros
 * de pesos (se aceptan con puntos: «120.000.000»).
 */
class FormularioRemate
{
    public const ZONA = 'America/Santiago';

    public static function reglasRemate(): array
    {
        return [
            'titulo' => ['nullable', 'string', 'max:200'],
            'descripcion' => ['nullable', 'string', 'max:5000'],
            'martillero_id' => ['nullable', Rule::exists('users', 'id')->where('rol', User::ROL_MARTILLERO)],
            'youtube_video_id' => ['nullable', 'string', 'regex:/^[A-Za-z0-9_-]{6,20}$/'],
            'inicio_en' => ['nullable', 'date'],
            'cierre_garantias_en' => ['nullable', 'date'],
            'duracion_minutos' => ['nullable', 'integer', 'min:1', 'max:600'],
            'pausa_minutos' => ['nullable', 'integer', 'min:0', 'max:600'],
            'incremento_minimo' => ['nullable', 'integer', 'min:1000'],
            'porcentaje_garantia' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ];
    }

    public static function reglasLote(): array
    {
        return [
            'direccion' => ['required', 'string', 'max:255'],
            'comuna' => ['required', 'string', 'max:80'],
            'region' => ['required', Rule::in(RegionesChile::ORDEN)],
            'tipo_propiedad' => ['required', Rule::in(Lote::TIPOS_PROPIEDAD)],
            'ocupacion' => ['nullable', Rule::in(Lote::OCUPACIONES)],
            'superficie_util' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'superficie_terraza' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'superficie_terreno' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'dormitorios' => ['nullable', 'integer', 'min:0', 'max:255'],
            'banos' => ['nullable', 'integer', 'min:0', 'max:255'],
            'estacionamientos' => ['nullable', 'integer', 'min:0', 'max:255'],
            'bodega' => ['nullable', 'boolean'],
            'latitud' => ['nullable', 'numeric', 'between:-90,90'],
            'longitud' => ['nullable', 'numeric', 'between:-180,180'],
            'descripcion' => ['nullable', 'string', 'max:5000'],
            'precio_base' => ['required', 'integer', 'min:1'],
            'duracion_minutos' => ['nullable', 'integer', 'min:1', 'max:600'],
            'atributos' => ['nullable', 'array'],
            'atributos.*' => ['nullable', 'string', 'max:255'],
        ];
    }

    public static function atributos(): array
    {
        return [
            'titulo' => 'título', 'descripcion' => 'descripción', 'martillero_id' => 'martillero', 'youtube_video_id' => 'ID del video de YouTube',
            'inicio_en' => 'inicio del remate', 'cierre_garantias_en' => 'cierre de garantías', 'duracion_minutos' => 'duración',
            'pausa_minutos' => 'pausa entre lotes', 'incremento_minimo' => 'incremento mínimo', 'porcentaje_garantia' => 'porcentaje de garantía',
            'tipo_propiedad' => 'tipo de propiedad', 'ocupacion' => 'ocupación', 'superficie_util' => 'superficie útil',
            'superficie_terraza' => 'superficie de terraza', 'superficie_terreno' => 'superficie de terreno', 'banos' => 'baños',
            'precio_base' => 'precio base', 'latitud' => 'latitud', 'longitud' => 'longitud',
        ];
    }

    /** Montos con separador de miles y comas decimales se normalizan antes de validar. */
    public static function normalizar(Request $request): void
    {
        $limpio = [];
        foreach (['precio_base', 'incremento_minimo'] as $campo) {
            if ($request->filled($campo)) {
                $limpio[$campo] = preg_replace('/[^\d]/', '', (string) $request->input($campo));
            }
        }
        foreach (['superficie_util', 'superficie_terraza', 'superficie_terreno', 'porcentaje_garantia', 'latitud', 'longitud'] as $campo) {
            if ($request->filled($campo)) {
                $texto = trim((string) $request->input($campo));
                // «1.234,5» (formato chileno) o «1234.5»: con coma, los puntos son de miles.
                $limpio[$campo] = str_contains($texto, ',') ? str_replace(['.', ','], ['', '.'], $texto) : $texto;
            }
        }
        $request->merge($limpio + ['bodega' => $request->boolean('bodega')]);
    }

    public static function datosRemate(array $validado): array
    {
        $datos = collect($validado)->only(['titulo', 'descripcion', 'martillero_id', 'youtube_video_id', 'incremento_minimo', 'porcentaje_garantia'])
            ->map(fn ($v) => $v === '' ? null : $v)->all();
        if (array_key_exists('titulo', $datos) && blank($datos['titulo'])) {
            unset($datos['titulo']); // el título es obligatorio en la base: vacío = se conserva (o sale de la dirección al crear)
        }
        foreach (['inicio_en', 'cierre_garantias_en'] as $campo) {
            if (array_key_exists($campo, $validado)) {
                $datos[$campo] = self::utc($validado[$campo]);
            }
        }
        if (array_key_exists('duracion_minutos', $validado)) {
            $datos['duracion_lote_segundos'] = $validado['duracion_minutos'] ? (int) $validado['duracion_minutos'] * 60 : null;
        }
        if (array_key_exists('pausa_minutos', $validado)) {
            // Vacío: la pausa por defecto de Configuración (17/09), como la duración del lote.
            $minutos = $validado['pausa_minutos'] === null || $validado['pausa_minutos'] === ''
                ? (int) Configuracion::valor('pausa_entre_lotes_minutos')
                : (int) $validado['pausa_minutos'];
            $datos['pausa_entre_lotes_segundos'] = $minutos * 60;
        }

        return $datos;
    }

    public static function datosLote(array $validado): array
    {
        $datos = collect($validado)->except(['duracion_minutos', 'atributos'])->all();
        if (array_key_exists('duracion_minutos', $validado)) {
            $datos['duracion_segundos'] = $validado['duracion_minutos'] ? (int) $validado['duracion_minutos'] * 60 : null;
        }
        if (array_key_exists('atributos', $validado)) {
            $datos['atributos'] = collect($validado['atributos'] ?? [])->only(array_keys(Lote::ATRIBUTOS))
                ->map(fn ($v) => is_string($v) ? trim($v) : $v)->filter(fn ($v) => $v !== null && $v !== '')->all() ?: null;
        }

        return $datos;
    }

    public static function utc(?string $local): ?CarbonImmutable
    {
        return blank($local) ? null : CarbonImmutable::parse($local, self::ZONA)->utc();
    }

    /** Valor para un input datetime-local, en hora de Santiago. */
    public static function local(?CarbonImmutable $utc): string
    {
        return $utc?->setTimezone(self::ZONA)->format('Y-m-d\TH:i') ?? '';
    }
}
