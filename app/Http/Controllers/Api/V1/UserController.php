<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Http\Requests\Api\V1\User\StoreUserRequest;
use App\Http\Requests\Api\V1\User\UpdateUserRequest;
use App\Http\Resources\Api\V1\UserResource;
use App\Services\IAM\UserService;
use Illuminate\Support\Facades\Gate;

class UserController extends Controller
{
    public function __construct(
        protected UserService $userService
    ) {}

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        Gate::authorize('viewAny', User::class);
        $users = User::with('roles')->get();
        return UserResource::collection($users);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreUserRequest $request)
    {
        Gate::authorize('create', User::class);
        $data = $request->validated();
        $user = $this->userService->storeUser($data);
        return new UserResource($user->load('roles'));
    }

    /**
     * Display the specified resource.
     */
    public function show(User $user)
    {
        Gate::authorize('view', $user);
        return new UserResource($user->load('roles'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateUserRequest $request, User $user)
    {
        Gate::authorize('update', $user);
        $data = $request->validated();
        $user = $this->userService->updateUser($user, $data);
        return new UserResource($user->load('roles'));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(User $user)
    {
        Gate::authorize('delete', $user);
        $user = $this->userService->deleteUser($user);
        return response()->noContent();
    }
}
