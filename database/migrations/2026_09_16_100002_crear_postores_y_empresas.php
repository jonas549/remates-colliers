<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Datos del postor, fuera de `users` (que guarda solo credenciales y rol).
 *
 * RUT: cifrado (texto) + índice ciego HMAC (App\Support\Rut) para búsqueda y unicidad.
 * Supuesto vigente (16/09): una empresa = una cuenta; la cuenta es de la persona que actúa por ella
 * («calidad en que actúa»). La regla se valida en la aplicación, no con un índice único sobre
 * postores.empresa_id, para poder aceptar varios representantes sin migrar.
 * Campos del registro: ~20 del diseño; los que pida el cliente después van a `datos_extra`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('empresas', function (Blueprint $table) {
            $table->id();
            $table->text('rut');
            $table->char('rut_indice', 64)->unique();
            $table->string('razon_social');
            $table->string('giro')->nullable();
            $table->datetimes();
        });

        Schema::create('postores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->restrictOnDelete();
            // natural | juridica
            $table->string('tipo', 20)->default('natural');
            $table->string('nombres');
            $table->string('apellidos');
            $table->text('rut');
            $table->char('rut_indice', 64)->unique();
            $table->date('fecha_nacimiento')->nullable();
            $table->string('nacionalidad', 60)->nullable();
            $table->string('estado_civil', 30)->nullable();
            $table->string('telefono', 30)->nullable();
            $table->string('direccion')->nullable();
            $table->string('comuna', 80)->nullable();
            $table->string('region', 80)->nullable();
            $table->foreignId('empresa_id')->nullable()->constrained('empresas')->restrictOnDelete();
            $table->string('calidad', 50)->nullable();
            $table->string('origen', 50)->nullable();
            $table->dateTime('acepta_terminos_en')->nullable();
            // Máquina de estados en revisión con el cliente: texto, no enum, para cambiarla sin migrar.
            // registrado → en_revision → aprobado | rechazado; aprobado ↔ bloqueado
            $table->string('estado', 20)->default('registrado')->index();
            $table->foreignId('revisado_por_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->dateTime('revisado_en')->nullable();
            $table->text('motivo_rechazo')->nullable();
            $table->json('datos_extra')->nullable();
            $table->datetimes();
        });

        Schema::create('postor_documentos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('postor_id')->constrained('postores')->restrictOnDelete();
            // ci-frente | ci-dorso | domicilio | poder (ids del formulario de registro)
            $table->string('tipo', 40);
            $table->string('ruta');
            $table->string('nombre_original');
            $table->string('mime', 100)->nullable();
            $table->unsignedInteger('tamano_bytes')->nullable();
            $table->datetimes();
            $table->index(['postor_id', 'tipo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('postor_documentos');
        Schema::dropIfExists('postores');
        Schema::dropIfExists('empresas');
    }
};
