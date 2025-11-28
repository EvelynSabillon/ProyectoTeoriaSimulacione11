<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Semental;

class ActualizarEstadisticasSementales extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sementales:actualizar-estadisticas
                          {--semental_id= : ID de semental específico (opcional)}
                          {--force : Forzar actualización incluso si ya tienen datos}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Actualizar las tasas históricas de preñez de los sementales basado en resultados de IATF confirmados';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('🐂 Iniciando actualización de estadísticas de sementales...');
        $this->newLine();

        // Determinar qué sementales actualizar
        if ($sementalId = $this->option('semental_id')) {
            $sementales = Semental::where('id', $sementalId)->get();
            
            if ($sementales->isEmpty()) {
                $this->error("❌ No se encontró semental con ID {$sementalId}");
                return 1;
            }
        } else {
            if ($this->option('force')) {
                $sementales = Semental::all();
                $this->warn('⚠️  Modo FORCE activado: Actualizando TODOS los sementales');
            } else {
                // Solo actualizar los que tienen IATFs confirmados
                $sementales = Semental::whereHas('iatfRecords', function($query) {
                    $query->whereNotNull('prenez_confirmada');
                })->get();
            }
        }

        if ($sementales->isEmpty()) {
            $this->warn('⚠️  No se encontraron sementales para actualizar');
            return 0;
        }

        $this->info("📊 Sementales a actualizar: {$sementales->count()}");
        $this->newLine();

        $bar = $this->output->createProgressBar($sementales->count());
        $bar->start();

        $actualizados = 0;
        $errores = 0;

        foreach ($sementales as $semental) {
            try {
                $tasaAnterior = $semental->tasa_historica_prenez;
                $semental->actualizarEstadisticas();
                $tasaNueva = $semental->fresh()->tasa_historica_prenez;

                if ($tasaAnterior != $tasaNueva) {
                    $actualizados++;
                }

                $bar->advance();
            } catch (\Exception $e) {
                $errores++;
                $this->newLine();
                $this->error("❌ Error actualizando semental {$semental->nombre}: {$e->getMessage()}");
            }
        }

        $bar->finish();
        $this->newLine(2);

        // Resumen
        $this->info('┌─────────────────────────────────────┐');
        $this->info('│     RESUMEN DE ACTUALIZACIÓN        │');
        $this->info('├─────────────────────────────────────┤');
        $this->info(sprintf('│ Total procesados:  %16d │', $sementales->count()));
        $this->info(sprintf('│ Actualizados:      %16d │', $actualizados));
        $this->info(sprintf('│ Sin cambios:       %16d │', $sementales->count() - $actualizados - $errores));
        $this->info(sprintf('│ Errores:           %16d │', $errores));
        $this->info('└─────────────────────────────────────┘');
        $this->newLine();

        // Mostrar top 5 mejores sementales
        $this->info('🏆 TOP 5 MEJORES SEMENTALES:');
        $this->newLine();

        $topSementales = Semental::whereNotNull('tasa_historica_prenez')
            ->orderBy('tasa_historica_prenez', 'desc')
            ->take(5)
            ->get();

        $this->table(
            ['Nombre', 'Raza', 'Tasa Preñez %', 'Calidad'],
            $topSementales->map(function($s) {
                return [
                    $s->nombre,
                    $s->raza ?? 'N/D',
                    number_format($s->tasa_historica_prenez, 2) . '%',
                    $s->calidad_texto
                ];
            })
        );

        $this->newLine();
        $this->info('✅ Actualización completada');

        return 0;
    }
}
