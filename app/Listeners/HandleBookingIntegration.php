<?php

namespace App\Listeners;

use App\Events\BookingCreated;
use App\Events\BookingDetailsChanged;
use App\Events\BookingStatusChanged;
use App\Mail\BookingConfirmation;
use App\Services\Integration\IntegrationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class HandleBookingIntegration implements ShouldQueue
{
    use InteractsWithQueue;

    public function __construct(
        protected IntegrationService $integrationService
    ) {}

    public function handleCreated(BookingCreated $event): void
    {
        $this->integrationService->handleBookingCreated($event->booking);

        $booking = $event->booking;
        $primaryGuest = $booking->getPrimaryGuest();

        \Illuminate\Support\Facades\Log::info('Trying to send email', [
            'guest_email' => $primaryGuest?->email,
            'booking_id'  => $booking->id,
        ]);
        if ($primaryGuest?->email) {
            Mail::to($primaryGuest->email)
                ->send(new BookingConfirmation($booking));
        }
    }

    public function handleStatusChanged(BookingStatusChanged $event): void
    {
        $this->integrationService->syncDeal($event->booking);
    }

    public function handleDetailsChanged(BookingDetailsChanged $event): void
    {
        $this->integrationService->syncDeal($event->booking);
    }
}
