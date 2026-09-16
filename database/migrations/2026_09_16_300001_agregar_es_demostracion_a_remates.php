<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Marca los remates creados por `colliers:remate-demo` (sandbox): el listado público y los reportes los excluyen.
 * Migración aditiva: columna con valor por defecto.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('remates', function (Blueprint $table) {
            $table->boolean('es_demostracion')->default(false)->index();
        });
    }

    public function down(): void
    {
        Schema::table('remates', function (Blueprint $table) {
            $table->dropIndex(['es_demostracion']);
            $table->dropColumn('es_demostracion');
        });
    }
};
