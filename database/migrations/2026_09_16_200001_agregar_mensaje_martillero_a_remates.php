<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Bloque J: último mensaje del martillero a la sala, difundido en el estado del remate.
 * Migración aditiva: dos columnas nulas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('remates', function (Blueprint $table) {
            $table->string('mensaje_martillero', 500)->nullable();
            $table->dateTime('mensaje_martillero_en')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('remates', function (Blueprint $table) {
            $table->dropColumn(['mensaje_martillero', 'mensaje_martillero_en']);
        });
    }
};
