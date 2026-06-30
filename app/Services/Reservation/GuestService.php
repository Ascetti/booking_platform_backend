<?php

namespace App\Services\Reservation;

use App\Enums\BookingStatusEnum;
use App\Models\Guest;
use App\Models\Hotel;
use Illuminate\Support\Facades\DB;
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

    public function updateOrCreate(Hotel $hotel, array $data): Guest
    {
        // Если передан id — обновляем существующего
        if (!empty($data['id'])) {
            $guest = Guest::where('id', $data['id'])
                ->where('hotel_id', $hotel->id)
                ->firstOrFail();

            $guest->update([
                'first_name'      => $data['first_name'],
                'last_name'       => $data['last_name'],
                'middle_name'     => $data['middle_name'] ?? $guest->middle_name,
                'birth_date'      => $data['birth_date'] ?? $guest->birth_date,
                'document_type'   => $data['document_type'] ?? $guest->document_type,
                'document_number' => $data['document_number'] ?? $guest->document_number,
                'email'           => $data['email'] ?? $guest->email,
                'phone'           => $data['phone'] ?? $guest->phone,
            ]);

            return $guest;
        }

        // Иначе создаём нового
        return Guest::create([
            'hotel_id'        => $hotel->id,
            'first_name'      => $data['first_name'],
            'last_name'       => $data['last_name'],
            'middle_name'     => $data['middle_name'] ?? null,
            'birth_date'      => $data['birth_date'] ?? null,
            'document_type'   => $data['document_type'] ?? null,
            'document_number' => $data['document_number'] ?? null,
            'email'           => $data['email'] ?? null,
            'phone'           => $data['phone'] ?? null,
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
