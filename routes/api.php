<?php

use App\Http\Controllers\Auth\AuthenticatedApiController;
use App\Http\Controllers\Auth\RegisteredUserApiController;
use App\Http\Controllers\Api\V1\AmenityController;
use App\Http\Controllers\Api\V1\HotelController;
use App\Http\Controllers\Api\V1\PermissionController;
use App\Http\Controllers\Api\V1\RoleController;
use App\Http\Controllers\Api\V1\StaffController;
use App\Http\Controllers\Api\V1\UserController;
use App\Models\Hotel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthenticatedApiController::class, 'store'])
    ->middleware('guest')
    ->name('login');

Route::post('/register', [RegisteredUserApiController::class, 'store'])
    ->middleware('guest')
    ->name('register');

Route::post('/logout', [AuthenticatedApiController::class, 'destroy'])
    ->middleware('auth:sanctum')
    ->name('logout');

Route::prefix('v1')->group(function () {});

Route::middleware('auth:sanctum')->group(function () {
    Route::apiResource('amenities', AmenityController::class);
    Route::apiResource('users', UserController::class);
    Route::apiResource('roles', RoleController::class);
    Route::apiResource('permissions', PermissionController::class);
    Route::apiResource('hotels', HotelController::class)->middleware('can:viewAny,' . Hotel::class);

    // Route::post('staff', [StaffController::class, 'store']);
    // Route::prefix('hotels/{hotel}')->group(function () {
    //     Route::get('staff', [StaffController::class, 'index']);
    //     Route::put('staff/sync', [StaffController::class, 'sync']);
    //     Route::delete('staff/{user}', [StaffController::class, 'destroy']);
    // });

    Route::get('/user', function (Request $request) {
        return $request->user();
    });
});
