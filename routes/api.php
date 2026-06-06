<?php

use App\Http\Controllers\Auth\AuthenticatedApiController;
use App\Http\Controllers\Auth\RegisteredUserApiController;
use App\Http\Controllers\Api\V1\AmenityController;
use App\Http\Controllers\Api\V1\Booking\BookingController;
use App\Http\Controllers\Api\V1\BookingStatusController;
use App\Http\Controllers\Api\V1\GuestController;
use App\Http\Controllers\Api\V1\HotelController;
use App\Http\Controllers\Api\V1\MediaController;
use App\Http\Controllers\Api\V1\PermissionController;
use App\Http\Controllers\Api\V1\PricingController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\RatePlanController;
use App\Http\Controllers\Api\V1\RoleController;
use App\Http\Controllers\Api\V1\RoomCategoryAmenityController;
use App\Http\Controllers\Api\V1\RoomCategoryController;
use App\Http\Controllers\Api\V1\RoomController;
use App\Http\Controllers\Api\V1\ServiceController;
use App\Http\Controllers\Api\V1\UserController;
use App\Http\Middleware\EnsureUserHasAccessToHotelData;
use App\Http\Middleware\EnsureUserHasAccessToPlatformData;
use App\Http\Resources\Api\v1\CurrentUserResource;
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

Route::prefix('v1')->group(function () {
    Route::middleware('auth:sanctum')->group(function () {
        Route::apiResource('permissions', PermissionController::class)->middleware(EnsureUserHasAccessToPlatformData::class);
        Route::apiResource('roles', RoleController::class)->middleware(EnsureUserHasAccessToPlatformData::class);
        Route::apiResource('users', UserController::class)->middleware(EnsureUserHasAccessToPlatformData::class);
        Route::apiResource('hotels', HotelController::class);

        Route::apiResource('amenities', AmenityController::class);
        Route::apiResource('hotels.room-categories', RoomCategoryController::class)->shallow()->middleware(EnsureUserHasAccessToHotelData::class);
        Route::apiResource('room-categories.rooms', RoomController::class)->shallow()->middleware(EnsureUserHasAccessToHotelData::class);
        Route::apiResource('room-categories.media', MediaController::class)->parameters(['media' => 'media'])->only(['index', 'store', 'destroy'])->shallow()->middleware(EnsureUserHasAccessToHotelData::class);

        Route::prefix('room-categories/{room_category}')->group(function () {
            Route::get('amenities', [RoomCategoryAmenityController::class, 'index']);
            Route::put('amenities', [RoomCategoryAmenityController::class, 'update']);
        })->middleware(EnsureUserHasAccessToHotelData::class);
        // Route::apiResource('room-categories.amenities', RoomCategoryAmenityController::class)->only(['index', 'update'])->middleware(EnsureUserHasAccessToHotelData::class);

        Route::apiResource('hotels.rate-plans', RatePlanController::class)->shallow()->middleware(EnsureUserHasAccessToHotelData::class);
        Route::prefix('rate-plans/{rate_plan}')->group(function () {
            Route::get('pricing', [PricingController::class, 'show']);
            // Route::get('prices', [RatePlanPricesController::class, 'index']);
            // Route::put('prices', [RatePlanPricesController::class, 'update']);
            // Route::get('overrides', [RatePlanOverridesController::class, 'index']);
            // Route::put('overrides', [RatePlanOverridesController::class, 'update']);
        })->middleware(EnsureUserHasAccessToHotelData::class);
        // Route::prefix('rate-plans/{rate_plan}')->group(function () {
        //     Route::get('pricing', [PricingController::class, 'show']);
        //     Route::post('pricing', [PricingController::class, 'update']);
        // })->middleware(EnsureUserHasAccessToHotelData::class);

        Route::apiResource('booking-statuses', BookingStatusController::class);
        Route::apiResource('hotels.services', ServiceController::class)->shallow()->middleware(EnsureUserHasAccessToHotelData::class);
        Route::prefix('hotels/{hotel}')->group(function () {
            // Route::get('availability', [BookingAvailabilityController::class, 'index']);
            Route::get('bookings', [BookingController::class, 'index']);
            Route::post('bookings', [BookingController::class, 'store']);
        })->middleware(EnsureUserHasAccessToHotelData::class);
        Route::prefix('bookings/{booking}')->group(function () {
            Route::get('/', [BookingController::class, 'show']);
            Route::delete('/', [BookingController::class, 'destroy']);
            Route::patch('/', [BookingController::class, 'update']);

            // Route::patch('status', [BookingStatusController::class, 'update']); // Изменение статуса
            // Route::patch('room', [BookingRoomController::class, 'update']); // Смена номера
            // Route::put('stay-details', [BookingStayDetailsController::class, 'update']); // Даты/Тарифы/Категории
            // Route::patch('services', [BookingServicesController::class, 'update']); // Массив услуг
            // Route::patch('guests', [BookingGuestsController::class, 'update']); // Массив гостей
        })->middleware(EnsureUserHasAccessToHotelData::class);
        Route::get('hotels/{hotel}/guests', [GuestController::class, 'index'])->middleware(EnsureUserHasAccessToHotelData::class);
        Route::apiResource('guests', GuestController::class)->except(['index', 'store'])->middleware(EnsureUserHasAccessToHotelData::class);


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
            return new CurrentUserResource($request->user()->load('hotels', 'roles.permissions',));
        });
    });
});
