<?php

use App\Http\Controllers\Api\AdminRepairController;
use App\Http\Controllers\Api\AdminValidationController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\HeatmapController;
use App\Http\Controllers\Api\RegisterOtpController;
use App\Http\Controllers\Api\ReportController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    // Authentication endpoints (F1, F2, OAuth)
    Route::prefix('auth')->group(function () {
        Route::post('/login', [AuthController::class, 'login']);
        Route::post('/otp/verify', [RegisterOtpController::class, 'verify']);
        Route::post('/otp/resend', [RegisterOtpController::class, 'resend']);
        Route::post('/register', [AuthController::class, 'register']);
        Route::post('/register/otp/verify', [RegisterOtpController::class, 'verify']);
        Route::post('/register/otp/resend', [RegisterOtpController::class, 'resend']);
        Route::get('/google/redirect', [AuthController::class, 'googleRedirect']);
        Route::get('/google/callback', [AuthController::class, 'googleCallback']);

        // Facebook OAuth Routes
        Route::get('/facebook/redirect', [AuthController::class, 'facebookRedirect']);
        Route::get('/facebook/callback', [AuthController::class, 'facebookCallback']);

        Route::middleware('auth:sanctum')->group(function () {
            Route::post('/logout', [AuthController::class, 'logout']);
            Route::get('/me', [AuthController::class, 'me']);
            Route::post('/update-phone', [AuthController::class, 'updatePhone']); // <-- Ditambahkan di sini
        });
    });

    // Public / Protected categories and segments (F3)
    Route::get('/categories', [ReportController::class, 'categories']);
    Route::get('/segments', [ReportController::class, 'segments']);

    // Signed photo route (FR-F3-14)
    Route::get('/reports/{id}/photo', [ReportController::class, 'photo'])
        ->name('report.photo')
        ->middleware('signed');

    // Authenticated routes
    Route::middleware('auth:sanctum')->group(function () {
        // Warga specific routes
        Route::middleware('role:warga')->group(function () {
            Route::post('/reports', [ReportController::class, 'store']);
            Route::get('/reports/my-summary', [ReportController::class, 'mySummary']);
            Route::get('/reports/my-reports', [ReportController::class, 'myReports']);
        });

        // Common report detail (accessible by report owner or admin)
        Route::get('/reports/{id}', [ReportController::class, 'show']);

        // Heatmap endpoint (F6 - accessible by warga and admin)
        Route::get('/heatmap', [HeatmapController::class, 'index']);

        // Admin specific validation & repair routes (F5, F9)
        Route::middleware('role:admin')->prefix('admin')->group(function () {
            Route::get('/reports', [AdminValidationController::class, 'index']);
            Route::post('/reports/{id}/validation', [AdminValidationController::class, 'validateReport']);
            Route::match(['patch', 'post'], '/reports/{id}/repair-status', [AdminRepairController::class, 'updateRepairStatus']);
            Route::get('/statistics', [AdminValidationController::class, 'statistics']);
        });
    });
});