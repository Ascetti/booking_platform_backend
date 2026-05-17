<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\User\UpdateUserRequest;
use App\Http\Resources\Api\V1\UserResource;
use App\Services\IAM\UserService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ProfileController extends Controller
{
    public function __construct(
        protected UserService $userService
    ) {}

    public function show(Request $request)
    {
        Gate::authorize('view', $request->user());
        return new UserResource($request->user());
    }

    public function update(UpdateUserRequest $request)
    {
        Gate::authorize('update', $request->user());
        $data = $request->validated();
        $user = $this->userService->updateUser($request->user(), $data);
        return new UserResource($user);
    }
}
