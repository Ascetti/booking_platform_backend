<?php

use App\Http\Controllers\Auth\AuthenticatedApiController;
use App\Http\Controllers\Auth\RegisteredUserApiController;
use App\Http\Controllers\Api\V1\AmenityController;
use App\Http\Controllers\Api\V1\AvailabilityController;
use App\Http\Controllers\Api\V1\Booking\BookingAvailableRoomsController;
use App\Http\Controllers\Api\V1\Booking\BookingController;
use App\Http\Controllers\Api\V1\Booking\BookingGuestsController;
use App\Http\Controllers\Api\V1\Booking\BookingRoomController;
use App\Http\Controllers\Api\V1\Booking\BookingServicesController;
use App\Http\Controllers\Api\V1\Booking\BookingStayDetailsController;
use App\Http\Controllers\Api\V1\Booking\StatusController;
use App\Http\Controllers\Api\V1\BookingStatusController;
use App\Http\Controllers\Api\V1\ChessboardController;
use App\Http\Controllers\Api\V1\GuestController;
use App\Http\Controllers\Api\V1\HotelController;
use App\Http\Controllers\Api\V1\HotelIntegrationController;
use App\Http\Controllers\Api\V1\MediaController;
use App\Http\Controllers\Api\V1\PermissionController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\Public\PublicAvailabilityController;
use App\Http\Controllers\Api\V1\Public\PublicBookingController;
use App\Http\Controllers\Api\V1\Public\PublicHotelController;
use App\Http\Controllers\Api\V1\Public\PublicPriceController;
use App\Http\Controllers\Api\V1\Public\PublicServiceController;
use App\Http\Controllers\Api\V1\RatePlan\RateGridController;
use App\Http\Controllers\Api\V1\RatePlan\RateOverrideController;
use App\Http\Controllers\Api\V1\RatePlan\RatePlanController;
use App\Http\Controllers\Api\V1\RatePlan\RatePriceController;
use App\Http\Controllers\Api\V1\RoleController;
use App\Http\Controllers\Api\V1\RoomCategoryAmenityController;
use App\Http\Controllers\Api\V1\RoomCategoryController;
use App\Http\Controllers\Api\V1\RoomController;
use App\Http\Controllers\Api\V1\ServiceController;
use App\Http\Controllers\Api\V1\UserController;
use App\Http\Controllers\Api\V1\Webhooks\BitrixWebhookController;
use App\Http\Middleware\EnsureUserHasAccessToHotelData;
use App\Http\Middleware\EnsureUserHasAccessToPlatformData;
use App\Http\Resources\Api\v1\CurrentUserResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Route::post('/login', [AuthenticatedApiController::class, 'store'])
//     ->middleware('guest')
//     ->name('login');

// Route::post('/register', [RegisteredUserApiController::class, 'store'])
//     ->middleware('guest')
//     ->name('register');

// Route::post('/logout', [AuthenticatedApiController::class, 'destroy'])
//     ->middleware('auth:sanctum')
//     ->name('logout');

Route::post('v1/webhooks/bitrix24', [BitrixWebhookController::class, 'handle'])->name('webhooks.bitrix24');

Route::post('/login', [AuthenticatedApiController::class, 'store'])->middleware('guest')->name('login');
Route::post('/logout', [AuthenticatedApiController::class, 'destroy'])->middleware('auth:sanctum')->name('logout');

Route::prefix('v1/public')->name('public.')->middleware('throttle:60,1')->group(function () {
    Route::get('hotels/{hotel}', [PublicHotelController::class, 'show'])
        ->name('public.hotels.show');
    Route::get('hotels/{hotel}/prices', [PublicPriceController::class, 'index'])
        ->name('public.hotels.prices');
    Route::get('hotels/{hotel}/availability', [PublicAvailabilityController::class, 'index'])
        ->name('public.hotels.availability');
    Route::get('hotels/{hotel}/services', [PublicServiceController::class, 'index'])
        ->name('public.hotels.services');
    Route::post('hotels/{hotel}/bookings', [PublicBookingController::class, 'store'])
        ->name('public.hotels.bookings.store');
});

