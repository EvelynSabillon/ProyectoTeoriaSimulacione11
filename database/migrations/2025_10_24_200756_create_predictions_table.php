<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('predictions')) {
            Schema::create('predictions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('iatf_record_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained();
            
            // Resultado de la Predicción
            $table->decimal('probabilidad_prenez', 5, 4)->comment('0.0000 a 1.0000');
            $table->boolean('prediccion_binaria')->comment('0=No preñez, 1=Preñez');
            $table->enum('nivel_confianza', ['bajo', 'medio', 'alto'])->nullable();
            $table->decimal('confianza_baja', 5, 4)->nullable()->comment('Límite inferior confianza');
            $table->decimal('confianza_alta', 5, 4)->nullable()->comment('Límite superior confianza');
            
            // Metadatos del Modelo
            $table->string('modelo_usado')->comment('RandomForest, XGBoost, etc.');
            $table->string('version_modelo')->nullable();
            $table->decimal('accuracy', 5, 4)->nullable();
            $table->decimal('precision', 5, 4)->nullable();
            $table->decimal('recall', 5, 4)->nullable();
            $table->decimal('f1_score', 5, 4)->nullable();
            $table->decimal('roc_auc', 5, 4)->nullable();
            
            // Features más importantes (JSON)
            $table->json('top_features')->nullable()->comment('Variables más influyentes');
            
            // Recomendaciones
            $table->text('recomendaciones')->nullable()->comment('Sugerencias del sistema');
            
            // Validación posterior
            $table->boolean('resultado_real')->nullable()->comment('Resultado confirmado después');
            $table->boolean('prediccion_correcta')->nullable();
            $table->date('fecha_validacion')->nullable();
            
            $table->timestamps();
            
            // Índices
            $table->index('iatf_record_id');
            $table->index('prediccion_binaria');
            $table->index('nivel_confianza');
        });
        } else {
            // Si la tabla ya existe, solo agregar columnas faltantes
            Schema::table('predictions', function (Blueprint $table) {
                if (!Schema::hasColumn('predictions', 'confianza_baja')) {
                    $table->decimal('confianza_baja', 5, 4)->nullable()->after('nivel_confianza')->comment('Límite inferior confianza');
                }
                if (!Schema::hasColumn('predictions', 'confianza_alta')) {
                    $table->decimal('confianza_alta', 5, 4)->nullable()->after('confianza_baja')->comment('Límite superior confianza');
                }
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('predictions');
    }
};