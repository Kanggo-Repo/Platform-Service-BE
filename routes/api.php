<?php

use App\Http\Controllers\Api\V1\PlatformDashboardController;
use App\Http\Controllers\Api\V1\PlatformHealthController;
use App\Http\Controllers\Api\V1\PlatformIdentityController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\RegistrationSettingsController;
use App\Http\Controllers\Api\V1\RoleManagementController;
use App\Http\Controllers\Api\V1\UserManagementController;
use App\Http\Middleware\AuthenticatePlatformToken;
use App\Http\Middleware\EnsurePlatformOperatorRole;
use Illuminate\Support\Facades\Route;
use Spatie\Health\Http\Controllers\HealthCheckJsonResultsController;

Route::prefix('v1')->group(function (): void {
    Route::get('/health', PlatformHealthController::class);
    Route::get('/health/json', HealthCheckJsonResultsController::class);

    Route::middleware(AuthenticatePlatformToken::class)->group(function (): void {
        Route::get('/me', [PlatformIdentityController::class, 'me']);
        Route::get('/navigation', [PlatformIdentityController::class, 'navigation']);
        Route::get('/profile', [ProfileController::class, 'show']);
        Route::put('/profile', [ProfileController::class, 'update']);

        Route::middleware(EnsurePlatformOperatorRole::class)->group(function (): void {
            Route::get('/dashboard', PlatformDashboardController::class);
            Route::get('/settings/registration', [RegistrationSettingsController::class, 'show']);
            Route::put('/settings/registration', [RegistrationSettingsController::class, 'update']);
            Route::get('/permissions', [RoleManagementController::class, 'permissions']);
            Route::get('/roles', [RoleManagementController::class, 'index']);
            Route::post('/roles', [RoleManagementController::class, 'store']);
            Route::put('/roles/{role}', [RoleManagementController::class, 'update']);
            Route::delete('/roles/{role}', [RoleManagementController::class, 'destroy']);
            Route::get('/users', [UserManagementController::class, 'index']);
            Route::post('/users', [UserManagementController::class, 'store']);
            Route::put('/users/{user}', [UserManagementController::class, 'update']);
            Route::delete('/users/{user}', [UserManagementController::class, 'destroy']);
        });
    });
});
