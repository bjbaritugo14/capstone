<?php

use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\ResetPasswordController;
use App\Http\Controllers\AccidentController;
use App\Http\Controllers\AdminIncidentManagementController;
use App\Http\Controllers\AdminRecommendationHistoryController;
use App\Http\Controllers\AffectedFamilyController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DswdDashboardController;
use App\Http\Controllers\DswdRecommendationController;
use App\Http\Controllers\RecommendationController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SystemSettingsController;
use App\Http\Controllers\UserManagementController;
use App\Http\Controllers\ValidationController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('login'));

Route::get('/login', [LoginController::class, 'create'])->name('login');
Route::post('/login', [LoginController::class, 'store'])->name('login.store');
Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

Route::get('/forgot-password', [ForgotPasswordController::class, 'create'])->name('password.request');
Route::post('/forgot-password', [ForgotPasswordController::class, 'store'])->name('password.email');
Route::get('/reset-password/{token}', [ResetPasswordController::class, 'create'])->name('password.reset');
Route::post('/reset-password', [ResetPasswordController::class, 'store'])->name('password.update');

Route::middleware('role:mdrrmo')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/reports/print', [ReportController::class, 'print'])->name('reports.print');
    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
    Route::post('/reports', [ReportController::class, 'store'])->name('reports.store');
    Route::get('/accidents', [AccidentController::class, 'index'])->name('accidents.index');
    Route::post('/accidents', [AccidentController::class, 'store'])->name('accidents.store');
    Route::get('/validation', [ValidationController::class, 'index'])->name('validation.index');
    Route::get('/validation/report/{report}', [ValidationController::class, 'showReport'])->name('validation.show-report');
    Route::post('/validation/report/{report}/validate', [ValidationController::class, 'validateReport'])->name('validation.validate-report');
    Route::post('/validation/report/{report}/return', [ValidationController::class, 'returnReport'])->name('validation.return-report');
    Route::get('/validation/accident/{accident}', [ValidationController::class, 'showAccident'])->name('validation.show-accident');
    Route::post('/validation/accident/{accident}/validate', [ValidationController::class, 'validateAccident'])->name('validation.validate-accident');
    Route::post('/validation/accident/{accident}/return', [ValidationController::class, 'returnAccident'])->name('validation.return-accident');
});

Route::middleware('role:mdrrmo,dswd')->group(function () {
    Route::get('/affected-families', [AffectedFamilyController::class, 'index'])->name('affected-families.index');
    Route::delete('/affected-families/{report}', [AffectedFamilyController::class, 'destroy'])->name('affected-families.destroy');
});

Route::prefix('dswd')->middleware('role:dswd')->group(function () {
    Route::get('/dashboard', [DswdDashboardController::class, 'index'])->name('dswd.dashboard');
    Route::get('/recommendations', [DswdRecommendationController::class, 'index'])->name('dswd.recommendations');
});

Route::prefix('admin')->middleware('role:super_admin,admin')->group(function () {
    Route::get('/users', [UserManagementController::class, 'index'])->name('admin.users');
    Route::post('/users', [UserManagementController::class, 'store'])->name('admin.users.store');
    Route::put('/users/{user}', [UserManagementController::class, 'update'])->name('admin.users.update');
    Route::put('/users/{user}/password', [UserManagementController::class, 'updatePassword'])->name('admin.users.password');
    Route::get('/incident-management', [AdminIncidentManagementController::class, 'index'])->name('admin.incident-management');
    Route::put('/incident-management/reports/{report}', [AdminIncidentManagementController::class, 'updateReport'])->name('admin.incident-management.reports.update');
    Route::put('/incident-management/accidents/{accident}', [AdminIncidentManagementController::class, 'updateAccident'])->name('admin.incident-management.accidents.update');
    Route::post('/incident-management/reports/{report}/archive', [AdminIncidentManagementController::class, 'archiveReport'])->name('admin.incident-management.reports.archive');
    Route::post('/incident-management/accidents/{accident}/archive', [AdminIncidentManagementController::class, 'archiveAccident'])->name('admin.incident-management.accidents.archive');
    Route::get('/recommendations', [RecommendationController::class, 'index'])->name('admin.recommendations.index');
    Route::post('/recommendations/reports/{report}', [RecommendationController::class, 'generate'])->name('admin.recommendations.generate');
    Route::get('/recommendation-history', [AdminRecommendationHistoryController::class, 'index'])->name('admin.recommendation-history');
    Route::get('/settings', [SystemSettingsController::class, 'index'])->name('admin.settings');
});
