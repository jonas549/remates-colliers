<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Alcance de la garantía (decisión de Jonas, 20/09): `remate` es lo vigente —una garantía por remate, como el acta—
 * y `unico` habilita al postor en TODOS los remates con una sola garantía. El modo se elige desde el panel y viene
 * apagado; el monto de la habilitación única lo fija Colliers.
 *
 * Aditiva: columna nueva con valor por defecto. Las garantías existentes quedan como están.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('garantias', function (Blueprint $table) {
            $table->string('alcance', 20)->default('remate')->after('remate_id');
            $table->index(['user_id', 'alcance', 'estado']);
        });
    }

    public function down(): void
    {
        Schema::table('garantias', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'alcance', 'estado']);
            $table->dropColumn('alcance');
        });
    }
};
