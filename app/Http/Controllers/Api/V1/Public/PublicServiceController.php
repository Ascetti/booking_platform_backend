<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ServiceResource;
use App\Models\Hotel;

class PublicServiceController extends Controller
{
    public function index(Hotel $hotel)
    {
        $services = $hotel->services()->get();
        return ServiceResource::collection($services);
    }
}