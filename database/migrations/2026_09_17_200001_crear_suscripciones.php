<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Bloque M: «Avísame» del sitio público. remate_id nulo = avisos de remates nuevos y del cierre de garantías; con valor =
 * recordatorio antes de que comience ese remate. Cada correo trae un enlace de baja con el token.
 * Migración aditiva: tabla nueva.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suscripciones', function (Blueprint $table) {
            $table->id();
            $table->string('email');
            $table->foreignId('remate_id')->nullable()->constrained('remates')->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->char('token', 40)->unique();
            $table->string('ip', 45)->nullable();
            $table->dateTime('baja_en')->nullable();
            $table->datetimes();

            $table->index(['email', 'remate_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('suscripciones');
    }
};
