<?php

namespace App\Http\Controllers\Api\V1\RatePlan;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\RatePlan\DeleteRateOverrideRequest;
use App\Http\Requests\Api\V1\RatePlan\GetRateOverrideRequest;
use App\Http\Requests\Api\V1\RatePlan\UpdateRateOverrideRequest;
use App\Models\RateOverride;
use App\Models\RatePlan;
use App\Services\Pricing\PricingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class RateOverrideController extends Controller
{
    public function __construct(
        protected PricingService $pricingService
    ) {}

    public function update(UpdateRateOverrideRequest $request, RatePlan $ratePlan)
    {
        Gate::authorize('update', $ratePlan);
        $this->pricingService->updateOverrides($ratePlan, $request->validated());
        return response()->json(['message' => 'Overrides updated successfully.']);
    }

    public function destroy(DeleteRateOverrideRequest $request, RatePlan $ratePlan)
    {
        Gate::authorize('update', $ratePlan);
        $this->pricingService->deleteOverrides($ratePlan, $request->validated());
        return response()->noContent();
    }
}
