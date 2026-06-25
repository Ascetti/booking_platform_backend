<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HotelIntegration extends Model
{
    protected $fillable = [
        'hotel_id',
        'channel_slug',
        'is_active',
        'credentials',
        'settings',
    ];

    protected function casts(): array
    {
        return [
            'is_active'   => 'boolean',
            'credentials' => 'array',
            'settings'    => 'array',
        ];
    }

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(IntegrationLog::class, 'integration_id');
    }
}