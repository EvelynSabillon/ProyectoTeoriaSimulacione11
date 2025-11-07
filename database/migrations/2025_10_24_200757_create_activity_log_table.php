<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained();
            
            // Información de la Actividad
            $table->string('accion')->comment('crear, editar, eliminar, predecir, etc.');
            $table->string('modelo_afectado')->comment('Animal, IATF, Prediction, etc.');
            $table->unsignedBigInteger('modelo_id')->nullable();
            
            // Detalles
            $table->text('descripcion')->nullable();
            $table->json('datos_anteriores')->nullable()->comment('Estado antes del cambio');
            $table->json('datos_nuevos')->nullable()->comment('Estado después del cambio');
            
            // Metadatos
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent();
            
            // Índices
            $table->index('user_id');
            $table->index('accion');
            $table->index('modelo_afectado');
            $table->index(['modelo_afectado', 'modelo_id']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_log');
    }
};
