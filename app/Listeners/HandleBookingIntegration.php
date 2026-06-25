<?php

namespace App\Listeners;

use App\Events\BookingCreated;
use App\Events\BookingDetailsChanged;
use App\Events\BookingStatusChanged;
use App\Services\Integration\IntegrationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class HandleBookingIntegration implements ShouldQueue
{
    use InteractsWithQueue;

    public function __construct(
        protected IntegrationService $integrationService
    ) {}

    public function handleCreated(BookingCreated $event): void
    {
        $this->integrationService->handleBookingCreated($event->booking);
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
