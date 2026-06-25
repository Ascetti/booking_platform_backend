<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Public\GetCalendarPricesRequest;
use App\Models\Hotel;
use App\Services\Pricing\PricingService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class PublicPriceController extends Controller
{
    public function __construct(
        protected PricingService $pricingService
    ) {}

    public function index(GetCalendarPricesRequest $request, Hotel $hotel)
    {
        $data = $request->validated();

        $prices = $this->pricingService->getCalendarPrices(
            hotel: $hotel,
            dateFrom: Carbon::parse($data['date_from']),
            dateTo: Carbon::parse($data['date_to']),
        );

        return response()->json(['data' => $prices]);
    }
}
