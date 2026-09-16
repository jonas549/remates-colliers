<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Registro de pujas (CLAUDE.md §5).
 * - `pujas`: solo pujas ACEPTADAS. Nunca se editan ni se borran (el modelo lo impide). Hora de recepción
 *   del servidor con microsegundos, IP y user agent.
 * - `puja_intentos`: rechazos con motivo. Se escriben FUERA de la transacción de la puja.
 * - `adjudicaciones`: una por lote adjudicado. Un lote desierto no tiene fila.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pujas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lote_id')->constrained('lotes')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->unsignedBigInteger('monto');
            $table->dateTime('recibida_en', 6);
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 512)->nullable();

            $table->index(['lote_id', 'monto']);
            $table->index(['lote_id', 'recibida_en']);
        });

        Schema::create('puja_intentos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lote_id')->nullable()->constrained('lotes')->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->unsignedBigInteger('monto')->nullable();
            $table->string('motivo', 40)->index();
            $table->json('detalle')->nullable();
            $table->dateTime('recibida_en', 6);
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 512)->nullable();

            $table->index(['lote_id', 'recibida_en']);
        });

        Schema::create('adjudicaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lote_id')->unique()->constrained('lotes')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('puja_id')->unique()->constrained('pujas')->restrictOnDelete();
            $table->unsignedBigInteger('monto');
            $table->dateTime('cerrado_en', 6);
            // tiempo | anticipado
            $table->string('motivo_cierre', 20);
            // adjudicado → cerrado | incumplido
            $table->string('estado', 20)->default('adjudicado')->index();
            $table->dateTime('notificado_ganador_en')->nullable();
            $table->dateTime('notificado_admin_en')->nullable();
            $table->datetimes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('adjudicaciones');
        Schema::dropIfExists('puja_intentos');
        Schema::dropIfExists('pujas');
    }
};
