<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Service\StoreServiceRequest;
use App\Http\Requests\Api\V1\Service\UpdateServiceRequest;
use App\Http\Resources\Api\V1\ServiceResource;
use App\Models\Hotel;
use App\Models\Service;
use App\Services\Hotel\ServiceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ServiceController extends Controller
{
    public function __construct(
        protected ServiceService $serviceService
    ) {}
    /**
     * Display a listing of the resource.
     */
    public function index(Hotel $hotel)
    {
        Gate::authorize('viewAny', [Service::class, $hotel]);
        $services = $hotel->services()->latest()->get();
        return ServiceResource::collection($services);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreServiceRequest $request, Hotel $hotel)
    {
        Gate::authorize('create', [Service::class, $hotel]);
        $data = $request->validated();
        $service = $this->serviceService->createService($hotel, $data);
        return new ServiceResource($service);
    }

    /**
     * Display the specified resource.
     */
    public function show(Service $service)
    {
        Gate::authorize('view', $service);
        return new ServiceResource($service);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateServiceRequest $request, Service $service)
    {
        Gate::authorize('update', $service);
        $data = $request->validated();
        $service = $this->serviceService->updateService($service, $data);
        return new ServiceResource($service);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Service $service)
    {
        Gate::authorize('delete', $service);
        // if ($service->bookings()
        //     // ->whereNotIn('status_id', [/* ID статусов которые запрещают */])
        //     ->exists()) {
        //     return response([
        //         'message' => 'Cannot delete service that is assigned to bookings.'
        //     ], 409);
        // }
        $service = $this->serviceService->deleteService($service);
        return response()->noContent();
    }
}
