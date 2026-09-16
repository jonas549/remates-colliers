<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Garantías: proceso 100% manual y externo (vale a la vista o transferencia). Sin pasarela de pago.
 *
 * Supuesto vigente (16/09): garantía POR REMATE (el acta dice «garantía aprobada para ese remate»);
 * la base del porcentaje es la suma de los precios base de los lotes. Monto, porcentaje y base se
 * guardan al crear la garantía para que un cambio de configuración no altere garantías existentes.
 * `lote_id` queda disponible (nulo) por si el cliente define garantía por lote: sería solo lógica.
 * Sin índice único (postor, remate): la unicidad se valida en la aplicación por la misma razón.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('garantias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('remate_id')->constrained('remates')->restrictOnDelete();
            $table->foreignId('lote_id')->nullable()->constrained('lotes')->restrictOnDelete();
            $table->unsignedBigInteger('monto');
            $table->decimal('porcentaje', 5, 2);
            $table->unsignedBigInteger('base_calculo');
            // pendiente → en_revision → aprobada | rechazada; aprobada → devuelta | imputada | ejecutada
            $table->string('estado', 20)->default('pendiente')->index();
            // vale_vista | transferencia
            $table->string('medio', 20)->nullable();
            $table->string('comprobante_ruta')->nullable();
            $table->string('comprobante_nombre')->nullable();
            $table->dateTime('comprobante_subido_en')->nullable();
            $table->foreignId('revisado_por_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->dateTime('revisado_en')->nullable();
            $table->text('motivo_rechazo')->nullable();
            $table->text('notas_internas')->nullable();
            $table->datetimes();

            $table->index(['remate_id', 'estado']);
            $table->index(['user_id', 'remate_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('garantias');
    }
};
