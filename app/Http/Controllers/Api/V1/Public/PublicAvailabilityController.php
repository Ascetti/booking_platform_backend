<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Public\GetAvailabilityRequest;
use App\Models\Hotel;
use App\Services\Reservation\AvailabilityService;
use Carbon\Carbon;

class PublicAvailabilityController extends Controller
{
    public function __construct(
        protected AvailabilityService $availabilityService
    ) {}

    public function index(GetAvailabilityRequest $request, Hotel $hotel)
    {
        $data = $request->validated();

        $categories = $this->availabilityService->getAvailableCategories(
            hotel: $hotel,
            checkIn: Carbon::parse($data['check_in_date']),
            checkOut: Carbon::parse($data['check_out_date']),
            adults: $data['adults_count'],
            children: $data['children_count'],
        );

        return response()->json(['data' => $categories]);
    }
}