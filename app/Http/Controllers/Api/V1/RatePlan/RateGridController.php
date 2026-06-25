<?php

namespace App\Http\Controllers\Api\V1\RatePlan;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\RatePlan\GetRateGridRequest;
use App\Models\RatePlan;
use App\Services\Pricing\PricingService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class RateGridController extends Controller
{
    public function __construct(
        protected PricingService $pricingService
    ) {}

    public function index(GetRateGridRequest $request, RatePlan $ratePlan)
    {
        Gate::authorize('view', $ratePlan);

        $data = $request->validated();

        $grid = $this->pricingService->getRateGrid(
            ratePlan: $ratePlan,
            dateFrom: Carbon::parse($data['date_from']),
            dateTo: Carbon::parse($data['date_to']),
        );

        return response()->json([
            'rate_plan_id'    => $ratePlan->id,
            'is_child'        => $ratePlan->parent_id !== null,
            'modifier_percent' => $ratePlan->modifier_percent,
            'date_from'       => $data['date_from'],
            'date_to'         => $data['date_to'],
            'categories'      => $grid,
        ]);
    }
}
