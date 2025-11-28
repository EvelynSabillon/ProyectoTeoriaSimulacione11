<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Agregar campo hora_iatf_score a iatf_records
        Schema::table('iatf_records', function (Blueprint $table) {
            $table->tinyInteger('hora_iatf_score')
                  ->nullable()
                  ->after('hora_iatf')
                  ->comment('Score de hora IATF: 2=Óptimo(6-10am), 1=Bueno(10-2pm), 0=Aceptable(2-6pm), -1=Fuera protocolo');
        });

        // Agregar campo tasa_historica_prenez a sementales (si no existe)
        if (!Schema::hasColumn('sementales', 'tasa_historica_prenez')) {
            Schema::table('sementales', function (Blueprint $table) {
                $table->decimal('tasa_historica_prenez', 5, 2)
                      ->nullable()
                      ->after('morfologia_espermatica')
                      ->comment('Tasa histórica de preñez del semental (0-100%)');
            });
        }

        // Actualizar hora_iatf_score para registros existentes
        DB::statement("
            UPDATE iatf_records 
            SET hora_iatf_score = CASE
                WHEN hora_iatf IS NOT NULL AND HOUR(hora_iatf) BETWEEN 6 AND 9 THEN 2
                WHEN hora_iatf IS NOT NULL AND HOUR(hora_iatf) BETWEEN 10 AND 13 THEN 1
                WHEN hora_iatf IS NOT NULL AND HOUR(hora_iatf) BETWEEN 14 AND 17 THEN 0
                WHEN hora_iatf IS NOT NULL THEN -1
                ELSE 0
            END
            WHERE hora_iatf_score IS NULL
        ");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('iatf_records', function (Blueprint $table) {
            $table->dropColumn('hora_iatf_score');
        });

        // Solo eliminar si la columna fue creada por esta migración
        if (Schema::hasColumn('sementales', 'tasa_historica_prenez')) {
            Schema::table('sementales', function (Blueprint $table) {
                $table->dropColumn('tasa_historica_prenez');
            });
        }
    }
};