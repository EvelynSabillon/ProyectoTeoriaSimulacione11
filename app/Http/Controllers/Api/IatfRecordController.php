<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\IatfRecord;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class IatfRecordController extends Controller
{
    /**
     * Listar todos los registros IATF
     */
    public function index(Request $request)
    {
        $query = IatfRecord::with(['animal', 'semental', 'prediction']);

        // Filtros
        if ($request->has('animal_id')) {
            $query->where('animal_id', $request->animal_id);
        }

        if ($request->has('resultado_iatf')) {
            $query->where('resultado_iatf', $request->resultado_iatf);
        }

        if ($request->has('fecha_inicio') && $request->has('fecha_fin')) {
            $query->whereBetween('fecha_iatf', [$request->fecha_inicio, $request->fecha_fin]);
        }

        if ($request->has('prenez_confirmada')) {
            $query->where('prenez_confirmada', $request->prenez_confirmada);
        }

        $records = $query->orderBy('fecha_iatf', 'desc')->paginate(15);

        return response()->json([
            'success' => true,
            'data' => $records,
        ]);
    }

    /**
     * Crear nuevo registro IATF
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'animal_id' => 'required|exists:animals,id',
            'semental_id' => 'nullable|exists:sementales,id',
            'fecha_iatf' => 'required|date',
            'fecha_protocolo_dia_0' => 'nullable|date',
            'fecha_protocolo_dia_8' => 'nullable|date',
            'fecha_protocolo_dia_9' => 'nullable|date',
            'fecha_protocolo_dia_10' => 'nullable|date',
            'condicion_ovarica_od' => 'nullable|in:C,CL,FD,F,I,A',
            'condicion_ovarica_oi' => 'nullable|in:C,CL,FD,F,I,A',
            'tono_uterino' => 'nullable|numeric|between:0,100',
            'tratamiento_previo' => 'nullable|in:T1,T2,RS,DESCARTE',
            'dias_tonificacion' => 'nullable|integer|min:0',
            'sal_mineral_gr' => 'nullable|numeric|min:0',
            'modivitasan_ml' => 'nullable|numeric|min:0',
            'fosfoton_ml' => 'nullable|numeric|min:0',
            'seve_ml' => 'nullable|numeric|min:0',
            'desparasitacion_previa' => 'boolean',
            'vitaminas_aplicadas' => 'boolean',
            'dispositivo_dib' => 'boolean',
            'estradiol_ml' => 'nullable|numeric|min:0',
            'retirada_dib' => 'boolean',
            'ecg_ml' => 'nullable|numeric|min:0',
            'pf2_alpha_ml' => 'nullable|numeric|min:0',
            'hora_iatf' => 'nullable|date_format:H:i',
            'epoca_anio' => 'nullable|in:verano,invierno,lluvias',
            'temperatura_ambiente' => 'nullable|numeric',
            'humedad_relativa' => 'nullable|numeric|between:0,100',
            'estres_manejo' => 'nullable|integer|between:1,5',
            'calidad_pasturas' => 'nullable|integer|between:1,5',
            'disponibilidad_agua' => 'nullable|in:adecuada,limitada',
            'gestacion_previa' => 'boolean',
            'dias_gestacion_previa' => 'nullable|integer|min:0|max:280',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $validator->errors(),
            ], 422);
        }

        $iatfRecord = IatfRecord::create($request->all());

        ActivityLog::registrar(
            'crear',
            'IatfRecord',
            $iatfRecord->id,
            "Registro IATF creado para animal ID: {$iatfRecord->animal_id}"
        );

        return response()->json([
            'success' => true,
            'message' => 'Registro IATF creado exitosamente',
            'data' => $iatfRecord->load(['animal', 'semental']),
        ], 201);
    }

    /**
     * Mostrar un registro IATF específico
     */
    public function show($id)
    {
        $iatfRecord = IatfRecord::with(['animal', 'semental', 'prediction'])
                                ->find($id);

        if (!$iatfRecord) {
            return response()->json([
                'success' => false,
                'message' => 'Registro IATF no encontrado',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $iatfRecord,
        ]);
    }

    /**
     * Actualizar registro IATF
     */
    public function update(Request $request, $id)
    {
        $iatfRecord = IatfRecord::find($id);

        if (!$iatfRecord) {
            return response()->json([
                'success' => false,
                'message' => 'Registro IATF no encontrado',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'animal_id' => 'required|exists:animals,id',
            'semental_id' => 'nullable|exists:sementales,id',
            'fecha_iatf' => 'required|date',
            'resultado_iatf' => 'nullable|in:confirmada,no_prenada,muerte_embrionaria,pendiente',
            'prenez_confirmada' => 'nullable|boolean',
            'fecha_confirmacion' => 'nullable|date',
            'dias_gestacion_confirmada' => 'nullable|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $validator->errors(),
            ], 422);
        }

        $datosAnteriores = $iatfRecord->toArray();
        $iatfRecord->update($request->all());

        ActivityLog::registrar(
            'actualizar',
            'IatfRecord',
            $iatfRecord->id,
            "Registro IATF actualizado",
            $datosAnteriores,
            $iatfRecord->toArray()
        );

        return response()->json([
            'success' => true,
            'message' => 'Registro IATF actualizado exitosamente',
            'data' => $iatfRecord->load(['animal', 'semental']),
        ]);
    }

    /**
     * Confirmar resultado de IATF
     */
    public function confirmarResultado(Request $request, $id)
    {
        $iatfRecord = IatfRecord::find($id);

        if (!$iatfRecord) {
            return response()->json([
                'success' => false,
                'message' => 'Registro IATF no encontrado',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'resultado_iatf' => 'required|in:confirmada,no_prenada,muerte_embrionaria',
            'fecha_confirmacion' => 'required|date',
            'dias_gestacion_confirmada' => 'nullable|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Actualizar resultado
        $iatfRecord->update([
            'resultado_iatf' => $request->resultado_iatf,
            'prenez_confirmada' => $request->resultado_iatf === 'confirmada',
            'fecha_confirmacion' => $request->fecha_confirmacion,
            'dias_gestacion_confirmada' => $request->dias_gestacion_confirmada ?? 45,
        ]);

        // Si existe predicción, validarla
        if ($iatfRecord->prediction) {
            $iatfRecord->prediction->validarConResultadoReal(
                $request->resultado_iatf === 'confirmada'
            );
        }

        // Actualizar estadísticas del semental si existe
        if ($iatfRecord->semental_id) {
            $iatfRecord->semental->actualizarEstadisticas();
        }

        ActivityLog::registrar(
            'confirmar_resultado',
            'IatfRecord',
            $iatfRecord->id,
            "Resultado IATF confirmado: {$request->resultado_iatf}"
        );

        return response()->json([
            'success' => true,
            'message' => 'Resultado confirmado exitosamente',
            'data' => $iatfRecord->load(['animal', 'semental', 'prediction']),
        ]);
    }

    /**
     * Eliminar registro IATF
     */
    public function destroy($id)
    {
        $iatfRecord = IatfRecord::find($id);

        if (!$iatfRecord) {
            return response()->json([
                'success' => false,
                'message' => 'Registro IATF no encontrado',
            ], 404);
        }

        $iatfRecord->delete();

        ActivityLog::registrar(
            'eliminar',
            'IatfRecord',
            $id,
            "Registro IATF eliminado"
        );

        return response()->json([
            'success' => true,
            'message' => 'Registro IATF eliminado exitosamente',
        ]);
    }
}
