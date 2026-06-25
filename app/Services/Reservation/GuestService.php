<?php

namespace App\Services\Reservation;

use App\Enums\BookingStatusEnum;
use App\Models\Guest;
use App\Models\Hotel;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class GuestService
{
    public function findOrCreate(Hotel $hotel, array $data): Guest
    {
        $email = $data['email'] ?? null;
        $phone = $data['phone'] ?? null;

        if ($email || $phone) {
            $guest = Guest::where('hotel_id', $hotel->id)
                ->where('first_name', $data['first_name'])
                ->where('last_name', $data['last_name'])
                ->where(function ($query) use ($email, $phone) {
                    if ($email) {
                        $query->where('email', $email);
                    }
                    if ($phone) {
                        $query->orWhere('phone', $phone);
                    }
                })
                ->first();

            if ($guest) {
                return $guest;
            }
        }

        return Guest::create([
            'hotel_id'   => $hotel->id,
            'first_name' => $data['first_name'],
            'last_name'  => $data['last_name'],
            'email'      => $email,
            'phone'      => $phone,
        ]);
    }

    public function update(Guest $guest, array $data): Guest
    {
        $guest->update($data);
        return $guest;
    }

    public function delete(Guest $guest): void
    {
        $hasActiveBookings = $guest->bookings()
            ->whereHas('status', function ($query) {
                $query->whereIn('slug', [
                    BookingStatusEnum::NEW->value,
                    BookingStatusEnum::CONFIRMED->value,
                    BookingStatusEnum::CHECKED_IN->value,
                ]);
            })
            ->exists();

        if ($hasActiveBookings) {
            throw new ConflictHttpException(
                'Cannot delete guest with active bookings.'
            );
        }

        $guest->delete();
    }
}
