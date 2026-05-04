<?php

use App\Http\Controllers\Auth\AuthenticatedApiController;
use App\Http\Controllers\Auth\RegisteredUserApiController;
use App\Http\Controllers\AmenityController;
use App\Http\Controllers\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthenticatedApiController::class, 'store'])
    ->middleware('guest')
    ->name('login');

Route::post('/register', [RegisteredUserApiController::class, 'store'])
    ->middleware('guest')
    ->name('register');

Route::post('/logout', [AuthenticatedAPiController::class, 'destroy'])
    ->middleware('auth:sanctum')
    ->name('logout');

Route::prefix('v1')->group(function () {
    
});
    
Route::middleware('auth:sanctum')->group(function () {        
    Route::apiResource('amenities', AmenityController::class);
    Route::apiResource('users', UserController::class);
    
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
});

        
