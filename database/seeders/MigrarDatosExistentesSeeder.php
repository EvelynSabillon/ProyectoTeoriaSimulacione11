<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\Grupo;
use App\Models\Animal;
use App\Models\IatfRecord;

class MigrarDatosExistentesSeeder extends Seeder
{
    /**
     * Migrar datos existentes a la nueva estructura
     * 
     * Ejecutar con: php artisan db:seed --class=MigrarDatosExistentesSeeder
     */
    public function run(): void
    {
        DB::beginTransaction();

        try {
            echo "🚀 Iniciando migración de datos...\n\n";

            // 1. Migrar grupos
            $this->migrarGrupos();

            // 2. Migrar enums de resultado_iatf
            $this->migrarResultadosIatf();

            // 3. Establecer estados reproductivos por defecto
            $this->establecerEstadosReproductivos();

            DB::commit();
            echo "\n✅ Migración completada exitosamente!\n";

        } catch (\Exception $e) {
            DB::rollBack();
            echo "\n❌ Error en migración: " . $e->getMessage() . "\n";
            throw $e;
        }
    }

    /**
     * Migrar datos de grupo_lote a tabla grupos
     */
    private function migrarGrupos(): void
    {
        echo "📦 Migrando grupos...\n";

        // Obtener valores únicos de grupo_lote
        $gruposLote = Animal::whereNotNull('grupo_lote')
            ->distinct()
            ->pluck('grupo_lote')
            ->filter();

        if ($gruposLote->isEmpty()) {
            echo "   ⚠️  No hay grupos para migrar\n";
            return;
        }

        foreach ($gruposLote as $nombreGrupo) {
            // Crear grupo si no existe
            $grupo = Grupo::firstOrCreate(
                ['nombre' => $nombreGrupo],
                [
                    'descripcion' => "Grupo migrado automáticamente desde grupo_lote",
                    'activo' => true,
                ]
            );

            // Actualizar animales con el grupo_id
            $actualizados = Animal::where('grupo_lote', $nombreGrupo)
                ->whereNull('grupo_id')
                ->update(['grupo_id' => $grupo->id]);

            echo "   ✓ Grupo '{$nombreGrupo}': {$actualizados} animales vinculados\n";
        }

        echo "   ✅ Total grupos creados: " . Grupo::count() . "\n\n";
    }

    /**
     * Migrar valores antiguos de resultado_iatf a nuevos valores
     */
    private function migrarResultadosIatf(): void
    {
        echo "🔄 Migrando valores de resultado_iatf...\n";

        $mapeo = [
            'Conf.' => 'confirmada',
            'X' => 'no_prenada',
            'ME' => 'muerte_embrionaria',
            'Pendiente' => 'pendiente',
        ];

        $totalActualizados = 0;

        foreach ($mapeo as $valorAntiguo => $valorNuevo) {
            $count = DB::table('iatf_records')
                ->where('resultado_iatf', $valorAntiguo)
                ->update(['resultado_iatf' => $valorNuevo]);

            if ($count > 0) {
                echo "   ✓ '{$valorAntiguo}' → '{$valorNuevo}': {$count} registros\n";
                $totalActualizados += $count;
            }
        }

        if ($totalActualizados === 0) {
            echo "   ⚠️  No hay registros IATF para migrar (valores ya actualizados o sin datos)\n";
        } else {
            echo "   ✅ Total registros IATF actualizados: {$totalActualizados}\n";
        }

        echo "\n";
    }

    /**
     * Establecer estados reproductivos por defecto para animales sin estado
     */
    private function establecerEstadosReproductivos(): void
    {
        echo "🐄 Estableciendo estados reproductivos...\n";

        // Animales sin estado reproductivo definido -> 'activa'
        $sinEstado = Animal::whereNull('estado_reproductivo')
            ->orWhere('estado_reproductivo', '')
            ->update(['estado_reproductivo' => 'activa']);

        echo "   ✓ Animales marcados como 'activa': {$sinEstado}\n";

        // Detectar animales con IATF confirmada reciente (últimos 90 días) -> 'prenada'
        $fechaLimite = now()->subDays(90);
        
        $animalesPrenadas = Animal::whereHas('iatfRecords', function($query) use ($fechaLimite) {
            $query->where('resultado_iatf', 'confirmada')
                  ->where('fecha_iatf', '>=', $fechaLimite);
        })->where('activo', true)
          ->update(['estado_reproductivo' => 'prenada']);

        echo "   ✓ Animales detectados como 'prenada' (IATF confirmada < 90 días): {$animalesPrenadas}\n";

        // Animales inactivos -> 'descarte'
        $descarte = Animal::where('activo', false)
            ->where('estado_reproductivo', 'activa')
            ->update(['estado_reproductivo' => 'descarte']);

        echo "   ✓ Animales inactivos marcados como 'descarte': {$descarte}\n";

        echo "   ✅ Estados reproductivos establecidos\n\n";
    }
}
