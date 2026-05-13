<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Role\StoreRoleRequest;
use App\Http\Requests\Api\V1\Role\UpdateRoleRequest;
use App\Http\Resources\Api\V1\RoleResource;
use App\Models\Role;
use App\Services\IAM\RoleService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use phpDocumentor\Reflection\Types\This;

class RoleController extends Controller
{
    public function __construct(
        protected RoleService $roleService
    ) {}

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        Gate::authorize('viewAny');
        $roles = Role::with('permissions')->get();
        return RoleResource::collection($roles);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreRoleRequest $request)
    {
        Gate::authorize('create');
        $data = $request->validated();
        $role = $this->roleService->storeRole($data);
        return new RoleResource($role->load('permissions'));
    }

    /**
     * Display the specified resource.
     */
    public function show(Role $role)
    {
        Gate::authorize('view', $role);
        return new RoleResource($role->load('permissions'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateRoleRequest $request, Role $role)
    {
        Gate::authorize('update', $role);
        $data = $request->validated();
        $role = $this->roleService->updateRole($role, $data);
        return new RoleResource($role->load('permissions'));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Role $role)
    {
        Gate::authorize('delete', $role);
        $this->roleService->deleteRole($role);
        return response()->noContent();
    }
}
