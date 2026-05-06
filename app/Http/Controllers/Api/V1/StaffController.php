<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\UserResource;
use App\Models\Hotel;
use App\Models\User;
use App\Services\IAM\StaffService;
use Illuminate\Http\Request;

class StaffController extends Controller
{
    public function __construct(
        protected StaffService $staffService
    ) {}

    public function index(Hotel $hotel)
    {
        return UserResource::collection($hotel->users()->with('roles')->get());
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'role_id' => ['required', 'integer', 'exists:roles,id'],
            'hotel_id' => ['nullable', 'integer', 'exists:hotels,id'],
        ]);

        $this->staffService->assignRole($data['user_id'], $data['role_id'], $data['hotel_id']);

        return response()->json(['message' => 'Role was assigned']);
    }

    public function sync(Request $request, Hotel $hotel)
    {
        $data = $request->validate([
            'staff' => ['required', 'array'],
            'staff.*.user_id' => ['required', 'integer', 'exists:users,id'],
            'staff.*.role_id' => ['required', 'integer', 'exists:roles,id'],
        ]);

        $this->staffService->syncHotelStaff($hotel->id, $data['staff']);

        return response()->json(['message' => 'Hotel staff was updated']);
    }

    public function destroy(Hotel $hotel, User $user, Request $request)
    {
        $roleId = $user->roles()
            ->wherePivot('hotel_id', $hotel->id)
            ->first()?->id;

        if (!$roleId) {
            return response()->json(['message' => 'Role was not found'], 404);
        }

        $this->staffService->removeRole($user->id, $roleId, $hotel->id);

        return response()->noContent();
    }
}
