<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Prediction;
use App\Models\IatfRecord;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

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
            if ($request->validadas === 'true') {
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
     * Crear predicción usando API Flask de ML
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

        // Validar que el registro tenga los datos mínimos necesarios
        $validacion = $this->validarDatosMinimos($iatfRecord);
        if (!$validacion['valido']) {
            return response()->json([
                'success' => false,
                'message' => 'Datos insuficientes para predicción',
                'errores' => $validacion['errores'],
            ], 422);
        }

        try {
            // Preparar datos para la API Flask
            $datosParaML = $this->prepararDatosParaML($iatfRecord);

            // Llamar a la API Flask
            $resultado = $this->llamarAPIFlask($datosParaML);

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
                'resultado_ml' => $resultado, // Info adicional de la API
            ], 201);

        } catch (\Exception $e) {
            Log::error('Error en predicción ML', [
                'iatf_record_id' => $iatfRecord->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al generar predicción',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Validar que el registro IATF tenga los datos mínimos necesarios
     */
    private function validarDatosMinimos($iatfRecord)
    {
        $errores = [];
        $animal = $iatfRecord->animal;
        $semental = $iatfRecord->semental;

        // Validar datos del animal
        if (!$animal) {
            $errores[] = 'No se encontró el animal asociado';
        } else {
            if (is_null($animal->edad_meses)) {
                $errores[] = 'El animal no tiene edad registrada';
            }
            if (is_null($animal->condicion_corporal)) {
                $errores[] = 'El animal no tiene condición corporal (BCS) registrada';
            }
            if (is_null($animal->dias_posparto)) {
                $errores[] = 'El animal no tiene días posparto registrados';
            }
            if (is_null($animal->numero_partos)) {
                $errores[] = 'El animal no tiene número de partos registrado';
            }
        }

        // Validar datos del registro IATF
        if (is_null($iatfRecord->condicion_ovarica_od)) {
            $errores[] = 'Falta condición ovárica derecha';
        }
        if (is_null($iatfRecord->condicion_ovarica_oi)) {
            $errores[] = 'Falta condición ovárica izquierda';
        }
        if (is_null($iatfRecord->tono_uterino)) {
            $errores[] = 'Falta tono uterino';
        }
        if (is_null($iatfRecord->tratamiento_previo)) {
            $errores[] = 'Falta tratamiento previo';
        }

        // Validar semental
        if (!$semental) {
            $errores[] = 'No se encontró el semental asociado';
        } elseif (is_null($semental->tasa_historica_prenez)) {
            $errores[] = 'El semental no tiene tasa histórica de preñez calculada';
        }

        return [
            'valido' => empty($errores),
            'errores' => $errores
        ];
    }

    /**
     * Preparar datos en el formato exacto que espera la API Flask
     */
    private function prepararDatosParaML($iatfRecord)
    {
        $animal = $iatfRecord->animal;
        $semental = $iatfRecord->semental;

        return [
            // 1. condicion_ov_od - Estado ovario derecho
            'condicion_ov_od' => strtoupper($iatfRecord->condicion_ovarica_od),
            
            // 2. condicion_ov_oi - Estado ovario izquierdo
            'condicion_ov_oi' => strtoupper($iatfRecord->condicion_ovarica_oi),
            
            // 3. tono_uterino - Tonicidad del útero (0-100)
            'tono_uterino' => (float) $iatfRecord->tono_uterino,
            
            // 4. tratamiento - Mapear valores
            'tratamiento' => $this->mapearTratamiento($iatfRecord->tratamiento_previo),
            
            // 5. edad_animal - Convertir meses a años
            'edad_animal' => (int) floor($animal->edad_meses / 12),
            
            // 6. bcs - Body Condition Score (1-5)
            'bcs' => (int) round($animal->condicion_corporal),
            
            // 7. dias_posparto - Días desde último parto
            'dias_posparto' => (int) $animal->dias_posparto,
            
            // 8. num_partos - Número de partos previos
            'num_partos' => (int) $animal->numero_partos,
            
            // 9. dias_tonificacion - Duración del tratamiento
            'dias_tonificacion' => (int) ($iatfRecord->dias_tonificacion ?? 0),
            
            // 10. sal_mineral - Gramos/día
            'sal_mineral' => (float) ($iatfRecord->sal_mineral_gr ?? 110), // Default 110
            
            // 11. desparasitacion - Binaria (0 o 1)
            'desparasitacion' => $iatfRecord->desparasitacion_previa ? 1 : 0,
            
            // 12. hora_iatf_score - Calcular desde hora_iatf
            'hora_iatf_score' => $this->calcularHoraIatfScore($iatfRecord->hora_iatf),
            
            // 13. epoca_año - Mapear y convertir a mayúsculas
            'epoca_año' => $this->mapearEpocaAnio($iatfRecord->epoca_anio),
            
            // 14. semental_tasa_prenez - Tasa histórica del semental
            'semental_tasa_prenez' => (float) ($semental->tasa_historica_prenez ?? 50), // Default 50%
        ];
    }

    /**
     * Mapear tratamiento de Laravel a formato API
     */
    private function mapearTratamiento($tratamiento)
    {
        $mapeo = [
            'T1' => 'T1',
            'T2' => 'T2',
            'RS' => 'NINGUNO',
            'DESCARTE' => 'NINGUNO',
            null => 'NINGUNO',
        ];

        return $mapeo[strtoupper($tratamiento ?? '')] ?? 'NINGUNO';
    }

    /**
     * Calcular score de hora IATF
     */
    private function calcularHoraIatfScore($horaIatf)
    {
        if (!$horaIatf) {
            return 0; // Valor por defecto si no hay hora
        }

        // Extraer hora (formato esperado: "HH:MM")
        $hora = (int) substr($horaIatf, 0, 2);

        // Scoring basado en ventana óptima
        if ($hora >= 6 && $hora < 10) {
            return 2; // ÓPTIMO (6am-10am)
        } elseif ($hora >= 10 && $hora < 14) {
            return 1; // BUENO (10am-2pm)
        } elseif ($hora >= 14 && $hora < 18) {
            return 0; // ACEPTABLE (2pm-6pm)
        } else {
            return -1; // FUERA DE PROTOCOLO
        }
    }

    /**
     * Mapear época del año
     */
    private function mapearEpocaAnio($epoca)
    {
        $mapeo = [
            'verano' => 'VERANO',
            'invierno' => 'INVIERNO',
            'lluvias' => 'LLUVIAS',
            'seca' => 'SECA',
            null => 'VERANO', // Default
        ];

        return $mapeo[strtolower($epoca ?? 'verano')] ?? 'VERANO';
    }

    /**
     * Llamar a la API Flask de Machine Learning
     */
    private function llamarAPIFlask($datos)
    {
        $apiUrl = env('ML_API_URL', 'http://localhost:5000');

        try {
            // Verificar salud de la API primero
            $healthCheck = Http::timeout(5)->get("{$apiUrl}/health");
            
            if (!$healthCheck->successful() || $healthCheck->json('status') !== 'healthy') {
                throw new \Exception('API Flask no está disponible');
            }

            // Hacer la predicción
            $response = Http::timeout(30)->post("{$apiUrl}/api/predict", [
                'data' => $datos,
                'models' => ['random_forest', 'xgboost'] // Solo los 2 modelos disponibles
            ]);

            if (!$response->successful()) {
                $errorBody = $response->json();
                throw new \Exception(
                    $errorBody['message'] ?? 'Error desconocido en API Flask'
                );
            }

            $resultado = $response->json();

            // Transformar respuesta de Flask al formato Laravel
            return $this->transformarRespuestaFlask($resultado);

        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::error('No se pudo conectar a la API Flask', [
                'url' => $apiUrl,
                'error' => $e->getMessage()
            ]);
            
            throw new \Exception(
                'No se pudo conectar a la API de Machine Learning. ' .
                'Verifique que el servidor Flask esté corriendo en ' . $apiUrl
            );
        }
    }

    /**
     * Transformar respuesta de Flask al formato esperado por Laravel
     */
    private function transformarRespuestaFlask($respuestaFlask)
    {
        $consensus = $respuestaFlask['consensus'];
        $predictions = $respuestaFlask['predictions'];
        $riskFactors = $respuestaFlask['risk_factors'] ?? [];

        // Determinar nivel de confianza basado en consenso
        $nivelConfianza = match($consensus['confianza']) {
            'Alta' => 'alto',
            'Media' => 'medio',
            'Baja' => 'bajo',
            default => 'medio'
        };

        // Extraer métricas del modelo desde la respuesta Flask
        $consensusMetrics = $respuestaFlask['model_metrics']['consensus'] ?? [];
        $metricas = [
            'accuracy' => $consensusMetrics['accuracy'] ?? null,
            'precision' => $consensusMetrics['precision'] ?? null,
            'recall' => $consensusMetrics['recall'] ?? null,
            'f1_score' => $consensusMetrics['f1_score'] ?? null,
            'roc_auc' => $consensusMetrics['roc_auc'] ?? null,
        ];

        // Extraer features importantes desde Flask
        $topFeatures = $respuestaFlask['feature_importance'] ?? [];

        // Generar recomendaciones basadas en factores de riesgo
        $recomendaciones = $this->generarRecomendacionesDesdeRiesgos(
            $riskFactors,
            $consensus['probabilidad_promedio']
        );

        return [
            'probabilidad_prenez' => $consensus['probabilidad_promedio'] / 100, // Convertir a decimal
            'prediccion_binaria' => $consensus['prediccion'] === 1,
            'nivel_confianza' => $nivelConfianza,
            'modelo_usado' => 'Consenso (Random Forest + XGBoost)',
            'version_modelo' => '1.0',
            'metricas' => $metricas,
            'top_features' => $topFeatures,
            'recomendaciones' => $recomendaciones,
            'detalles_modelos' => $predictions,
            'factores_riesgo' => $riskFactors,
        ];
    }

    /**
     * Generar recomendaciones desde factores de riesgo
     */
    private function generarRecomendacionesDesdeRiesgos($factoresRiesgo, $probabilidad)
    {
        $recomendaciones = [];

        if ($probabilidad < 40) {
            $recomendaciones[] = "⚠️ Probabilidad baja de preñez ({$probabilidad}%). Evaluar condiciones del animal antes de proceder.";
        }

        foreach ($factoresRiesgo as $factor) {
            if (str_contains($factor, 'Condición ovárica')) {
                $recomendaciones[] = "🔸 {$factor}. Considerar tratamiento hormonal adicional.";
            } elseif (str_contains($factor, 'Tono uterino')) {
                $recomendaciones[] = "💊 {$factor}. Reforzar protocolo de tonificación.";
            } elseif (str_contains($factor, 'Sin tratamiento')) {
                $recomendaciones[] = "⚕️ {$factor}. Aplicar protocolo T1 o T2 antes de IATF.";
            } elseif (str_contains($factor, 'Condición corporal')) {
                $recomendaciones[] = "🥗 {$factor}. Mejorar nutrición y suplementación.";
            } elseif (str_contains($factor, 'posparto')) {
                $recomendaciones[] = "⏱️ {$factor}. Considerar esperar más tiempo para recuperación.";
            } elseif (str_contains($factor, 'desparasitado')) {
                $recomendaciones[] = "💉 {$factor}. Realizar desparasitación antes de IATF.";
            } elseif (str_contains($factor, 'Hora de IATF')) {
                $recomendaciones[] = "🕐 {$factor}. Ajustar horario de inseminación a ventana 6-10 AM.";
            } elseif (str_contains($factor, 'Semental')) {
                $recomendaciones[] = "🐂 {$factor}. Considerar cambiar a semental de mayor calidad.";
            } else {
                $recomendaciones[] = "⚠️ {$factor}";
            }
        }

        if (empty($recomendaciones) && $probabilidad >= 70) {
            $recomendaciones[] = "✅ Condiciones óptimas para IATF. Continuar con protocolo estándar.";
        }

        return implode("\n", $recomendaciones);
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
     * Registrar resultado real de la predicción
     */
    public function registrarResultado(Request $request, $id)
    {
        $prediction = Prediction::find($id);

        if (!$prediction) {
            return response()->json([
                'success' => false,
                'message' => 'Predicción no encontrada',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'resultado_real' => 'required|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Actualizar resultado real
        $prediction->resultado_real = $request->resultado_real;
        
        // Verificar si la predicción fue correcta
        $prediction->prediccion_correcta = (
            $prediction->prediccion_binaria === $request->resultado_real
        );
        
        $prediction->save();

        // Registrar actividad
        ActivityLog::registrar(
            'actualizar',
            'Prediction',
            $prediction->id,
            "Resultado real registrado: " . ($request->resultado_real ? 'gestante' : 'no gestante')
        );

        return response()->json([
            'success' => true,
            'message' => 'Resultado registrado exitosamente',
            'data' => $prediction,
        ]);
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

    /**
     * Verificar estado de la API Flask
     */
    public function healthCheck()
    {
        try {
            $apiUrl = env('ML_API_URL', 'http://localhost:5000');
            $response = Http::timeout(5)->get("{$apiUrl}/health");
            
            if ($response->successful()) {
                return response()->json([
                    'success' => true,
                    'message' => 'API Flask disponible',
                    'data' => $response->json()
                ]);
            }
            
            return response()->json([
                'success' => false,
                'message' => 'API Flask no responde correctamente',
                'status_code' => $response->status()
            ], 503);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'No se pudo conectar a la API Flask',
                'error' => $e->getMessage()
            ], 503);
        }
    }
}