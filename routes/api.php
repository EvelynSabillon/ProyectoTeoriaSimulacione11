<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AnimalController;
use App\Http\Controllers\Api\SementalController;
use App\Http\Controllers\Api\IatfRecordController;
use App\Http\Controllers\Api\PredictionController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\GrupoController;
use App\Http\Controllers\Api\AuthController;

/*
|--------------------------------------------------------------------------
| API Routes - BOVIPRED
|--------------------------------------------------------------------------
*/

// Rutas versiónadas
Route::prefix('v1')->group(function () {

    // --- RUTAS PÚBLICAS: AUTH ---
    Route::post('auth/register', [AuthController::class, 'register']);
    Route::post('auth/login', [AuthController::class, 'login']);

    // Rutas protegidas por token
    Route::middleware('auth:sanctum')->group(function () {
        // Auth
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::get('auth/profile', [AuthController::class, 'profile']);
        Route::put('auth/profile', [AuthController::class, 'updateProfile']);
        Route::post('auth/change-password', [AuthController::class, 'changePassword']);
        Route::get('auth/users', [AuthController::class, 'listUsers']);
        Route::post('auth/users/{id}/toggle', [AuthController::class, 'toggleUserStatus']);

        // === GRUPOS ===
        Route::apiResource('grupos', GrupoController::class);
        Route::get('grupos/{id}/estadisticas', [GrupoController::class, 'estadisticas']);

        // === ANIMALES ===
        Route::apiResource('animals', AnimalController::class);
        Route::get('animals/{id}/estadisticas', [AnimalController::class, 'estadisticas']);

        // === SEMENTALES ===
        Route::apiResource('sementales', SementalController::class);
        Route::post('sementales/{id}/actualizar-estadisticas', [SementalController::class, 'actualizarEstadisticas']);

        // === REGISTROS IATF ===
        Route::apiResource('iatf-records', IatfRecordController::class);
        Route::post('iatf-records/{id}/confirmar-resultado', [IatfRecordController::class, 'confirmarResultado']);

        // === PREDICCIONES ===
        Route::apiResource('predictions', PredictionController::class)->only(['index', 'store', 'show']);
        Route::get('predictions/estadisticas/general', [PredictionController::class, 'estadisticas']);

        // === REPORTES ===
        Route::get('reports', [ReportController::class, 'index']);
        Route::get('reports/{id}', [ReportController::class, 'show']);
        Route::delete('reports/{id}', [ReportController::class, 'destroy']);
        Route::get('dashboard', [ReportController::class, 'dashboard']);

        // Generación de reportes específicos
        Route::post('reports/tasas-prenez', [ReportController::class, 'generarReporteTasasPrenez']);
        Route::post('reports/efectividad-protocolo', [ReportController::class, 'generarReporteEfectividadProtocolo']);
        Route::post('reports/analisis-semental', [ReportController::class, 'generarReporteSemental']);
        Route::post('reports/rendimiento-ml', [ReportController::class, 'generarReporteRendimientoML']);
    });

});

// Ruta de prueba
Route::get('/test', function () {
    return response()->json([
        'success' => true,
        'message' => 'API BOVIPRED funcionando correctamente',
        'version' => '1.0.0',
        'timestamp' => now(),
    ]);
});