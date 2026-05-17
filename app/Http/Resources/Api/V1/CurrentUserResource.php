<?php

namespace App\Http\Resources\Api\v1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CurrentUserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $platformRoles = $this->roles->where('pivot.hotel_id', null);
        $platformPermissions = $platformRoles
            ->flatMap(fn ($role) => $role->permissions)
            ->pluck('slug')
            ->unique()
            ->values();

        $hotelAccess = $this->hotels->map(function ($hotel) {
            $hotelRoles = $this->roles->where('pivot.hotel_id', $hotel->id);
            $hotelPermissions = $hotelRoles
                ->flatMap(fn ($role) => $role->permissions)
                ->pluck('slug')
                ->unique()
                ->values();
            return [
                'hotel' => [
                    'id' => $hotel->id,
                    'name' => $hotel->name,
                ],
                'roles' => $hotelRoles->map(fn ($role) => [
                    'id' => $role->id,
                    'slug' => $role->slug,
                    'name' => $role->name,
                ])->values(),
                'permissions' => $hotelPermissions,
            ];
        });

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'platform_access' => [
                'roles' => $platformRoles->map(fn ($role) => [
                    'id' => $role->id,
                    'slug' => $role->slug,
                    'name' => $role->name,
                ])->values(),
                'permissions' => $platformPermissions,
            ],
            'hotel_access' => $hotelAccess,
        ];
    }
}
