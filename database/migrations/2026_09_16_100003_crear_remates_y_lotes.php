<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Remates y lotes. Modelo siempre con lotes: un remate de una propiedad es un remate con un lote.
 *
 * Supuestos vigentes (16/09, pendientes con el cliente; ninguno requiere migrar para cambiarse):
 * - Horario fijo: cada lote guarda `abre_en` y `cierra_en` absolutos (UTC), calculados al publicar con
 *   inicio + duración + pausa. Encadenar al cierre efectivo sería solo lógica sobre las mismas columnas.
 * - Duración por remate (`duracion_lote_segundos`), con valor opcional por lote (`duracion_segundos`).
 * - Cierre anticipado: adjudica la mejor puja (modal del diseño). Se registra `motivo_cierre` y quién cerró.
 * - Estados como texto, no enum: la máquina de estados está en revisión.
 *
 * `lotes` es la fila que bloquea el motor de pujas (lockForUpdate): guarda precio actual, ganador y cierre.
 * Montos en pesos, enteros. Fechas en UTC.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('remates', function (Blueprint $table) {
            $table->id();
            $table->string('folio', 20)->unique();
            $table->string('slug', 120)->unique();
            $table->string('titulo');
            $table->text('descripcion')->nullable();
            // borrador → publicado → en_curso → finalizado | cancelado
            $table->string('estado', 20)->default('borrador')->index();
            $table->dateTime('inicio_en')->nullable()->index();
            $table->dateTime('cierre_garantias_en')->nullable();
            // null = valor global de configuraciones
            $table->unsignedInteger('duracion_lote_segundos')->nullable();
            $table->unsignedInteger('pausa_entre_lotes_segundos')->default(0);
            $table->unsignedBigInteger('incremento_minimo')->nullable();
            $table->decimal('porcentaje_garantia', 5, 2)->nullable();
            $table->string('youtube_video_id', 20)->nullable();
            $table->foreignId('martillero_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->dateTime('publicado_en')->nullable();
            $table->dateTime('finalizado_en')->nullable();
            $table->dateTime('cancelado_en')->nullable();
            $table->datetimes();
        });

        Schema::create('lotes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('remate_id')->constrained('remates')->restrictOnDelete();
            $table->unsignedSmallInteger('orden')->default(1);
            // programado → abierto → liquidando → adjudicado | desierto; adjudicado → cerrado | incumplido
            $table->string('estado', 20)->default('programado')->index();

            // Activo
            $table->string('titulo');
            $table->string('tipo_propiedad', 50)->nullable()->index();
            $table->string('direccion')->nullable();
            $table->string('comuna', 80)->nullable()->index();
            $table->string('region', 80)->nullable()->index();
            $table->decimal('latitud', 10, 7)->nullable();
            $table->decimal('longitud', 10, 7)->nullable();
            $table->decimal('superficie_util', 10, 2)->nullable();
            $table->decimal('superficie_terraza', 10, 2)->nullable();
            $table->decimal('superficie_terreno', 10, 2)->nullable();
            $table->unsignedTinyInteger('dormitorios')->nullable();
            $table->unsignedTinyInteger('banos')->nullable();
            $table->unsignedTinyInteger('estacionamientos')->nullable();
            $table->boolean('bodega')->default(false);
            $table->string('ocupacion', 30)->nullable();
            $table->text('descripcion')->nullable();
            // Ficha extensible: año, orientación, piso, roles, gastos comunes, mandante, tipo de venta…
            $table->json('atributos')->nullable();

            // Subasta
            $table->unsignedBigInteger('precio_base');
            $table->unsignedInteger('duracion_segundos')->nullable();
            $table->dateTime('abre_en')->nullable();
            $table->dateTime('cierra_en')->nullable()->index();
            $table->unsignedBigInteger('precio_actual')->nullable();
            $table->foreignId('ganador_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->unsignedInteger('total_pujas')->default(0);
            $table->dateTime('ultima_puja_en', 6)->nullable();
            $table->dateTime('cerrado_en', 6)->nullable();
            // tiempo | anticipado
            $table->string('motivo_cierre', 20)->nullable();
            $table->foreignId('cerrado_por_id')->nullable()->constrained('users')->restrictOnDelete();
            // Si el remate no se concreta se crea uno nuevo: el lote nuevo apunta al original.
            $table->foreignId('lote_origen_id')->nullable()->constrained('lotes')->restrictOnDelete();
            $table->datetimes();

            $table->index(['remate_id', 'orden']);
        });

        Schema::create('lote_imagenes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lote_id')->constrained('lotes')->restrictOnDelete();
            $table->string('ruta');
            $table->unsignedSmallInteger('orden')->default(1);
            $table->string('texto_alternativo')->nullable();
            $table->string('credito')->nullable();
            $table->datetimes();
            $table->index(['lote_id', 'orden']);
        });

        Schema::create('lote_visitas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lote_id')->constrained('lotes')->restrictOnDelete();
            $table->dateTime('inicia_en');
            $table->dateTime('termina_en');
            $table->string('notas')->nullable();
            $table->datetimes();
            $table->index(['lote_id', 'inicia_en']);
        });

        Schema::create('documentos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('remate_id')->constrained('remates')->restrictOnDelete();
            // null = documento del remate completo (bases); con valor = propio del lote
            $table->foreignId('lote_id')->nullable()->constrained('lotes')->restrictOnDelete();
            $table->string('titulo');
            $table->string('ruta');
            $table->string('nombre_original');
            $table->string('mime', 100)->nullable();
            $table->unsignedInteger('tamano_bytes')->nullable();
            $table->unsignedSmallInteger('orden')->default(1);
            $table->boolean('publico')->default(true);
            $table->datetimes();
        });
    }

    public function down(): void
    {
        // `lotes` se referencia a sí misma (lote_origen_id): con filas, SQLite rechaza el DROP.
        Schema::withoutForeignKeyConstraints(function () {
            Schema::dropIfExists('documentos');
            Schema::dropIfExists('lote_visitas');
            Schema::dropIfExists('lote_imagenes');
            Schema::dropIfExists('lotes');
            Schema::dropIfExists('remates');
        });
    }
};
