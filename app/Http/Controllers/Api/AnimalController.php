<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Animal;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AnimalController extends Controller
{
    /**
     * Listar todos los animales
     */
    public function index(Request $request)
    {
        $query = Animal::with(['ultimoIatf', 'grupo']);

        // Filtro opcional
        if ($request->has('activo')) {
            // Convertir string a boolean
            $activo = filter_var($request->activo, FILTER_VALIDATE_BOOLEAN);
            $query->where('activo', $activo);
        }

        if ($request->has('grupo_id')) {
            $query->where('grupo_id', $request->grupo_id);
        }

        if ($request->has('grupo_lote')) {
            $query->where('grupo_lote', $request->grupo_lote);
        }

        if ($request->has('estado_reproductivo')) {
            $query->where('estado_reproductivo', $request->estado_reproductivo);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('arete', 'like', "%{$search}%")
                  ->orWhere('grupo_lote', 'like', "%{$search}%")
                  ->orWhereHas('grupo', function($gq) use ($search) {
                      $gq->where('nombre', 'like', "%{$search}%");
                  });
            });
        }

        $animales = $query->orderBy('arete')->paginate(15);

        return response()->json([
            'success' => true,
            'data' => $animales,
        ]);
    }

    /**
     * Crear nuevo animal
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'arete' => 'required|string|unique:animals,arete',
            'grupo_id' => 'nullable|exists:grupos,id',
            'grupo_lote' => 'nullable|string',
            'edad_meses' => 'nullable|integer|min:0',
            'peso_kg' => 'nullable|numeric|min:0',
            'condicion_corporal' => 'nullable|numeric|between:1,5',
            'numero_partos' => 'nullable|integer|min:0',
            'dias_posparto' => 'nullable|integer|min:0',
            'dias_abiertos' => 'nullable|integer|min:0',
            'historial_abortos' => 'boolean',
            'numero_abortos' => 'nullable|integer|min:0',
            'enfermedades_reproductivas' => 'boolean',
            'descripcion_enfermedades' => 'nullable|string',
            'estado_reproductivo' => 'nullable|in:activa,prenada,seca,descarte',
            'fecha_ultimo_tratamiento' => 'nullable|date',
            'observaciones' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $validator->errors(),
            ], 422);
        }

        $animal = Animal::create($request->all());

        // Registrar actividad
        ActivityLog::registrar(
            'crear',
            'Animal',
            $animal->id,
            "Animal {$animal->arete} creado"
        );

        return response()->json([
            'success' => true,
            'message' => 'Animal creado exitosamente',
            'data' => $animal,
        ], 201);
    }

    /**
     * Mostrar un animal específico
     */
    public function show($id)
    {
        $animal = Animal::with(['iatfRecords.semental', 'iatfRecords.prediction'])
                        ->find($id);

        if (!$animal) {
            return response()->json([
                'success' => false,
                'message' => 'Animal no encontrado',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $animal,
        ]);
    }

    /**
     * Actualizar animal
     */
    public function update(Request $request, $id)
    {
        $animal = Animal::find($id);

        if (!$animal) {
            return response()->json([
                'success' => false,
                'message' => 'Animal no encontrado',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'arete' => 'required|string|unique:animals,arete,' . $id,
            'grupo_id' => 'nullable|exists:grupos,id',
            'grupo_lote' => 'nullable|string',
            'edad_meses' => 'nullable|integer|min:0',
            'peso_kg' => 'nullable|numeric|min:0',
            'condicion_corporal' => 'nullable|numeric|between:1,5',
            'numero_partos' => 'nullable|integer|min:0',
            'dias_posparto' => 'nullable|integer|min:0',
            'dias_abiertos' => 'nullable|integer|min:0',
            'estado_reproductivo' => 'nullable|in:activa,prenada,seca,descarte',
            'fecha_ultimo_tratamiento' => 'nullable|date',
            'activo' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $validator->errors(),
            ], 422);
        }

        $datosAnteriores = $animal->toArray();
        $animal->update($request->all());

        // Registrar actividad
        ActivityLog::registrar(
            'actualizar',
            'Animal',
            $animal->id,
            "Animal {$animal->arete} actualizado",
            $datosAnteriores,
            $animal->toArray()
        );

        return response()->json([
            'success' => true,
            'message' => 'Animal actualizado exitosamente',
            'data' => $animal,
        ]);
    }

    /**
     * Eliminar animal (soft delete)
     */
    public function destroy($id)
    {
        $animal = Animal::find($id);

        if (!$animal) {
            return response()->json([
                'success' => false,
                'message' => 'Animal no encontrado',
            ], 404);
        }

        $animal->delete();

        // Registrar actividad
        ActivityLog::registrar(
            'eliminar',
            'Animal',
            $id,
            "Animal {$animal->arete} eliminado"
        );

        return response()->json([
            'success' => true,
            'message' => 'Animal eliminado exitosamente',
        ]);
    }

    /**
     * Obtener estadísticas del animal
     */
    public function estadisticas($id)
    {
        $animal = Animal::with(['iatfRecords'])->find($id);

        if (!$animal) {
            return response()->json([
                'success' => false,
                'message' => 'Animal no encontrado',
            ], 404);
        }

        $totalIatf = $animal->iatfRecords->count();
        $prenecesConfirmadas = $animal->iatfRecords->where('resultado_iatf', 'confirmada')->count();
        $muertesEmbrionarias = $animal->iatfRecords->where('resultado_iatf', 'muerte_embrionaria')->count();

        $tasaPrenez = $totalIatf > 0 ? ($prenecesConfirmadas / $totalIatf) * 100 : 0;

        return response()->json([
            'success' => true,
            'data' => [
                'animal' => $animal,
                'estadisticas' => [
                    'total_iatf' => $totalIatf,
                    'preneces_confirmadas' => $prenecesConfirmadas,
                    'muertes_embrionarias' => $muertesEmbrionarias,
                    'tasa_prenez' => round($tasaPrenez, 2),
                ],
            ],
        ]);
    }
}
