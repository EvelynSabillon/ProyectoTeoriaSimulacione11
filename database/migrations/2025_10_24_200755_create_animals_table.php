<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('animals')) {
            Schema::create('animals', function (Blueprint $table) {
            $table->id();
            $table->string('arete')->unique()->comment('Número de arete del animal');
            $table->foreignId('grupo_id')->nullable()->constrained('grupos')->onDelete('set null');
            $table->string('grupo_lote')->nullable()->comment('DEPRECADO: migrar a grupo_id');
            
            // Variables Fisiológicas
            $table->integer('edad_meses')->nullable()->comment('Edad en meses');
            $table->decimal('peso_kg', 8, 2)->nullable()->comment('Peso corporal en kg');
            $table->decimal('condicion_corporal', 3, 1)->nullable()->comment('BCS: 1-5');
            $table->integer('numero_partos')->default(0)->comment('Número de partos previos');
            $table->integer('dias_posparto')->nullable()->comment('Días desde último parto');
            $table->integer('dias_abiertos')->nullable()->comment('Días sin quedar gestante');
            
            // Historial Reproductivo
            $table->boolean('historial_abortos')->default(false);
            $table->integer('numero_abortos')->default(0);
            $table->boolean('enfermedades_reproductivas')->default(false);
            $table->text('descripcion_enfermedades')->nullable();
            
            // Estado Actual
            $table->enum('estado_reproductivo', ['activa', 'prenada', 'seca', 'descarte'])->default('activa')->comment('Estado reproductivo del animal');
            $table->datetime('fecha_ultimo_tratamiento')->nullable()->comment('Última aplicación de tratamiento');
            $table->boolean('activo')->default(true)->comment('Animal activo en el sistema');
            $table->text('observaciones')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
            
            // Índices
            $table->index('arete');
            $table->index('grupo_id');
            $table->index('grupo_lote');
            $table->index('activo');
            $table->index('estado_reproductivo');
        });
        } else {
            // Si la tabla ya existe, solo agregar columnas faltantes
            Schema::table('animals', function (Blueprint $table) {
                if (!Schema::hasColumn('animals', 'grupo_id')) {
                    $table->foreignId('grupo_id')->nullable()->after('grupo_lote')->constrained('grupos')->onDelete('set null');
                }
                if (!Schema::hasColumn('animals', 'estado_reproductivo')) {
                    $table->enum('estado_reproductivo', ['activa', 'prenada', 'seca', 'descarte'])->default('activa')->after('descripcion_enfermedades')->comment('Estado reproductivo del animal');
                }
                if (!Schema::hasColumn('animals', 'fecha_ultimo_tratamiento')) {
                    $table->datetime('fecha_ultimo_tratamiento')->nullable()->after('estado_reproductivo')->comment('Última aplicación de tratamiento');
                }
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('animals');
    }
};
