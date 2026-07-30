<?php

use App\Http\Controllers\Api\AssessmentController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BarangayController;
use App\Http\Controllers\Api\MobileReportController;
use App\Http\Controllers\Api\MobileVehicularAccidentController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\RecommendationController;
use App\Http\Controllers\Api\ValidationController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::put('/profile/password', [ProfileController::class, 'updatePassword']);

    Route::middleware('role:field_officer')->group(function () {
        Route::get('/barangays', [BarangayController::class, 'index']);
        Route::apiResource('/reports', MobileReportController::class)
            ->only(['index', 'store', 'update', 'destroy'])
            ->names('api.reports')
            ->parameters(['reports' => 'report']);
        Route::apiResource('/vehicular-accidents', MobileVehicularAccidentController::class)
            ->only(['index', 'store', 'update', 'destroy'])
            ->parameters(['vehicular-accidents' => 'vehicularAccident']);
    });

    Route::middleware('role:mdrrmo')->group(function () {
        Route::get('/dashboard/stats', [AssessmentController::class, 'stats']);
        Route::get('/assessments', [AssessmentController::class, 'index']);
        Route::post('/assessments', [AssessmentController::class, 'store']);
    });

    Route::middleware('role:mdrrmo,validator')->group(function () {
        Route::post('/assessments/{id}/validate', [ValidationController::class, 'validateReport']);
    });

    Route::middleware('role:mdrrmo,dswd')->group(function () {
        Route::get('/recommendations', [RecommendationController::class, 'index']);
    });
});
