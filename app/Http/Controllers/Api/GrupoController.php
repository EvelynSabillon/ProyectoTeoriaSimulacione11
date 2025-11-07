<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Grupo;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class GrupoController extends Controller
{
    /**
     * Listar todos los grupos
     */
    public function index(Request $request)
    {
        $query = Grupo::withCount('animals');

        // Filtro opcional
        if ($request->has('activo')) {
            $query->where('activo', $request->activo);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('nombre', 'like', "%{$search}%")
                  ->orWhere('descripcion', 'like', "%{$search}%");
            });
        }

        $grupos = $query->orderBy('nombre')->paginate(15);

        return response()->json([
            'success' => true,
            'data' => $grupos,
        ]);
    }

    /**
     * Crear nuevo grupo
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nombre' => 'required|string|max:50|unique:grupos,nombre',
            'descripcion' => 'nullable|string|max:255',
            'activo' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $validator->errors(),
            ], 422);
        }

        $grupo = Grupo::create($request->all());

        // Registrar actividad
        ActivityLog::registrar(
            'crear',
            'Grupo',
            $grupo->id,
            "Grupo {$grupo->nombre} creado"
        );

        return response()->json([
            'success' => true,
            'message' => 'Grupo creado exitosamente',
            'data' => $grupo,
        ], 201);
    }

    /**
     * Mostrar un grupo específico
     */
    public function show($id)
    {
        $grupo = Grupo::with(['animals' => function($query) {
            $query->where('activo', true);
        }])->find($id);

        if (!$grupo) {
            return response()->json([
                'success' => false,
                'message' => 'Grupo no encontrado',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $grupo,
        ]);
    }

    /**
     * Actualizar grupo
     */
    public function update(Request $request, $id)
    {
        $grupo = Grupo::find($id);

        if (!$grupo) {
            return response()->json([
                'success' => false,
                'message' => 'Grupo no encontrado',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'nombre' => 'required|string|max:50|unique:grupos,nombre,' . $id,
            'descripcion' => 'nullable|string|max:255',
            'activo' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $validator->errors(),
            ], 422);
        }

        $datosAnteriores = $grupo->toArray();
        $grupo->update($request->all());

        // Registrar actividad
        ActivityLog::registrar(
            'actualizar',
            'Grupo',
            $grupo->id,
            "Grupo {$grupo->nombre} actualizado",
            $datosAnteriores,
            $grupo->toArray()
        );

        return response()->json([
            'success' => true,
            'message' => 'Grupo actualizado exitosamente',
            'data' => $grupo,
        ]);
    }

    /**
     * Eliminar grupo
     */
    public function destroy($id)
    {
        $grupo = Grupo::find($id);

        if (!$grupo) {
            return response()->json([
                'success' => false,
                'message' => 'Grupo no encontrado',
            ], 404);
        }

        // Verificar si tiene animales asociados
        if ($grupo->animals()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => 'No se puede eliminar el grupo porque tiene animales asociados',
            ], 422);
        }

        $nombre = $grupo->nombre;
        $grupo->delete();

        // Registrar actividad
        ActivityLog::registrar(
            'eliminar',
            'Grupo',
            $id,
            "Grupo {$nombre} eliminado"
        );

        return response()->json([
            'success' => true,
            'message' => 'Grupo eliminado exitosamente',
        ]);
    }

    /**
     * Obtener estadísticas del grupo
     */
    public function estadisticas($id)
    {
        $grupo = Grupo::with(['animals.iatfRecords'])->find($id);

        if (!$grupo) {
            return response()->json([
                'success' => false,
                'message' => 'Grupo no encontrado',
            ], 404);
        }

        $totalAnimales = $grupo->animals->count();
        $animalesActivos = $grupo->animals->where('activo', true)->count();
        
        $estadosReproductivos = $grupo->animals->groupBy('estado_reproductivo')
            ->map(function($items, $key) {
                return $items->count();
            });

        $totalIatf = 0;
        $prenecesConfirmadas = 0;
        foreach ($grupo->animals as $animal) {
            $totalIatf += $animal->iatfRecords->count();
            $prenecesConfirmadas += $animal->iatfRecords->where('resultado_iatf', 'confirmada')->count();
        }

        $tasaPrenez = $totalIatf > 0 ? ($prenecesConfirmadas / $totalIatf) * 100 : 0;

        return response()->json([
            'success' => true,
            'data' => [
                'grupo' => $grupo,
                'estadisticas' => [
                    'total_animales' => $totalAnimales,
                    'animales_activos' => $animalesActivos,
                    'estados_reproductivos' => $estadosReproductivos,
                    'total_iatf' => $totalIatf,
                    'preneces_confirmadas' => $prenecesConfirmadas,
                    'tasa_prenez' => round($tasaPrenez, 2),
                ],
            ],
        ]);
    }
}