Route::prefix('v1')->middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        return new CurrentUserResource($request->user()->load('hotels', 'roles.permissions',));
    });
    Route::get('profile', [ProfileController::class, 'show'])->name('profile.show');
    Route::patch('profile', [ProfileController::class, 'update'])->name('profile.update');

    Route::middleware(EnsureUserHasAccessToPlatformData::class)->group(function () {
        Route::apiResource('users', UserController::class);
        Route::apiResource('roles', RoleController::class);
        Route::apiResource('permissions', PermissionController::class);

        Route::post('hotels', [HotelController::class, 'store'])->name('hotels.store');
        Route::delete('hotels/{hotel}', [HotelController::class, 'destroy'])->name('hotels.destroy');

        Route::post('amenities', [AmenityController::class, 'store'])->name('amenities.store');
        Route::patch('amenities/{amenity}', [AmenityController::class, 'update'])->name('amenities.update');
        Route::delete('amenities/{amenity}', [AmenityController::class, 'destroy'])->name('amenities.destroy');

        Route::post('booking-statuses', [BookingStatusController::class, 'store'])->name('booking-statuses.store');
        Route::patch('booking-statuses/{booking_status}', [BookingStatusController::class, 'update'])->name('booking-statuses.update');
        Route::delete('booking-statuses/{booking_status}', [BookingStatusController::class, 'destroy'])->name('booking-statuses.destroy');

        // Route::get('hotels/{hotel}/staff', [StaffController::class, 'index'])->name('hotels.staff.index');
        // Route::post('hotels/{hotel}/staff', [StaffController::class, 'store'])->name('hotels.staff.store');
        // Route::delete('hotels/{hotel}/staff/{user}', [StaffController::class, 'destroy'])->name('hotels.staff.destroy');
    });

    Route::get('hotels', [HotelController::class, 'index'])->name('hotels.index');
    Route::get('hotels/{hotel}', [HotelController::class, 'show'])->name('hotels.show');
    Route::patch('hotels/{hotel}', [HotelController::class, 'update'])->name('hotels.update');
    Route::get('amenities', [AmenityController::class, 'index'])->name('amenities.index');
    Route::get('amenities/{amenity}', [AmenityController::class, 'show'])->name('amenities.show');
    Route::get('booking-statuses', [BookingStatusController::class, 'index'])->name('booking-statuses.index');
    Route::get('booking-statuses/{booking_status}', [BookingStatusController::class, 'show'])->name('booking-statuses.show');

    Route::middleware(EnsureUserHasAccessToHotelData::class)->group(function () {
        Route::apiResource('hotels.room-categories', RoomCategoryController::class)->shallow();
        Route::apiResource('room-categories.rooms', RoomController::class)->shallow();
        Route::apiResource('room-categories.media', MediaController::class)->parameters(['media' => 'media'])->only(['index', 'store', 'destroy'])->shallow();
        Route::get('room-categories/{room_category}/amenities', [RoomCategoryAmenityController::class, 'index'])->name('room-categories.amenities.index');
        Route::put('room-categories/{room_category}/amenities', [RoomCategoryAmenityController::class, 'update'])->name('room-categories.amenities.update');

        Route::apiResource('hotels.rate-plans', RatePlanController::class)->shallow();
        Route::get('rate-plans/{rate_plan}/prices', [RatePriceController::class, 'index'])->name('rate-plans.prices.index');
        Route::put('rate-plans/{rate_plan}/prices', [RatePriceController::class, 'update'])->name('rate-plans.prices.update');
        Route::put('rate-plans/{rate_plan}/overrides', [RateOverrideController::class, 'update'])->name('rate-plans.overrides.update');
        Route::delete('rate-plans/{rate_plan}/overrides', [RateOverrideController::class, 'destroy'])->name('rate-plans.overrides.destroy');
        Route::get('rate-plans/{rate_plan}/grid', [RateGridController::class, 'index'])->name('hotels.rate-grid');

        Route::apiResource('hotels.services', ServiceController::class)->shallow();

        Route::get('hotels/{hotel}/bookings', [BookingController::class, 'index'])->name('hotels.bookings.index');
        Route::post('hotels/{hotel}/bookings', [BookingController::class, 'store'])->name('hotels.bookings.store');
        Route::get('bookings/{booking}', [BookingController::class, 'show'])->name('bookings.show');
        Route::delete('bookings/{booking}', [BookingController::class, 'destroy'])->name('bookings.destroy');

        Route::patch('bookings/{booking}/status', [StatusController::class, 'update'])->name('bookings.status.update');
        Route::patch('bookings/{booking}/room', [BookingRoomController::class, 'update'])->name('bookings.room.update');
        Route::put('bookings/{booking}/stay-details', [BookingStayDetailsController::class, 'update'])->name('bookings.stay-details.update');
        Route::put('bookings/{booking}/guests', [BookingGuestsController::class, 'update'])->name('bookings.guests.update');
        Route::put('bookings/{booking}/services', [BookingServicesController::class, 'update'])->name('bookings.services.update');
        Route::get('bookings/{booking}/available-rooms', [BookingAvailableRoomsController::class, 'index'])->name('bookings.available-rooms');

        Route::get('hotels/{hotel}/guests', [GuestController::class, 'index'])->name('hotels.guests.index');
        Route::get('guests/{guest}', [GuestController::class, 'show'])->name('guests.show');
        Route::patch('guests/{guest}', [GuestController::class, 'update'])->name('guests.update');
        Route::delete('guests/{guest}', [GuestController::class, 'destroy'])->name('guests.destroy');

        Route::get('hotels/{hotel}/availability', [AvailabilityController::class, 'index'])->name('hotels.availability');
        Route::get('hotels/{hotel}/chessboard', [ChessboardController::class, 'index'])->name('hotels.chessboard');

        Route::prefix('hotels/{hotel}/integrations')->group(function () {
            Route::get('/', [HotelIntegrationController::class, 'index']);
            Route::post('bitrix24', [HotelIntegrationController::class, 'connectBitrix24']);
            Route::delete('{integration}', [HotelIntegrationController::class, 'destroy']);
        });
    });
});

