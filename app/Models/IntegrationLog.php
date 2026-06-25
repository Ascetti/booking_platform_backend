<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IntegrationLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'integration_id',
        'direction',
        'payload',
        'response',
        'http_status',
    ];

    protected function casts(): array
    {
        return [
            'payload'    => 'array',
            'response'   => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function integration(): BelongsTo
    {
        return $this->belongsTo(HotelIntegration::class, 'integration_id');
    }
}