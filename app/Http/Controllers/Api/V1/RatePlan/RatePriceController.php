<?php

namespace App\Http\Controllers\Api\V1\RatePlan;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\RatePlan\UpdateRatePriceRequest;
use App\Models\RatePlan;
use App\Services\Pricing\PricingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class RatePriceController extends Controller
{
    public function __construct(
        protected PricingService $pricingService
    ) {}

    public function index(RatePlan $ratePlan)
    {
        Gate::authorize('view', $ratePlan);

        $prices = $this->pricingService->getBasePrices($ratePlan);

        return response()->json([
            'rate_plan_id' => $ratePlan->id,
            'categories' => $prices,
        ]);
    }

    public function update(UpdateRatePriceRequest $request, RatePlan $ratePlan)
    {
        Gate::authorize('update', $ratePlan);

        $prices = $this->pricingService->updateBasePrices(
            $ratePlan,
            $request->validated()['base_prices']
        );
        return response()->json([
            'rate_plan_id' => $ratePlan->id,
            'categories' => $prices,
        ]);
    }
}