// Route::prefix('v1')->group(function () {
//     Route::middleware('auth:sanctum')->group(function () {
//         Route::apiResource('permissions', PermissionController::class)->middleware(EnsureUserHasAccessToPlatformData::class);
//         Route::apiResource('roles', RoleController::class)->middleware(EnsureUserHasAccessToPlatformData::class);
//         Route::apiResource('users', UserController::class)->middleware(EnsureUserHasAccessToPlatformData::class);
//         Route::apiResource('hotels', HotelController::class);

//         Route::apiResource('amenities', AmenityController::class);
//         Route::apiResource('hotels.room-categories', RoomCategoryController::class)->shallow()->middleware(EnsureUserHasAccessToHotelData::class);
//         Route::apiResource('room-categories.rooms', RoomController::class)->shallow()->middleware(EnsureUserHasAccessToHotelData::class);
//         Route::apiResource('room-categories.media', MediaController::class)->parameters(['media' => 'media'])->only(['index', 'store', 'destroy'])->shallow()->middleware(EnsureUserHasAccessToHotelData::class);

//         Route::prefix('room-categories/{room_category}')->group(function () {
//             Route::get('amenities', [RoomCategoryAmenityController::class, 'index']);
//             Route::put('amenities', [RoomCategoryAmenityController::class, 'update']);
//         })->middleware(EnsureUserHasAccessToHotelData::class);
//         // Route::apiResource('room-categories.amenities', RoomCategoryAmenityController::class)->only(['index', 'update'])->middleware(EnsureUserHasAccessToHotelData::class);

