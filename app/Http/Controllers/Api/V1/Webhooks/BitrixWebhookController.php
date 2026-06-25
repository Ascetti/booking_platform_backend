<?php

namespace App\Http\Controllers\Api\V1\Webhooks;

use App\Http\Controllers\Controller;
use App\Services\Integration\IntegrationService;
use Illuminate\Http\Request;

class BitrixWebhookController extends Controller
{
    public function __construct(
        protected IntegrationService $integrationService
    ) {}

    public function handle(Request $request)
    {
        $payload = $request->all();

        $this->integrationService->handleIncomingWebhook($payload);

        // Битрикс24 ожидает 200 в ответ
        return response()->json(['status' => 'ok']);
    }
}