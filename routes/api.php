<?php

use App\Http\Controllers\Auth\AuthenticatedApiController;
use App\Http\Controllers\Auth\RegisteredUserApiController;
use App\Http\Controllers\Api\V1\AmenityController;
use App\Http\Controllers\Api\V1\BookingStatusController;
use App\Http\Controllers\Api\V1\GuestController;
use App\Http\Controllers\Api\V1\HotelController;
use App\Http\Controllers\Api\V1\MediaController;
use App\Http\Controllers\Api\V1\PermissionController;
use App\Http\Controllers\Api\V1\PricingController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\RatePlanController;
use App\Http\Controllers\Api\V1\RoleController;
use App\Http\Controllers\Api\V1\RoomCategoryController;
use App\Http\Controllers\Api\V1\RoomController;
use App\Http\Controllers\Api\V1\ServiceController;
use App\Http\Controllers\Api\V1\StaffController;
use App\Http\Controllers\Api\V1\UserController;
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
    Route::apiResource('permissions', PermissionController::class);
    Route::apiResource('roles', RoleController::class);
    Route::apiResource('users', UserController::class);
    Route::apiResource('hotels', HotelController::class);

    Route::apiResource('amenities', AmenityController::class);
    Route::apiResource('hotels.room-categories', RoomCategoryController::class)->shallow();
    Route::apiResource('hotels.rooms', RoomController::class)->shallow();
    Route::apiResource('hotels.media', MediaController::class)->only(['store', 'destroy'])->shallow();

    Route::apiResource('hotels.rate-plans', RatePlanController::class)->shallow();
    Route::prefix('rate-plans/{rate-plan}')->group(function () {
        Route::get('pricing', [PricingController::class, 'show']);
        Route::post('pricing', [PricingController::class, 'update']);
    });

    Route::apiResource('booking-statuses', BookingStatusController::class);
    Route::apiResource('hotels.services', ServiceController::class)->shallow();
    // Route::prefix('hotels/{hotel}')->group(function () {
    //     Route::get('availability', [BookingAvailabilityController::class, 'index']);
    //     Route::get('bookings', [BookingController::class, 'index']);
    //     Route::post('bookings', [BookingController::class, 'store']);
    // });
    // Route::prefix('bookings/{booking}')->group(function () {
    //     Route::get('/', [BookingController::class, 'show']);
    //     Route::delete('/', [BookingController::class, 'destroy']);
    //     Route::patch('/', [BookingController::class, 'update']);
    
    //     Route::patch('status', [BookingStatusController::class, 'update']); // Изменение статуса
    //     Route::patch('room', [BookingRoomController::class, 'update']); // Смена номера
    //     Route::put('stay-details', [BookingStayDetailsController::class, 'update']); // Даты/Тарифы/Категории
    //     Route::patch('services', [BookingServicesController::class, 'update']); // Массив услуг
    //     Route::patch('guests', [BookingGuestsController::class, 'update']); // Массив гостей
    // });
    Route::get('hotels/{hotel}/guests', [GuestController::class, 'index']);
    Route::apiResource('guests', GuestController::class)->except(['index', 'store']);


    // Route::apiResource('booking-statuses', BookingStatusController::class);
    // Route::apiResource('hotels.services', ServiceController::class)->shallow()->scoped();
    // Route::apiResource('hotels.bookings', BookingController::class)->shallow()->scoped();    
    // Route::get('hotels/{hotel}/guests', [GuestController::class, 'index'])->name('hotels.guests.index');
    // Route::apiResource('guests', GuestController::class)->except(['index']);

    // Route::post('staff', [StaffController::class, 'store']);
    // Route::prefix('hotels/{hotel}')->group(function () {
    //     Route::get('staff', [StaffController::class, 'index']);
    //     Route::put('staff/sync', [StaffController::class, 'sync']);
    //     Route::delete('staff/{user}', [StaffController::class, 'destroy']);
    // });
    

    Route::get('profile', [ProfileController::class, 'show']);
    Route::patch('profile', [ProfileController::class, 'update']);
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
});
