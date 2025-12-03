<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;
use App\Models\Role;
use Illuminate\Http\Request;
use App\Traits\ApiResponse;
use Illuminate\Support\Facades\Auth;

class RoleController extends Controller
{
    use ApiResponse;

    public function index(Request $request)
    {
        $perPage = $request->get('per_page', 20);
        $query = Role::query()->sameInstitute();
        if ($search = $request->get('search')) {
            $query->where('name', 'like', "%{$search}%");
        }

        $roles = $query->with(['permissions'])->paginate($perPage);

        return $this->successResponse($roles, 'roles fetched successfully');
    }

    public function store(Request $request)
    {
        $validator = app('validator')->make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', 422, $validator->errors());
        }
        // Check if user has an institution
        if (!Auth::user()->institution) {
            return $this->errorResponse('User does not belong to any institution', 403);
        }

        $validatedData = $validator->validated();
        $validatedData['institution_id'] = Auth::user()->institution->id;

        try {
            $role = Role::create($validatedData);
            return $this->successResponse($role, 'Role created successfully', 201);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to create role: ' . $e->getMessage(), 500);
        }
    }
    public function show($id)
    {
        $role = Role::with('permissions')->find($id);

        if (! $role) {
            return $this->errorResponse('Role not found', 404);
        }

        return $this->successResponse($role, 'Role fetched successfully');
    }


    public function update(Request $request, Role $role)
    {
        $validator = app('validator')->make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', 422, $validator->errors());
        }

        $role->update($validator->validated());
        return $this->successResponse($role, 'Role updated successfully');
    }

    public function destroy(Role $role)
    {
        $role->delete();
        return $this->successResponse($role, 'Role deleted successfully');
    }
}
