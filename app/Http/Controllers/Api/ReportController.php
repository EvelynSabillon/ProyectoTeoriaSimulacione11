<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Report;
use App\Models\IatfRecord;
use App\Models\Animal;
use App\Models\Semental;
use App\Models\Prediction;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    /**
     * Listar todos los reportes
     */
    public function index(Request $request)
    {
        $query = Report::with('user');

        if ($request->has('tipo_reporte')) {
            $query->where('tipo_reporte', $request->tipo_reporte);
        }

        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        $reports = $query->orderBy('created_at', 'desc')->paginate(15);

        return response()->json([
            'success' => true,
            'data' => $reports,
        ]);
    }

    /**
     * Generar reporte de tasas de preñez
     */
    public function generarReporteTasasPrenez(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'fecha_inicio' => 'required|date',
            'fecha_fin' => 'required|date|after_or_equal:fecha_inicio',
            'grupo_lote' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Consultar datos
        $query = IatfRecord::whereBetween('fecha_iatf', [
            $request->fecha_inicio,
            $request->fecha_fin
        ]);

        if ($request->has('grupo_lote')) {
            $query->whereHas('animal', function($q) use ($request) {
                $q->where('grupo_lote', $request->grupo_lote);
            });
        }

        $registros = $query->with(['animal', 'semental'])->get();

        // Calcular estadísticas
        $totalIatf = $registros->count();
        $prenecesConfirmadas = $registros->where('prenez_confirmada', true)->count();
        $muertesEmbrionarias = $registros->where('resultado_iatf', 'muerte_embrionaria')->count();
        $noGestantes = $registros->where('resultado_iatf', 'X')->count();
        $pendientes = $registros->where('resultado_iatf', 'Pendiente')->count();

        $tasaPrenez = $totalIatf > 0 ? ($prenecesConfirmadas / $totalIatf) * 100 : 0;
        $tasaMuerteEmbrionaria = $totalIatf > 0 ? ($muertesEmbrionarias / $totalIatf) * 100 : 0;

        // Datos por grupo
        $datosPorGrupo = $registros->groupBy(function($item) {
            return $item->animal->grupo_lote ?? 'Sin grupo';
        })->map(function($grupo) {
            $total = $grupo->count();
            $confirmadas = $grupo->where('prenez_confirmada', true)->count();
            return [
                'total' => $total,
                'confirmadas' => $confirmadas,
                'tasa_prenez' => $total > 0 ? round(($confirmadas / $total) * 100, 2) : 0,
            ];
        });

        $dataResultados = [
            'resumen' => [
                'total_iatf' => $totalIatf,
                'preneces_confirmadas' => $prenecesConfirmadas,
                'muertes_embrionarias' => $muertesEmbrionarias,
                'no_gestantes' => $noGestantes,
                'pendientes' => $pendientes,
                'tasa_prenez' => round($tasaPrenez, 2),
                'tasa_muerte_embrionaria' => round($tasaMuerteEmbrionaria, 2),
            ],
            'por_grupo' => $datosPorGrupo,
            'registros' => $registros->map(function($r) {
                return [
                    'arete' => $r->animal->arete,
                    'grupo' => $r->animal->grupo_lote,
                    'fecha_iatf' => $r->fecha_iatf,
                    'resultado' => $r->resultado_iatf,
                    'semental' => $r->semental->nombre ?? 'N/A',
                ];
            }),
        ];

        // Crear reporte
        $report = Report::create([
            'user_id' => auth()->id(),
            'tipo_reporte' => 'tasas_prenez',
            'fecha_inicio' => $request->fecha_inicio,
            'fecha_fin' => $request->fecha_fin,
            'grupo_lote' => $request->grupo_lote,
            'data_resultados' => $dataResultados,
            'total_animales' => $registros->pluck('animal_id')->unique()->count(),
            'total_iatf' => $totalIatf,
            'tasa_prenez' => round($tasaPrenez, 2),
            'tasa_muerte_embrionaria' => round($tasaMuerteEmbrionaria, 2),
        ]);

        ActivityLog::registrar(
            'generar_reporte',
            'Report',
            $report->id,
            "Reporte de tasas de preñez generado"
        );

        return response()->json([
            'success' => true,
            'message' => 'Reporte generado exitosamente',
            'data' => $report,
        ], 201);
    }

    /**
     * Generar reporte de efectividad de protocolo
     */
    public function generarReporteEfectividadProtocolo(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'fecha_inicio' => 'required|date',
            'fecha_fin' => 'required|date|after_or_equal:fecha_inicio',
            'tratamiento' => 'nullable|in:T1,T2',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $validator->errors(),
            ], 422);
        }

        $query = IatfRecord::whereBetween('fecha_iatf', [
            $request->fecha_inicio,
            $request->fecha_fin
        ]);

        if ($request->has('tratamiento')) {
            $query->where('tratamiento_previo', $request->tratamiento);
        }

        $registros = $query->get();

        // Análisis por tratamiento
        $analisisPorTratamiento = $registros->groupBy('tratamiento_previo')->map(function($grupo, $tratamiento) {
            $total = $grupo->count();
            $confirmadas = $grupo->where('prenez_confirmada', true)->count();
            return [
                'tratamiento' => $tratamiento ?? 'Sin tratamiento',
                'total' => $total,
                'confirmadas' => $confirmadas,
                'tasa_prenez' => $total > 0 ? round(($confirmadas / $total) * 100, 2) : 0,
            ];
        });

        // Análisis de uso de DIB
        $conDIB = $registros->where('dispositivo_dib', true);
        $sinDIB = $registros->where('dispositivo_dib', false);

        $dataResultados = [
            'por_tratamiento' => $analisisPorTratamiento,
            'uso_dib' => [
                'con_dib' => [
                    'total' => $conDIB->count(),
                    'confirmadas' => $conDIB->where('prenez_confirmada', true)->count(),
                    'tasa_prenez' => $conDIB->count() > 0 
                        ? round(($conDIB->where('prenez_confirmada', true)->count() / $conDIB->count()) * 100, 2) 
                        : 0,
                ],
                'sin_dib' => [
                    'total' => $sinDIB->count(),
                    'confirmadas' => $sinDIB->where('prenez_confirmada', true)->count(),
                    'tasa_prenez' => $sinDIB->count() > 0 
                        ? round(($sinDIB->where('prenez_confirmada', true)->count() / $sinDIB->count()) * 100, 2) 
                        : 0,
                ],
            ],
        ];

        $report = Report::create([
            'user_id' => auth()->id(),
            'tipo_reporte' => 'efectividad_protocolo',
            'fecha_inicio' => $request->fecha_inicio,
            'fecha_fin' => $request->fecha_fin,
            'data_resultados' => $dataResultados,
            'total_iatf' => $registros->count(),
        ]);

        ActivityLog::registrar(
            'generar_reporte',
            'Report',
            $report->id,
            "Reporte de efectividad de protocolo generado"
        );

        return response()->json([
            'success' => true,
            'message' => 'Reporte generado exitosamente',
            'data' => $report,
        ], 201);
    }

    /**
     * Generar reporte de análisis de semental
     */
    public function generarReporteSemental(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'semental_id' => 'nullable|exists:sementales,id',
            'fecha_inicio' => 'nullable|date',
            'fecha_fin' => 'nullable|date|after_or_equal:fecha_inicio',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $validator->errors(),
            ], 422);
        }

        $query = Semental::with(['iatfRecords']);

        if ($request->has('semental_id')) {
            $query->where('id', $request->semental_id);
        }

        $sementales = $query->get();

        $analisisSementales = $sementales->map(function($semental) use ($request) {
            $registros = $semental->iatfRecords;

            // Filtrar por fechas si se especifican
            if ($request->has('fecha_inicio') && $request->has('fecha_fin')) {
                $registros = $registros->whereBetween('fecha_iatf', [
                    $request->fecha_inicio,
                    $request->fecha_fin
                ]);
            }

            $total = $registros->count();
            $confirmadas = $registros->where('prenez_confirmada', true)->count();
            $muertesEmb = $registros->where('resultado_iatf', 'muerte_embrionaria')->count();

            return [
                'id' => $semental->id,
                'nombre' => $semental->nombre,
                'raza' => $semental->raza,
                'total_servicios' => $total,
                'preneces_confirmadas' => $confirmadas,
                'muertes_embrionarias' => $muertesEmb,
                'tasa_prenez' => $total > 0 ? round(($confirmadas / $total) * 100, 2) : 0,
                'tasa_muerte_embrionaria' => $total > 0 ? round(($muertesEmb / $total) * 100, 2) : 0,
                'calidad_seminal' => $semental->calidad_seminal,
            ];
        });

        $report = Report::create([
            'user_id' => auth()->id(),
            'tipo_reporte' => 'analisis_semental',
            'fecha_inicio' => $request->fecha_inicio,
            'fecha_fin' => $request->fecha_fin,
            'data_resultados' => ['sementales' => $analisisSementales],
        ]);

        ActivityLog::registrar(
            'generar_reporte',
            'Report',
            $report->id,
            "Reporte de análisis de semental generado"
        );

        return response()->json([
            'success' => true,
            'message' => 'Reporte generado exitosamente',
            'data' => $report,
        ], 201);
    }

    /**
     * Generar reporte de rendimiento del modelo ML
     */
    public function generarReporteRendimientoML()
    {
        $predicciones = Prediction::with(['iatfRecord'])->get();
        
        $totalPredicciones = $predicciones->count();
        $validadas = $predicciones->whereNotNull('resultado_real');
        $correctas = $validadas->where('prediccion_correcta', true);

        $tasaAcierto = $validadas->count() > 0 
            ? ($correctas->count() / $validadas->count()) * 100 
            : 0;

        // Análisis por nivel de confianza
        $porNivelConfianza = $predicciones->groupBy('nivel_confianza')->map(function($grupo, $nivel) {
            $validadas = $grupo->whereNotNull('resultado_real');
            $correctas = $validadas->where('prediccion_correcta', true);
            
            return [
                'nivel' => $nivel,
                'total' => $grupo->count(),
                'validadas' => $validadas->count(),
                'correctas' => $correctas->count(),
                'tasa_acierto' => $validadas->count() > 0 
                    ? round(($correctas->count() / $validadas->count()) * 100, 2) 
                    : 0,
            ];
        });

        $dataResultados = [
            'resumen' => [
                'total_predicciones' => $totalPredicciones,
                'predicciones_validadas' => $validadas->count(),
                'predicciones_correctas' => $correctas->count(),
                'tasa_acierto_global' => round($tasaAcierto, 2),
                'promedio_probabilidad' => round($predicciones->avg('probabilidad_prenez') * 100, 2),
            ],
            'por_nivel_confianza' => $porNivelConfianza,
            'metricas_promedio' => [
                'accuracy' => round($predicciones->avg('accuracy'), 4),
                'precision' => round($predicciones->avg('precision'), 4),
                'recall' => round($predicciones->avg('recall'), 4),
                'f1_score' => round($predicciones->avg('f1_score'), 4),
                'roc_auc' => round($predicciones->avg('roc_auc'), 4),
            ],
        ];

        $report = Report::create([
            'user_id' => auth()->id(),
            'tipo_reporte' => 'rendimiento_modelo',
            'data_resultados' => $dataResultados,
        ]);

        ActivityLog::registrar(
            'generar_reporte',
            'Report',
            $report->id,
            "Reporte de rendimiento del modelo ML generado"
        );

        return response()->json([
            'success' => true,
            'message' => 'Reporte generado exitosamente',
            'data' => $report,
        ], 201);
    }

    /**
     * Dashboard general con estadísticas
     */
    public function dashboard()
    {
        // Estadísticas generales
        $totalAnimales = Animal::activos()->count();
        $totalIatf = IatfRecord::count();
        $totalPredicciones = Prediction::count();
        
        // Últimos 30 días
        $fecha30Dias = now()->subDays(30);
        
        $iatfRecientes = IatfRecord::where('fecha_iatf', '>=', $fecha30Dias)->get();
        $prenecesRecientes = $iatfRecientes->where('prenez_confirmada', true)->count();
        
        $tasaPrenezReciente = $iatfRecientes->count() > 0 
            ? ($prenecesRecientes / $iatfRecientes->count()) * 100 
            : 0;

        // IATF pendientes de confirmación
        $pendientesConfirmacion = IatfRecord::where('resultado_iatf', 'Pendiente')
            ->where('fecha_iatf', '<=', now()->subDays(45))
            ->count();

        // Top 5 sementales
        $topSementales = Semental::activos()
            ->orderBy('tasa_historica_prenez', 'desc')
            ->take(5)
            ->get(['id', 'nombre', 'tasa_historica_prenez', 'total_servicios']);

        // Distribución por grupos
        $distribucionGrupos = Animal::activos()
            ->select('grupo_lote', DB::raw('count(*) as total'))
            ->groupBy('grupo_lote')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'resumen' => [
                    'total_animales' => $totalAnimales,
                    'total_iatf' => $totalIatf,
                    'total_predicciones' => $totalPredicciones,
                    'tasa_prenez_30_dias' => round($tasaPrenezReciente, 2),
                    'pendientes_confirmacion' => $pendientesConfirmacion,
                ],
                'top_sementales' => $topSementales,
                'distribucion_grupos' => $distribucionGrupos,
            ],
        ]);
    }

    /**
     * Mostrar un reporte específico
     */
    public function show($id)
    {
        $report = Report::with('user')->find($id);

        if (!$report) {
            return response()->json([
                'success' => false,
                'message' => 'Reporte no encontrado',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $report,
        ]);
    }

    /**
     * Eliminar reporte
     */
    public function destroy($id)
    {
        $report = Report::find($id);

        if (!$report) {
            return response()->json([
                'success' => false,
                'message' => 'Reporte no encontrado',
            ], 404);
        }

        $report->delete();

        ActivityLog::registrar(
            'eliminar',
            'Report',
            $id,
            "Reporte eliminado"
        );

        return response()->json([
            'success' => true,
            'message' => 'Reporte eliminado exitosamente',
        ]);
    }
}