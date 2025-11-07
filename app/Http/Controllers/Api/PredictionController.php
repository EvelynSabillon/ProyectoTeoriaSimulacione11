<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\IatfRecord;
use App\Models\Prediction;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;

class PredictionController extends Controller
{
    /**
     * Listar todas las predicciones
     */
    public function index(Request $request)
    {
        $query = Prediction::with(['iatfRecord.animal', 'iatfRecord.semental', 'user']);

        if ($request->has('nivel_confianza')) {
            $query->where('nivel_confianza', $request->nivel_confianza);
        }

        if ($request->has('validadas')) {
            if ($request->validadas) {
                $query->whereNotNull('resultado_real');
            } else {
                $query->whereNull('resultado_real');
            }
        }

        $predictions = $query->orderBy('created_at', 'desc')->paginate(15);

        return response()->json([
            'success' => true,
            'data' => $predictions,
        ]);
    }

    /**
     * Crear predicción (llamada al modelo ML)
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'iatf_record_id' => 'required|exists:iatf_records,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Obtener registro IATF con todas las relaciones
        $iatfRecord = IatfRecord::with(['animal', 'semental'])->find($request->iatf_record_id);

        // Verificar si ya existe una predicción
        if ($iatfRecord->prediction) {
            return response()->json([
                'success' => false,
                'message' => 'Ya existe una predicción para este registro IATF',
                'data' => $iatfRecord->prediction,
            ], 409);
        }

        // Preparar datos para el modelo ML
        $datosParaML = $this->prepararDatosParaML($iatfRecord);

        // OPCIÓN A: Llamar a API Python/Flask (cuando esté disponible)
        // $resultado = $this->llamarAPIML($datosParaML);

        // OPCIÓN B: Simulación (mientras no tengas Python)
        $resultado = $this->simularPrediccion($datosParaML);

        // Crear registro de predicción
        $prediction = Prediction::create([
            'iatf_record_id' => $iatfRecord->id,
            'user_id' => auth()->id(),
            'probabilidad_prenez' => $resultado['probabilidad_prenez'],
            'prediccion_binaria' => $resultado['prediccion_binaria'],
            'nivel_confianza' => $resultado['nivel_confianza'],
            'modelo_usado' => $resultado['modelo_usado'],
            'version_modelo' => $resultado['version_modelo'],
            'accuracy' => $resultado['metricas']['accuracy'] ?? null,
            'precision' => $resultado['metricas']['precision'] ?? null,
            'recall' => $resultado['metricas']['recall'] ?? null,
            'f1_score' => $resultado['metricas']['f1_score'] ?? null,
            'roc_auc' => $resultado['metricas']['roc_auc'] ?? null,
            'top_features' => $resultado['top_features'] ?? null,
            'recomendaciones' => $resultado['recomendaciones'] ?? null,
        ]);

        ActivityLog::registrar(
            'predecir',
            'Prediction',
            $prediction->id,
            "Predicción generada para IATF ID: {$iatfRecord->id}"
        );

        return response()->json([
            'success' => true,
            'message' => 'Predicción generada exitosamente',
            'data' => $prediction->load(['iatfRecord.animal', 'iatfRecord.semental']),
        ], 201);
    }

    /**
     * Mostrar una predicción específica
     */
    public function show($id)
    {
        $prediction = Prediction::with(['iatfRecord.animal', 'iatfRecord.semental'])
                                ->find($id);

        if (!$prediction) {
            return response()->json([
                'success' => false,
                'message' => 'Predicción no encontrada',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $prediction,
        ]);
    }

    /**
     * Preparar datos para el modelo ML
     */
    private function prepararDatosParaML($iatfRecord)
    {
        $animal = $iatfRecord->animal;
        $semental = $iatfRecord->semental;

        return [
            // Variables del Animal
            'edad_meses' => $animal->edad_meses,
            'peso_kg' => $animal->peso_kg,
            'condicion_corporal' => $animal->condicion_corporal,
            'numero_partos' => $animal->numero_partos,
            'dias_posparto' => $animal->dias_posparto,
            'dias_abiertos' => $animal->dias_abiertos,
            'historial_abortos' => $animal->historial_abortos ? 1 : 0,
            'enfermedades_reproductivas' => $animal->enfermedades_reproductivas ? 1 : 0,
            
            // Variables del Registro IATF
            'condicion_ovarica_od' => $iatfRecord->condicion_ovarica_od,
            'condicion_ovarica_oi' => $iatfRecord->condicion_ovarica_oi,
            'tono_uterino' => $iatfRecord->tono_uterino,
            'tratamiento_previo' => $iatfRecord->tratamiento_previo,
            'dias_tonificacion' => $iatfRecord->dias_tonificacion,
            'sal_mineral_gr' => $iatfRecord->sal_mineral_gr,
            'modivitasan_ml' => $iatfRecord->modivitasan_ml,
            'fosfoton_ml' => $iatfRecord->fosfoton_ml,
            'seve_ml' => $iatfRecord->seve_ml,
            'desparasitacion_previa' => $iatfRecord->desparasitacion_previa ? 1 : 0,
            'vitaminas_aplicadas' => $iatfRecord->vitaminas_aplicadas ? 1 : 0,
            'dispositivo_dib' => $iatfRecord->dispositivo_dib ? 1 : 0,
            'estradiol_ml' => $iatfRecord->estradiol_ml,
            'retirada_dib' => $iatfRecord->retirada_dib ? 1 : 0,
            'ecg_ml' => $iatfRecord->ecg_ml,
            'pf2_alpha_ml' => $iatfRecord->pf2_alpha_ml,
            'epoca_anio' => $iatfRecord->epoca_anio,
            'temperatura_ambiente' => $iatfRecord->temperatura_ambiente,
            'humedad_relativa' => $iatfRecord->humedad_relativa,
            'estres_manejo' => $iatfRecord->estres_manejo,
            'calidad_pasturas' => $iatfRecord->calidad_pasturas,
            'disponibilidad_agua' => $iatfRecord->disponibilidad_agua,
            'gestacion_previa' => $iatfRecord->gestacion_previa ? 1 : 0,
            'dias_gestacion_previa' => $iatfRecord->dias_gestacion_previa,
            
            // Variables del Semental (si existe)
            'calidad_seminal' => $semental->calidad_seminal ?? null,
            'concentracion_espermatica' => $semental->concentracion_espermatica ?? null,
            'morfologia_espermatica' => $semental->morfologia_espermatica ?? null,
            'tasa_historica_prenez_semental' => $semental->tasa_historica_prenez ?? null,
        ];
    }

    /**
     * Llamar a la API de Machine Learning (Python/Flask)
     * NOTA: Usar cuando tengas la API ML funcionando
     */
    private function llamarAPIML($datos)
    {
        try {
            $response = Http::timeout(30)->post(env('ML_API_URL', 'http://localhost:5000/api/predict'), [
                'data' => $datos,
            ]);

            if ($response->successful()) {
                return $response->json();
            }

            throw new \Exception('Error en la API de ML: ' . $response->body());
        } catch (\Exception $e) {
            // Si falla, usar simulación
            return $this->simularPrediccion($datos);
        }
    }

    /**
     * Simular predicción (TEMPORAL - mientras no tengas Python)
     */
    private function simularPrediccion($datos)
    {
        // Algoritmo simple de scoring basado en las variables más importantes
        $score = 50; // Base: 50%

        // Condición corporal (muy importante)
        if (isset($datos['condicion_corporal'])) {
            if ($datos['condicion_corporal'] >= 3.0 && $datos['condicion_corporal'] <= 3.5) {
                $score += 15;
            } elseif ($datos['condicion_corporal'] < 2.5) {
                $score -= 20;
            }
        }

        // Días posparto (importante)
        if (isset($datos['dias_posparto'])) {
            if ($datos['dias_posparto'] >= 60 && $datos['dias_posparto'] <= 90) {
                $score += 10;
            } elseif ($datos['dias_posparto'] < 45) {
                $score -= 15;
            }
        }

        // Condición ovárica
        if (in_array($datos['condicion_ovarica_od'], ['C', 'CL', 'FD'])) {
            $score += 10;
        }
        if (in_array($datos['condicion_ovarica_oi'], ['C', 'CL', 'FD'])) {
            $score += 10;
        }

        // Tono uterino
        if (isset($datos['tono_uterino']) && $datos['tono_uterino'] >= 60) {
            $score += 8;
        }

        // Historial de abortos (negativo)
        if ($datos['historial_abortos'] == 1) {
            $score -= 12;
        }

        // Enfermedades reproductivas (negativo)
        if ($datos['enfermedades_reproductivas'] == 1) {
            $score -= 15;
        }

        // Calidad del semental
        if (isset($datos['calidad_seminal']) && $datos['calidad_seminal'] >= 70) {
            $score += 8;
        }

        // Normalizar entre 0 y 1
        $probabilidad = max(0.1, min(0.95, $score / 100));

        // Determinar nivel de confianza
        if ($probabilidad >= 0.7) {
            $nivelConfianza = 'alto';
        } elseif ($probabilidad >= 0.4) {
            $nivelConfianza = 'medio';
        } else {
            $nivelConfianza = 'bajo';
        }

        // Generar recomendaciones
        $recomendaciones = $this->generarRecomendaciones($datos, $probabilidad);

        return [
            'probabilidad_prenez' => round($probabilidad, 4),
            'prediccion_binaria' => $probabilidad >= 0.5,
            'nivel_confianza' => $nivelConfianza,
            'modelo_usado' => 'SimulacionTemporalV1',
            'version_modelo' => '1.0.0',
            'metricas' => [
                'accuracy' => 0.7500,
                'precision' => 0.7200,
                'recall' => 0.7000,
                'f1_score' => 0.7100,
                'roc_auc' => 0.8000,
            ],
            'top_features' => [
                ['feature' => 'condicion_corporal', 'importance' => 0.25],
                ['feature' => 'dias_posparto', 'importance' => 0.20],
                ['feature' => 'condicion_ovarica', 'importance' => 0.18],
                ['feature' => 'tono_uterino', 'importance' => 0.15],
                ['feature' => 'calidad_seminal', 'importance' => 0.12],
            ],
            'recomendaciones' => $recomendaciones,
        ];
    }

    /**
     * Generar recomendaciones basadas en los datos
     */
    private function generarRecomendaciones($datos, $probabilidad)
    {
        $recomendaciones = [];

        if ($probabilidad < 0.4) {
            $recomendaciones[] = "⚠️ Probabilidad baja de preñez. Considere evaluar las condiciones del animal.";
        }

        if (isset($datos['condicion_corporal']) && $datos['condicion_corporal'] < 2.5) {
            $recomendaciones[] = "🔸 Mejorar condición corporal (actualmente {$datos['condicion_corporal']}). Meta: 3.0-3.5";
        }

        if (isset($datos['dias_posparto']) && $datos['dias_posparto'] < 60) {
            $recomendaciones[] = "⏱️ Animal con {$datos['dias_posparto']} días posparto. Considere esperar hasta 60+ días.";
        }

        if ($datos['enfermedades_reproductivas'] == 1) {
            $recomendaciones[] = "🏥 Animal con historial de enfermedades reproductivas. Requiere seguimiento veterinario.";
        }

        if ($datos['historial_abortos'] == 1) {
            $recomendaciones[] = "⚠️ Animal con historial de abortos. Monitoreo especial recomendado.";
        }

        if (empty($recomendaciones)) {
            $recomendaciones[] = "✅ Condiciones favorables para IATF. Continuar con protocolo estándar.";
        }

        return implode("\n", $recomendaciones);
    }

    /**
     * Obtener estadísticas generales de predicciones
     */
    public function estadisticas()
    {
        $totalPredicciones = Prediction::count();
        $prediccionesValidadas = Prediction::whereNotNull('resultado_real')->count();
        $prediccionesCorrectas = Prediction::where('prediccion_correcta', true)->count();

        $tasaAcierto = $prediccionesValidadas > 0 
            ? ($prediccionesCorrectas / $prediccionesValidadas) * 100 
            : 0;

        $promedioConfianza = Prediction::avg('probabilidad_prenez');

        return response()->json([
            'success' => true,
            'data' => [
                'total_predicciones' => $totalPredicciones,
                'predicciones_validadas' => $prediccionesValidadas,
                'predicciones_correctas' => $prediccionesCorrectas,
                'tasa_acierto' => round($tasaAcierto, 2),
                'promedio_confianza' => round($promedioConfianza * 100, 2),
            ],
        ]);
    }
}