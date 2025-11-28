<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Semental;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SementalController extends Controller
{
    /**
     * Listar todos los sementales
     */
    public function index(Request $request)
    {
        $query = Semental::query();

        // Filtro opcional
        if ($request->has('activo')) {
            // Convertir string a boolean
            $activo = filter_var($request->activo, FILTER_VALIDATE_BOOLEAN);
            $query->where('activo', $activo);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('nombre', 'like', "%{$search}%")
                  ->orWhere('raza', 'like', "%{$search}%")
                  ->orWhere('codigo_pajilla', 'like', "%{$search}%");
            });
        }

        $sementales = $query->orderBy('nombre')->paginate(15);

        return response()->json([
            'success' => true,
            'data' => $sementales,
        ]);
    }

    /**
     * Crear nuevo semental
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nombre' => 'required|string|unique:sementales,nombre',
            'raza' => 'nullable|string',
            'codigo_pajilla' => 'nullable|string',
            'calidad_seminal' => 'nullable|numeric|between:0,100',
            'concentracion_espermatica' => 'nullable|numeric|min:0',
            'morfologia_espermatica' => 'nullable|numeric|between:0,100',
            'proveedor' => 'nullable|string',
            'fecha_adquisicion' => 'nullable|date',
            'precio_pajilla' => 'nullable|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $validator->errors(),
            ], 422);
        }

        $semental = Semental::create($request->all());

        ActivityLog::registrar(
            'crear',
            'Semental',
            $semental->id,
            "Semental {$semental->nombre} creado"
        );

        return response()->json([
            'success' => true,
            'message' => 'Semental creado exitosamente',
            'data' => $semental,
        ], 201);
    }

    /**
     * Mostrar un semental específico
     */
    public function show($id)
    {
        $semental = Semental::with(['iatfRecords'])->find($id);

        if (!$semental) {
            return response()->json([
                'success' => false,
                'message' => 'Semental no encontrado',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $semental,
        ]);
    }

    /**
     * Actualizar semental
     */
    public function update(Request $request, $id)
    {
        $semental = Semental::find($id);

        if (!$semental) {
            return response()->json([
                'success' => false,
                'message' => 'Semental no encontrado',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'nombre' => 'required|string|unique:sementales,nombre,' . $id,
            'raza' => 'nullable|string',
            'codigo_pajilla' => 'nullable|string',
            'calidad_seminal' => 'nullable|numeric|between:0,100',
            'concentracion_espermatica' => 'nullable|numeric|min:0',
            'morfologia_espermatica' => 'nullable|numeric|between:0,100',
            'activo' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $validator->errors(),
            ], 422);
        }

        $semental->update($request->all());

        ActivityLog::registrar(
            'actualizar',
            'Semental',
            $semental->id,
            "Semental {$semental->nombre} actualizado"
        );

        return response()->json([
            'success' => true,
            'message' => 'Semental actualizado exitosamente',
            'data' => $semental,
        ]);
    }

    /**
     * Eliminar semental
     */
    public function destroy($id)
    {
        $semental = Semental::find($id);

        if (!$semental) {
            return response()->json([
                'success' => false,
                'message' => 'Semental no encontrado',
            ], 404);
        }

        $semental->delete();

        ActivityLog::registrar(
            'eliminar',
            'Semental',
            $id,
            "Semental {$semental->nombre} eliminado"
        );

        return response()->json([
            'success' => true,
            'message' => 'Semental eliminado exitosamente',
        ]);
    }

    /**
     * Actualizar estadísticas del semental
     */
    public function actualizarEstadisticas($id)
    {
        $semental = Semental::find($id);

        if (!$semental) {
            return response()->json([
                'success' => false,
                'message' => 'Semental no encontrado',
            ], 404);
        }

        $semental->actualizarEstadisticas();

        return response()->json([
            'success' => true,
            'message' => 'Estadísticas actualizadas',
            'data' => $semental,
        ]);
    }
}