<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * `users` guarda solo credenciales y rol (administrador, martillero, postor). Los datos del postor
 * (RUT cifrado, persona natural/jurídica, contacto) irán en tablas propias en el Bloque C.
 * Migración solo aditiva: columnas nuevas, nada se renombra ni se elimina.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('rol', 20)->default('postor')->after('password')->index();
            $table->string('estado', 20)->default('activo')->after('rol')->index();
            $table->boolean('debe_cambiar_clave')->default(false)->after('estado');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['rol']);
            $table->dropIndex(['estado']);
            $table->dropColumn(['rol', 'estado', 'debe_cambiar_clave']);
        });
    }
};