//         Route::apiResource('hotels.rate-plans', RatePlanController::class)->shallow()->middleware(EnsureUserHasAccessToHotelData::class);
//         Route::prefix('rate-plans/{rate_plan}')->group(function () {
//             Route::get('pricing', [PricingController::class, 'show']);
//             // Route::get('prices', [RatePlanPricesController::class, 'index']);
//             // Route::put('prices', [RatePlanPricesController::class, 'update']);
//             // Route::get('overrides', [RatePlanOverridesController::class, 'index']);
//             // Route::put('overrides', [RatePlanOverridesController::class, 'update']);
//         })->middleware(EnsureUserHasAccessToHotelData::class);
//         // Route::prefix('rate-plans/{rate_plan}')->group(function () {
//         //     Route::get('pricing', [PricingController::class, 'show']);
//         //     Route::post('pricing', [PricingController::class, 'update']);
//         // })->middleware(EnsureUserHasAccessToHotelData::class);

//         Route::apiResource('booking-statuses', BookingStatusController::class);
//         Route::apiResource('hotels.services', ServiceController::class)->shallow()->middleware(EnsureUserHasAccessToHotelData::class);
//         Route::prefix('hotels/{hotel}')->group(function () {
//             // Route::get('availability', [BookingAvailabilityController::class, 'index']);
//             Route::get('bookings', [BookingController::class, 'index']);
//             Route::post('bookings', [BookingController::class, 'store']);
//         })->middleware(EnsureUserHasAccessToHotelData::class);
//         Route::prefix('bookings/{booking}')->group(function () {
//             Route::get('/', [BookingController::class, 'show']);
//             Route::delete('/', [BookingController::class, 'destroy']);
//             Route::patch('/', [BookingController::class, 'update']);

//             // Route::patch('status', [BookingStatusController::class, 'update']); // Изменение статуса
//             // Route::patch('room', [BookingRoomController::class, 'update']); // Смена номера
//             // Route::put('stay-details', [BookingStayDetailsController::class, 'update']); // Даты/Тарифы/Категории
//             // Route::patch('services', [BookingServicesController::class, 'update']); // Массив услуг
//             // Route::patch('guests', [BookingGuestsController::class, 'update']); // Массив гостей
//         })->middleware(EnsureUserHasAccessToHotelData::class);
//         Route::get('hotels/{hotel}/guests', [GuestController::class, 'index'])->middleware(EnsureUserHasAccessToHotelData::class);
//         Route::apiResource('guests', GuestController::class)->except(['index', 'store'])->middleware(EnsureUserHasAccessToHotelData::class);


//         // Route::apiResource('booking-statuses', BookingStatusController::class);
//         // Route::apiResource('hotels.services', ServiceController::class)->shallow()->scoped();
//         // Route::apiResource('hotels.bookings', BookingController::class)->shallow()->scoped();    
//         // Route::get('hotels/{hotel}/guests', [GuestController::class, 'index'])->name('hotels.guests.index');
//         // Route::apiResource('guests', GuestController::class)->except(['index']);

//         // Route::post('staff', [StaffController::class, 'store']);
//         // Route::prefix('hotels/{hotel}')->group(function () {
//         //     Route::get('staff', [StaffController::class, 'index']);
//         //     Route::put('staff/sync', [StaffController::class, 'sync']);
//         //     Route::delete('staff/{user}', [StaffController::class, 'destroy']);
//         // });


//         Route::get('profile', [ProfileController::class, 'show']);
//         Route::patch('profile', [ProfileController::class, 'update']);
//         Route::get('/user', function (Request $request) {
//             return new CurrentUserResource($request->user()->load('hotels', 'roles.permissions',));
//         });
//     });
// });