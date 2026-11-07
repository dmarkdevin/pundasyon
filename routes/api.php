<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\RoleController;
use App\Http\Controllers\Api\V1\UserController;
use App\Http\Middleware\EnsureApiUserIsActive;
use Illuminate\Support\Facades\Route;

/*
| Version 1. A future breaking change goes in a new v2 group, so v1 keeps working.
| Docs: /docs/api. Generated modules add their routes below the marker.
*/
Route::prefix('v1')->name('api.v1.')->group(function (): void {
    Route::post('auth/login', [AuthController::class, 'login'])->middleware('throttle:api-login')->name('auth.login');

    Route::middleware(['auth:sanctum', EnsureApiUserIsActive::class, 'throttle:api'])->group(function (): void {
        Route::get('auth/me', [AuthController::class, 'me'])->name('auth.me');
        Route::post('auth/logout', [AuthController::class, 'logout'])->name('auth.logout');

        Route::apiResource('users', UserController::class);
        Route::post('users/{user}/restore', [UserController::class, 'restore'])->withTrashed()->name('users.restore');

        Route::apiResource('roles', RoleController::class);

        // Routes of modules made with make:module, one file each.
        foreach (glob(__DIR__.'/api/modules/*.php') ?: [] as $moduleRoutes) {
            require $moduleRoutes;
        }
    });
});
