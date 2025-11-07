<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Animal;

class AnimalSeeder extends Seeder
{
    public function run(): void
    {
        $animales = [
            [
                'arete' => '001',
                'grupo_lote' => 'Gitano',
                'edad_meses' => 36,
                'peso_kg' => 450.0,
                'condicion_corporal' => 3.0,
                'numero_partos' => 2,
                'dias_posparto' => 75,
                'dias_abiertos' => 90,
                'historial_abortos' => false,
                'numero_abortos' => 0,
                'enfermedades_reproductivas' => false,
                'activo' => true,
            ],
            [
                'arete' => '002',
                'grupo_lote' => 'Gitano',
                'edad_meses' => 48,
                'peso_kg' => 480.0,
                'condicion_corporal' => 3.5,
                'numero_partos' => 3,
                'dias_posparto' => 60,
                'dias_abiertos' => 75,
                'historial_abortos' => false,
                'numero_abortos' => 0,
                'enfermedades_reproductivas' => false,
                'activo' => true,
            ],
            [
                'arete' => '003',
                'grupo_lote' => 'Bartolo',
                'edad_meses' => 30,
                'peso_kg' => 420.0,
                'condicion_corporal' => 2.5,
                'numero_partos' => 1,
                'dias_posparto' => 90,
                'dias_abiertos' => 120,
                'historial_abortos' => true,
                'numero_abortos' => 1,
                'enfermedades_reproductivas' => false,
                'activo' => true,
            ],
            [
                'arete' => '004',
                'grupo_lote' => 'Bartolo',
                'edad_meses' => 42,
                'peso_kg' => 470.0,
                'condicion_corporal' => 3.2,
                'numero_partos' => 2,
                'dias_posparto' => 65,
                'dias_abiertos' => 80,
                'historial_abortos' => false,
                'numero_abortos' => 0,
                'enfermedades_reproductivas' => false,
                'activo' => true,
            ],
            [
                'arete' => '005',
                'grupo_lote' => 'Brisas',
                'edad_meses' => 38,
                'peso_kg' => 460.0,
                'condicion_corporal' => 3.3,
                'numero_partos' => 2,
                'dias_posparto' => 70,
                'dias_abiertos' => 85,
                'historial_abortos' => false,
                'numero_abortos' => 0,
                'enfermedades_reproductivas' => false,
                'activo' => true,
            ],
        ];

        foreach ($animales as $animal) {
            Animal::create($animal);
        }
    }
}
