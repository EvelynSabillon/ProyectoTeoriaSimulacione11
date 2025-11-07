<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sementales', function (Blueprint $table) {
            $table->id();
            $table->string('nombre')->unique()->comment('Nombre o ID del semental');
            $table->string('raza')->nullable();
            $table->string('codigo_pajilla')->nullable()->comment('Código del semen');
            
            // Calidad Seminal
            $table->decimal('calidad_seminal', 5, 2)->nullable()->comment('% motilidad');
            $table->decimal('concentracion_espermatica', 8, 2)->nullable()->comment('millones/ml');
            $table->decimal('morfologia_espermatica', 5, 2)->nullable()->comment('% normales');
            
            // Estadísticas Históricas
            $table->decimal('tasa_historica_prenez', 5, 2)->nullable()->comment('% de éxito');
            $table->integer('total_servicios')->default(0);
            $table->integer('total_preneces')->default(0);
            $table->integer('total_muertes_embrionarias')->default(0);
            
            // Proveedor
            $table->string('proveedor')->nullable();
            $table->date('fecha_adquisicion')->nullable();
            $table->decimal('precio_pajilla', 10, 2)->nullable();
            
            // Estado
            $table->boolean('activo')->default(true);
            $table->text('observaciones')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
            
            // Índices
            $table->index('nombre');
            $table->index('activo');
            $table->index('tasa_historica_prenez');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sementales');
    }
};