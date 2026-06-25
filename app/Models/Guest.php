<?php

namespace App\Models;

use App\Enums\DocumentTypeEnum;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Guest extends Model
{
    /** @use HasFactory<\Database\Factories\GuestFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = ['hotel_id', 'first_name', 'last_name', 'middle_name', 'birth_date', 'document_type', 
        'document_number', 'email', 'phone'
    ];

    protected function casts()  {
        return [
            'birth_date' => 'date',
            'document_type' => DocumentTypeEnum::class
        ];
    }

    protected function phone(): Attribute
    {
        return Attribute::make(
            set: function (?string $value) {
                if (!$value) {
                    return null;
                }
                $digits = preg_replace('/\D/', '', $value);
                if (strlen($digits) === 11) {
                    $digits = substr($digits, 1);
                }
                return sprintf(
                    '+7 (%s) %s %s-%s',
                    substr($digits, 0, 3),
                    substr($digits, 3, 3),
                    substr($digits, 6, 2),
                    substr($digits, 8, 2),
                );
            },
        );
    }

    public function bookings(): BelongsToMany
    {
        return $this->belongsToMany(Booking::class, 'booking_guest')
            ->withPivot([
                'is_primary', 'first_name', 'last_name', 'email', 'phone'
            ]);
    }

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }
}
