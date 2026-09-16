<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Valores de negocio editables desde el panel (CLAUDE.md §6, Bloque V). Los valores por defecto los
 * siembra `colliers:instalar`, nunca un seeder (el deploy no corre db:seed).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('configuraciones', function (Blueprint $table) {
            $table->id();
            $table->string('clave', 100)->unique();
            $table->text('valor')->nullable();
            // entero | porcentaje | texto | booleano | json
            $table->string('tipo', 20)->default('texto');
            $table->string('grupo', 50)->default('general')->index();
            $table->string('descripcion')->nullable();
            $table->datetimes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('configuraciones');
    }
};
