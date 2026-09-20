<?php

namespace App\Models;

use App\Casts\FechaUtc;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * Garantía de un postor para un remate. Proceso manual y externo: sin pasarela de pago.
 * Monto, porcentaje y base quedan fijos al crearla (ver Remate::montoGarantia()).
 */
#[Table('garantias')]
#[Fillable([
    'user_id', 'remate_id', 'lote_id', 'monto', 'porcentaje', 'base_calculo', 'medio',
    'comprobante_ruta', 'comprobante_nombre', 'comprobante_subido_en',
])]
class Garantia extends Model
{
    public const ESTADO_PENDIENTE = 'pendiente';

    public const ESTADO_EN_REVISION = 'en_revision';

    public const ESTADO_APROBADA = 'aprobada';

    public const ESTADO_RECHAZADA = 'rechazada';

    /** Alcance: la garantía vale para su remate (acta) o habilita al postor en todos (modo único del panel). */
    public const ALCANCE_REMATE = 'remate';

    public const ALCANCE_UNICO = 'unico';

    public const MEDIO_VALE_VISTA = 'vale_vista';

    public const MEDIO_TRANSFERENCIA = 'transferencia';

    protected function casts(): array
    {
        return [
            'monto' => 'integer',
            'base_calculo' => 'integer',
            'porcentaje' => 'decimal:2',
            'comprobante_subido_en' => FechaUtc::class,
            'revisado_en' => FechaUtc::class,
            'created_at' => FechaUtc::class,
            'updated_at' => FechaUtc::class,
        ];
    }

    /** Crea la garantía pendiente de un postor con el monto vigente del remate. */
    public static function paraRemate(Remate $remate, User $postor): self
    {
        return self::create([
            'user_id' => $postor->id,
            'remate_id' => $remate->id,
            'monto' => $remate->montoGarantia(),
            'porcentaje' => $remate->porcentajeGarantia(),
            'base_calculo' => $remate->baseGarantia(),
        ]);
    }

    /**
     * Habilitación única (modo del panel): un solo monto fijo, definido por Colliers, que vale para todos los
     * remates. Se guarda el remate desde el que se constituyó, para saber de dónde salió y para que la revisión
     * y el comprobante funcionen igual que siempre.
     */
    public static function habilitacionUnica(Remate $origen, User $postor, int $monto): self
    {
        $garantia = self::create([
            'user_id' => $postor->id,
            'remate_id' => $origen->id,
            'monto' => $monto,
            // Sin remate no hay precio base que porcentuar: van en 0 («no aplica»); `alcance` distingue el caso.
            'porcentaje' => 0,
            'base_calculo' => 0,
        ]);
        $garantia->forceFill(['alcance' => self::ALCANCE_UNICO])->save();

        return $garantia;
    }

    public function esUnica(): bool
    {
        return $this->alcance === self::ALCANCE_UNICO;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function remate(): BelongsTo
    {
        return $this->belongsTo(Remate::class);
    }

    public function revisadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revisado_por_id');
    }

    /** El archivo existe en el disco privado (los datos de ejemplo del seeder traen rutas sin archivo). */
    public function tieneComprobante(): bool
    {
        return filled($this->comprobante_ruta) && Storage::disk('local')->exists($this->comprobante_ruta);
    }

    public function estaAprobada(): bool
    {
        return $this->estado === self::ESTADO_APROBADA;
    }
}
