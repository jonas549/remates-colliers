<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Plantillas de correo editables desde el panel (Bloque V, 17/09). Solo guarda las EDITADAS: si una plantilla no
 * tiene fila, se usa la del código (App\Correo\Plantillas::CATALOGO), que es también la que restaura el botón.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plantillas_correo', function (Blueprint $table) {
            $table->id();
            $table->string('clave', 60)->unique();
            $table->string('asunto');
            $table->text('cuerpo');
            $table->string('boton')->nullable();
            $table->foreignId('actualizado_por_id')->nullable()->constrained('users')->nullOnDelete();
            $table->datetimes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plantillas_correo');
    }
};
