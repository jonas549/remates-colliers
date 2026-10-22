<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Segundo factor por correo para el panel (22/10). Solo tablas nuevas.
 *
 * - `codigos_ingreso`: cada código enviado, guardado como hash (nunca en claro), con vencimiento, intentos y uso.
 *   Sirve también para limitar los envíos por hora: no se borran al usarse.
 * - `dispositivos_confiables`: «recordar este dispositivo». La cookie lleva un token al azar; aquí va su hash.
 *   Se revoca desde «Sesiones activas» o al cambiar la contraseña.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('codigos_ingreso', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('codigo_hash');
            $table->dateTime('expira_en');
            $table->unsignedTinyInteger('intentos')->default(0);
            $table->dateTime('usado_en')->nullable();
            $table->dateTime('descartado_en')->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->timestamps();
            $table->index(['user_id', 'created_at']);
        });

        Schema::create('dispositivos_confiables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('token_hash', 64)->unique();
            $table->string('user_agent', 512)->nullable();
            $table->string('ip', 45)->nullable();
            $table->dateTime('expira_en');
            $table->dateTime('ultimo_uso_en')->nullable();
            $table->dateTime('revocado_en')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'expira_en']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dispositivos_confiables');
        Schema::dropIfExists('codigos_ingreso');
    }
};
