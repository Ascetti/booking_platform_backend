<?php

namespace App\Services\Hotel;

use App\Models\Hotel;
use App\Models\Service;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class ServiceService
{
    public function createService(Hotel $hotel, array $data): Service
    {
        return $hotel->services()->create($data);
    }

    public function updateService(Service $service, array $data): Service
    {
        $service->update($data);
        return $service;
    }

    public function deleteService(Service $service): bool
    {
        if ($service->bookings()->exists()) {
            throw new ConflictHttpException('Cannot delete service that is assigned to bookings.');
        };
        return $service->delete();
    }
}
