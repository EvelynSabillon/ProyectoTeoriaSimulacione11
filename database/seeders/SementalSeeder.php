<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Semental;

class SementalSeeder extends Seeder
{
    public function run(): void
    {
        $sementales = [
            [
                'nombre' => 'Angus Superior',
                'raza' => 'Angus',
                'codigo_pajilla' => 'ANG-2024-001',
                'calidad_seminal' => 85.5,
                'concentracion_espermatica' => 120.0,
                'morfologia_espermatica' => 90.0,
                'tasa_historica_prenez' => 65.0,
                'total_servicios' => 50,
                'total_preneces' => 33,
                'proveedor' => 'GenTech SA',
                'precio_pajilla' => 150.00,
                'activo' => true,
            ],
            [
                'nombre' => 'Brahman Elite',
                'raza' => 'Brahman',
                'codigo_pajilla' => 'BRA-2024-002',
                'calidad_seminal' => 80.0,
                'concentracion_espermatica' => 110.0,
                'morfologia_espermatica' => 85.0,
                'tasa_historica_prenez' => 58.0,
                'total_servicios' => 40,
                'total_preneces' => 23,
                'proveedor' => 'Ganadería Los Robles',
                'precio_pajilla' => 120.00,
                'activo' => true,
            ],
            [
                'nombre' => 'Simmental Pro',
                'raza' => 'Simmental',
                'codigo_pajilla' => 'SIM-2024-003',
                'calidad_seminal' => 88.0,
                'concentracion_espermatica' => 130.0,
                'morfologia_espermatica' => 92.0,
                'tasa_historica_prenez' => 70.0,
                'total_servicios' => 60,
                'total_preneces' => 42,
                'proveedor' => 'GenTech SA',
                'precio_pajilla' => 180.00,
                'activo' => true,
            ],
        ];

        foreach ($sementales as $semental) {
            Semental::create($semental);
        }
    }
}
