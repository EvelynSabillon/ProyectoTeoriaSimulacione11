<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            
            // Tipo de Reporte
            $table->enum('tipo_reporte', [
                'tasas_prenez',
                'efectividad_protocolo',
                'analisis_semental',
                'rendimiento_modelo',
                'general'
            ]);
            
            // Parámetros del Reporte
            $table->date('fecha_inicio')->nullable();
            $table->date('fecha_fin')->nullable();
            $table->string('grupo_lote')->nullable();
            $table->json('filtros_aplicados')->nullable();
            
            // Resultados del Reporte (JSON)
            $table->json('data_resultados')->nullable()->comment('Datos calculados del reporte');
            
            // Estadísticas Generales
            $table->integer('total_animales')->nullable();
            $table->integer('total_iatf')->nullable();
            $table->decimal('tasa_prenez', 5, 2)->nullable()->comment('Porcentaje');
            $table->decimal('tasa_muerte_embrionaria', 5, 2)->nullable();
            
            // Archivo Generado
            $table->string('archivo_pdf')->nullable()->comment('Ruta del PDF generado');
            $table->string('archivo_excel')->nullable();
            
            $table->text('observaciones')->nullable();
            $table->timestamps();
            
            // Índices
            $table->index('user_id');
            $table->index('tipo_reporte');
            $table->index(['fecha_inicio', 'fecha_fin']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};
