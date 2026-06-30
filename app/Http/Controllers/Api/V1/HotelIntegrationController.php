<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Integration\ConnectBitrix24Request;
use App\Http\Resources\Api\V1\HotelIntegrationResource;
use App\Models\Hotel;
use App\Models\HotelIntegration;
use App\Services\Integration\IntegrationService;
use Illuminate\Support\Facades\Gate;

class HotelIntegrationController extends Controller
{
    public function __construct(
        protected IntegrationService $integrationService
    ) {}

    public function index(Hotel $hotel)
    {
        Gate::authorize('update', $hotel);

        $integrations = HotelIntegration::where('hotel_id', $hotel->id)->get();

        return HotelIntegrationResource::collection($integrations);
    }

    /**
     * Подключить интеграцию с Битрикс24.
     * Сохраняет вебхук и сразу настраивает воронку/стадии/поля.
     */
    public function connectBitrix24(ConnectBitrix24Request $request, Hotel $hotel)
    {
        Gate::authorize('update', $hotel);

        $integration = HotelIntegration::updateOrCreate(
            [
                'hotel_id'     => $hotel->id,
                'channel_slug' => 'bitrix24',
            ],
            [
                'is_active'   => true,
                'credentials' => ['webhook_url' => $request->validated()['webhook_url']],
            ]
        );

        $settings = $this->integrationService->setupIntegration($integration);

        $message = $settings['pipeline_id']
            ? 'Integration connected and configured successfully.'
            : 'Integration connected. Custom pipeline is not configured using default pipeline and stages.';

        return response()->json([
            'message' => $message,
            'data'    => new HotelIntegrationResource($integration->fresh()),
        ]);
    }

    public function destroy(Hotel $hotel, HotelIntegration $integration)
    {
        Gate::authorize('update', $hotel);

        if ($integration->hotel_id !== $hotel->id) {
            abort(404);
        }

        $integration->delete();

        return response()->noContent();
    }
}
