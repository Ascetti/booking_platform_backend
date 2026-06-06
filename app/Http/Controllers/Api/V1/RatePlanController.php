<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\RatePlan\StoreRatePlanRequest;
use App\Http\Requests\Api\V1\RatePlan\UpdateRatePlanRequest;
use App\Http\Resources\Api\V1\RatePlanResource;
use App\Models\Hotel;
use App\Models\RatePlan;
use App\Services\Hotel\RatePlanService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class RatePlanController extends Controller
{
    public function __construct(
        protected RatePlanService $ratePlanService
    ) {}

    /**
     * Display a listing of the resource.
     */
    public function index(Hotel $hotel)
    {
        Gate::authorize('viewAny', [RatePlan::class, $hotel]);
        $ratePlans = $hotel->plans()->with(['parent'])->get();
        return RatePlanResource::collection($ratePlans);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreRatePlanRequest $request, Hotel $hotel)
    {
        Gate::authorize('create', [RatePlan::class, $hotel]);
        $data = $request->validated();
        $ratePlan = $this->ratePlanService->createRatePlan($hotel, $data);
        return new RatePlanResource($ratePlan);
    }

    /**
     * Display the specified resource.
     */
    public function show(RatePlan $ratePlan)
    {
        Gate::authorize('view', $ratePlan);
        return new RatePlanResource($ratePlan->load(['parent', 'children']));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateRatePlanRequest $request, RatePlan $ratePlan)
    {
        Gate::authorize('update', $ratePlan);
        $data = $request->validated();
        $ratePlan = $this->ratePlanService->updateRatePlan($ratePlan, $data);
        return new RatePlanResource($ratePlan);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(RatePlan $ratePlan)
    {
        Gate::authorize('delete', $ratePlan);
        $this->ratePlanService->deleteRatePlan($ratePlan);
        return response()->noContent();
    }
}
