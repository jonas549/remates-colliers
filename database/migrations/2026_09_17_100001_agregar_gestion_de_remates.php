<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Bloque I: gestión de remates desde el panel. Migración aditiva: columnas nulas.
 * - lotes.nota_cierre: motivo que escribe quien cierra anticipadamente (el modal del diseño lo pide).
 * - remates.motivo_cancelacion: por qué se canceló un remate publicado.
 * - remates.remate_origen_id: un remate que no se concreta no se reabre; el nuevo apunta al original
 *   (el listado público muestra «Se republicó en un remate nuevo»).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lotes', function (Blueprint $table) {
            $table->string('nota_cierre', 500)->nullable();
        });
        Schema::table('remates', function (Blueprint $table) {
            $table->string('motivo_cancelacion', 500)->nullable();
            $table->foreignId('remate_origen_id')->nullable()->constrained('remates')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('remates', function (Blueprint $table) {
            $table->dropForeign(['remate_origen_id']);
            $table->dropColumn(['motivo_cancelacion', 'remate_origen_id']);
        });
        Schema::table('lotes', function (Blueprint $table) {
            $table->dropColumn('nota_cierre');
        });
    }
};
