<?php

namespace App\Providers;

use App\Events\BookingCreated;
use App\Events\BookingStatusChanged;
use App\Listeners\HandleBookingIntegration;
use App\Models\Hotel;
use App\Models\RoomCategory;
use App\Models\User;
use App\Policies\HotelPolicy;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        ResetPassword::createUrlUsing(function (object $notifiable, string $token) {
            return config('app.frontend_url') . "/password-reset/$token?email={$notifiable->getEmailForPasswordReset()}";
        });

        // Event::listen(
        //     BookingCreated::class,
        //     [HandleBookingIntegration::class, 'handleCreated']
        // );

        // Event::listen(
        //     BookingStatusChanged::class,
        //     [HandleBookingIntegration::class, 'handleStatusChanged']
        // );
    }
}
