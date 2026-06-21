<?php

use App\Http\Controllers\Api\AssessmentController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\MobileReportController;
use App\Http\Controllers\Api\MobileVehicularAccidentController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\RecommendationController;
use App\Http\Controllers\Api\ValidationController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/dashboard/stats', [AssessmentController::class, 'stats']);
    Route::get('/assessments', [AssessmentController::class, 'index']);
    Route::post('/assessments', [AssessmentController::class, 'store']);
    Route::post('/assessments/{id}/validate', [ValidationController::class, 'validateReport']);
    Route::get('/recommendations', [RecommendationController::class, 'index']);
    Route::apiResource('/reports', MobileReportController::class)
        ->names('api.reports')
        ->parameters(['reports' => 'report']);
    Route::apiResource('/vehicular-accidents', MobileVehicularAccidentController::class)
        ->parameters(['vehicular-accidents' => 'vehicularAccident']);
    Route::put('/profile/password', [ProfileController::class, 'updatePassword']);
});
