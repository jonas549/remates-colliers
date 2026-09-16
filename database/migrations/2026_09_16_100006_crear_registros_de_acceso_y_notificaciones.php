<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Bitácoras que solo crecen: accesos (Bloque D) y notificaciones enviadas (Bloque M).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('access_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('email')->nullable()->index();
            // ingreso | ingreso_fallido | salida | bloqueo | cambio_clave | …
            $table->string('evento', 30)->index();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->json('detalle')->nullable();
            $table->dateTime('created_at')->index();
        });

        Schema::create('notificaciones_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('canal', 20)->default('correo');
            $table->string('tipo', 60)->index();
            $table->string('destinatario');
            $table->string('asunto')->nullable();
            // pendiente | enviada | fallida
            $table->string('estado', 20)->default('pendiente')->index();
            $table->text('error')->nullable();
            $table->nullableMorphs('notificable');
            $table->dateTime('enviada_en')->nullable();
            $table->datetimes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notificaciones_log');
        Schema::dropIfExists('access_logs');
    }
};
