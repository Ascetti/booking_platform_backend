<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Pricing\UpdatePricingRequest;
use App\Http\Resources\Api\V1\RatePricingResource;
use App\Models\RatePlan;
use App\Services\Hotel\PricingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class PricingController extends Controller
{
    public function __construct(
        protected PricingService $pricingService
    ) {}

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request, RatePlan $ratePlan)
    {
        Gate::authorize('view', $ratePlan);
        $data = $request->validate([
            'start_date' => ['required', 'date', 'date_format:Y-m-d'],
            'end_date'   => ['required', 'date', 'date_format:Y-m-d', 'after_or_equal:start_date'],
        ]);
        $pricingDrid = $this->pricingService->getPricingGrid(
            $ratePlan,
            $data['start_date'],
            $data['end_date']
        );
        return RatePricingResource::collection($pricingDrid);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdatePricingRequest $request, RatePlan $ratePlan)
    {
        Gate::authorize('update', $ratePlan);
        $data = $request->validated();
        $this->pricingService->updatePricing($ratePlan, $data);
        return response()->json(['message' => 'Pricing updated successfully']);
    }
}
