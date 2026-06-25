<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HotelIntegrationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'channel_slug' => $this->channel_slug,
            'is_active'    => $this->is_active,
            // credentials не отдаём наружу — там секретный URL вебхука
            'is_configured' => !empty($this->settings['pipeline_id'] ?? null),
            'pipeline_id'  => $this->settings['pipeline_id'] ?? null,
        ];
    }
}
