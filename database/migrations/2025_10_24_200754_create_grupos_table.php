<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grupos', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 50)->unique()->comment('Nombre del grupo/lote');
            $table->string('descripcion', 255)->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
            
            // Índice
            $table->index('activo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grupos');
    }
};
