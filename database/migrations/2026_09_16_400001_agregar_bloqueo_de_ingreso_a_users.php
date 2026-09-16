<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Bloque D: bloqueo tras intentos fallidos con el contador EN TABLA, no en caché (vaciar la caché no puede
 * desbloquear una cuenta). Migración aditiva: columnas con valor por defecto o nulas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedSmallInteger('intentos_fallidos')->default(0);
            $table->dateTime('bloqueado_hasta')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['intentos_fallidos', 'bloqueado_hasta']);
        });
    }
};
