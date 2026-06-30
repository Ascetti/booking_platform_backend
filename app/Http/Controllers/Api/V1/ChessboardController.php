<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\GetChessboardRequest;
use App\Models\Booking;
use App\Models\Hotel;
use App\Services\Reservation\ChessboardService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Gate;

class ChessboardController extends Controller
{
    public function __construct(
        protected ChessboardService $chessboardService
    ) {}

    public function index(GetChessboardRequest $request, Hotel $hotel)
    {
        Gate::authorize('viewAny', [Booking::class, $hotel]);

        $data = $request->validated();

        $chessboard = $this->chessboardService->getChessboard(
            hotel: $hotel,
            dateFrom: Carbon::parse($data['date_from']),
            dateTo: Carbon::parse($data['date_to']),
            search: $data['search'] ?? null,
            statuses: $data['statuses'] ?? [],
        );

        return response()->json([
            'date_from'  => $data['date_from'],
            'date_to'    => $data['date_to'],
            'categories' => $chessboard,
        ]);
    }
}
