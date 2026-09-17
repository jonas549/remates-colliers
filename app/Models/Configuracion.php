<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Valores de negocio editables desde el panel (Bloque V). Los valores por defecto se siembran con
 * `colliers:instalar` y solo si la clave no existe: nunca pisan lo que un administrador cambió.
 */
#[Table('configuraciones')]
#[Fillable(['clave', 'valor', 'tipo', 'grupo', 'descripcion'])]
class Configuracion extends Model
{
    /** Solo valores confirmados en el acta, en el diseño o en decisiones de Jonas (CLAUDE.md §3 y §7). */
    public const DEFECTOS = [
        'incremento_minimo' => ['valor' => '100000', 'tipo' => 'entero', 'grupo' => 'pujas', 'descripcion' => 'Incremento mínimo global entre pujas, en pesos'],
        'pujas_rapidas' => ['valor' => '[100000,500000,1000000]', 'tipo' => 'json', 'grupo' => 'pujas', 'descripcion' => 'Montos de los botones de puja rápida, en pesos'],
        'margen_liquidacion_segundos' => ['valor' => '2', 'tipo' => 'entero', 'grupo' => 'pujas', 'descripcion' => 'Segundos después del cierre para terminar las pujas recibidas antes de T'],
        'porcentaje_garantia' => ['valor' => '10', 'tipo' => 'porcentaje', 'grupo' => 'garantias', 'descripcion' => 'Porcentaje de la garantía sobre el precio base del remate'],
        'login_intentos_maximos' => ['valor' => '5', 'tipo' => 'entero', 'grupo' => 'seguridad', 'descripcion' => 'Intentos fallidos de ingreso antes de bloquear la cuenta (diseño del Login)'],
        'login_bloqueo_minutos' => ['valor' => '15', 'tipo' => 'entero', 'grupo' => 'seguridad', 'descripcion' => 'Minutos que dura el bloqueo por intentos fallidos (decisión del 16/09)'],
        'duracion_lote_minutos' => ['valor' => '30', 'tipo' => 'entero', 'grupo' => 'remates', 'descripcion' => 'Duración por defecto de cada lote (temporizador fijo, sin extensiones)'],
        'garantias_cierre_horas_antes' => ['valor' => '48', 'tipo' => 'entero', 'grupo' => 'garantias', 'descripcion' => 'Horas antes del inicio en que cierra la recepción de garantías, si el remate no fija otra fecha (diseño: «hasta 48 horas antes»)'],
    ];

    /** Crea las claves que faltan con su valor por defecto. Devuelve cuántas creó. Idempotente. */
    public static function sembrarDefectos(): int
    {
        $creadas = 0;
        foreach (self::DEFECTOS as $clave => $datos) {
            $creadas += static::firstOrCreate(['clave' => $clave], $datos)->wasRecentlyCreated ? 1 : 0;
        }

        return $creadas;
    }

    /**
     * Valores leídos hace menos de MEMORIA_SEGUNDOS: una puja consultaba la misma clave 3–4 veces. Vida corta a propósito:
     * el cron y la cola viven hasta 50 s y deben ver un cambio hecho desde el panel. Guardar desde el panel la vacía.
     *
     * @var array<string, array{0: float, 1: ?self}>
     */
    private static array $memoria = [];

    private const MEMORIA_SEGUNDOS = 2.0;

    protected static function booted(): void
    {
        static::saved(fn () => self::olvidar());
        static::deleted(fn () => self::olvidar());
    }

    public static function olvidar(): void
    {
        self::$memoria = [];
    }

    public static function valor(string $clave, mixed $defecto = null): mixed
    {
        $recordada = self::$memoria[$clave] ?? null;
        if ($recordada !== null && microtime(true) - $recordada[0] < self::MEMORIA_SEGUNDOS) {
            $fila = $recordada[1];
        } else {
            $fila = static::query()->where('clave', $clave)->first();
            self::$memoria[$clave] = [microtime(true), $fila];
        }

        if ($fila === null) {
            $base = self::DEFECTOS[$clave] ?? null;

            return $base === null ? $defecto : self::convertir($base['valor'], $base['tipo']);
        }

        return self::convertir($fila->valor, $fila->tipo);
    }

    private static function convertir(?string $valor, string $tipo): mixed
    {
        if ($valor === null) {
            return null;
        }

        return match ($tipo) {
            'entero' => (int) $valor,
            'booleano' => filter_var($valor, FILTER_VALIDATE_BOOL),
            'json' => json_decode($valor, true),
            default => $valor,
        };
    }
}
