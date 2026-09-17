<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Bitácora de correos: guardar lo que respondió el transporte (17/09).
 *
 * Antes se anotaba «enviada» con solo saber que el canal no lanzó una excepción: con el transporte en modo registro
 * («log») el correo no salía a ninguna parte y la pantalla igual decía enviado. Ahora se guarda el transporte usado, la
 * respuesta literal del servidor, el identificador del mensaje y el remitente, y el estado dice lo que de verdad pasó:
 *
 *   pendiente · aceptada (el servidor de salida la aceptó) · registrada (quedó en el log, no salió) · fallida
 *
 * Las filas anteriores a este cambio pasan a `sin_verificar`: no hay forma de saber cuáles salieron de verdad.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notificaciones_log', function (Blueprint $table) {
            $table->string('transporte', 30)->nullable()->after('canal');
            $table->string('respuesta', 500)->nullable()->after('estado');
            $table->string('message_id')->nullable()->after('respuesta');
            $table->string('remitente')->nullable()->after('destinatario');
        });

        DB::table('notificaciones_log')->where('estado', 'enviada')->update(['estado' => 'sin_verificar']);
    }

    public function down(): void
    {
        DB::table('notificaciones_log')->whereIn('estado', ['aceptada', 'registrada', 'sin_verificar'])->update(['estado' => 'enviada']);

        Schema::table('notificaciones_log', function (Blueprint $table) {
            $table->dropColumn(['transporte', 'respuesta', 'message_id', 'remitente']);
        });
    }
};
