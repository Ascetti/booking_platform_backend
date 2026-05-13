<?php

namespace App\Models;

use App\Enums\DocumentTypeEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Guest extends Model
{
    /** @use HasFactory<\Database\Factories\GuestFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = ['first_name', 'last_name', 'middle_name', 'birth_date', 'document_type', 
        'document_number', 'email', 'phone'
    ];

    protected function casts()  {
        return [
            'birth_date' => 'date',
            'document_type' => DocumentTypeEnum::class
        ];
    }

    public function bookings(): BelongsToMany
    {
        return $this->belongsToMany(Booking::class, 'booking_guest')
            ->withPivot([
                'is_primary', 'first_name', 'last_name', 'email', 'phone'
            ])
            ->withTimestamps();
    }
}
