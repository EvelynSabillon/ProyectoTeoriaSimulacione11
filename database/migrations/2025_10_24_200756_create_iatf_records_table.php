<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('iatf_records')) {
            Schema::create('iatf_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('animal_id')->constrained()->onDelete('cascade');
            $table->foreignId('semental_id')->nullable()->constrained('sementales');
            $table->date('fecha_iatf')->comment('Fecha de inseminación');
            
            // Fechas del Protocolo IATF (por día)
            $table->datetime('fecha_protocolo_dia_0')->nullable()->comment('D0: Inicio protocolo');
            $table->datetime('fecha_protocolo_dia_8')->nullable()->comment('D8: Retiro DIB');
            $table->datetime('fecha_protocolo_dia_9')->nullable()->comment('D9: Aplicaciones pre-IATF');
            $table->datetime('fecha_protocolo_dia_10')->nullable()->comment('D10: Día IATF');
            
            // Condición Ovárica
            $table->enum('condicion_ovarica_od', ['C', 'CL', 'FD', 'F', 'I', 'A'])->nullable()->comment('Ovario derecho');
            $table->enum('condicion_ovarica_oi', ['C', 'CL', 'FD', 'F', 'I', 'A'])->nullable()->comment('Ovario izquierdo');
            $table->decimal('tono_uterino', 5, 2)->nullable()->comment('0-100%');
            
            // Tratamiento y Tonificación
            $table->enum('tratamiento_previo', ['T1', 'T2', 'RS', 'DESCARTE'])->nullable();
            $table->integer('dias_tonificacion')->nullable()->comment('30-45 días típico');
            
            // Nutrición y Suplementos
            $table->decimal('sal_mineral_gr', 8, 2)->nullable()->comment('110 gr típico');
            $table->decimal('modivitasan_ml', 8, 2)->nullable()->comment('15-20 ml');
            $table->decimal('fosfoton_ml', 8, 2)->nullable()->comment('18-20 ml');
            $table->decimal('seve_ml', 8, 2)->nullable()->comment('Selenio 10 ml');
            $table->boolean('desparasitacion_previa')->default(false);
            $table->boolean('vitaminas_aplicadas')->default(false);
            
            // Protocolo IATF
            $table->boolean('dispositivo_dib')->default(false)->comment('Implante progesterona');
            $table->decimal('estradiol_ml', 8, 2)->nullable()->comment('2 ml típico');
            $table->boolean('retirada_dib')->default(false);
            $table->decimal('ecg_ml', 8, 2)->nullable()->comment('Ecequín - 2 ml típico');
            $table->decimal('pf2_alpha_ml', 8, 2)->nullable()->comment('Prostaglandina');
            $table->time('hora_iatf')->nullable()->comment('Hora de inseminación');
            
            // Variables Ambientales
            $table->enum('epoca_anio', ['verano', 'invierno', 'lluvias'])->nullable();
            $table->decimal('temperatura_ambiente', 5, 2)->nullable()->comment('°C');
            $table->decimal('humedad_relativa', 5, 2)->nullable()->comment('%');
            $table->integer('estres_manejo')->nullable()->comment('Escala 1-5');
            $table->integer('calidad_pasturas')->nullable()->comment('Escala 1-5');
            $table->enum('disponibilidad_agua', ['adecuada', 'limitada'])->nullable();
            
            // Gestación Previa
            $table->boolean('gestacion_previa')->default(false);
            $table->integer('dias_gestacion_previa')->nullable()->comment('0-280 días');
            
            // Resultado IATF (Target)
            $table->enum('resultado_iatf', ['confirmada', 'no_prenada', 'muerte_embrionaria', 'pendiente'])->default('pendiente')
                  ->comment('confirmada=Gestante, no_prenada=Vacía, muerte_embrionaria=ME');
            $table->boolean('prenez_confirmada')->nullable()->comment('Variable objetivo ML');
            $table->date('fecha_confirmacion')->nullable()->comment('45 días post IATF');
            $table->integer('dias_gestacion_confirmada')->nullable();
            
            $table->text('observaciones')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            // Índices
            $table->index('animal_id');
            $table->index('fecha_iatf');
            $table->index('resultado_iatf');
            $table->index('prenez_confirmada');
        });
        } else {
            // Si la tabla ya existe, solo agregar columnas faltantes
            Schema::table('iatf_records', function (Blueprint $table) {
                if (!Schema::hasColumn('iatf_records', 'fecha_protocolo_dia_0')) {
                    $table->datetime('fecha_protocolo_dia_0')->nullable()->after('fecha_iatf')->comment('D0: Inicio protocolo');
                }
                if (!Schema::hasColumn('iatf_records', 'fecha_protocolo_dia_8')) {
                    $table->datetime('fecha_protocolo_dia_8')->nullable()->after('fecha_protocolo_dia_0')->comment('D8: Retiro DIB');
                }
                if (!Schema::hasColumn('iatf_records', 'fecha_protocolo_dia_9')) {
                    $table->datetime('fecha_protocolo_dia_9')->nullable()->after('fecha_protocolo_dia_8')->comment('D9: Aplicaciones pre-IATF');
                }
                if (!Schema::hasColumn('iatf_records', 'fecha_protocolo_dia_10')) {
                    $table->datetime('fecha_protocolo_dia_10')->nullable()->after('fecha_protocolo_dia_9')->comment('D10: Día IATF');
                }
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('iatf_records');
    }
};
