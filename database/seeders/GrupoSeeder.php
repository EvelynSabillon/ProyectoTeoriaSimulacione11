<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Grupo;

class GrupoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $grupos = [
            [
                'nombre' => 'Grupo A',
                'descripcion' => 'Vacas de primer parto',
                'activo' => true,
            ],
            [
                'nombre' => 'Grupo B',
                'descripcion' => 'Vacas multíparas',
                'activo' => true,
            ],
            [
                'nombre' => 'Grupo C',
                'descripcion' => 'Vaquillonas',
                'activo' => true,
            ],
            [
                'nombre' => 'Grupo D',
                'descripcion' => 'Vacas de alta producción',
                'activo' => true,
            ],
            [
                'nombre' => 'Grupo E',
                'descripcion' => 'Grupo de recuperación',
                'activo' => true,
            ],
        ];

        foreach ($grupos as $grupo) {
            Grupo::firstOrCreate(
                ['nombre' => $grupo['nombre']],
                $grupo
            );
        }

        $this->command->info('✅ Grupos creados exitosamente');
    }
}
