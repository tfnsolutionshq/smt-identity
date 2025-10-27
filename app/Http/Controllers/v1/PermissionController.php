<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use Illuminate\Http\Request;
use App\Traits\ApiResponse;
use Illuminate\Support\Facades\Auth;

class PermissionController extends Controller
{
    use ApiResponse;

    public function index(Request $request)
    {
        $perPage = $request->get('per_page', 20);
        $query = Permission::query()->sameInstitute();
        if ($search = $request->get('search')) {
            $query->where('name', 'like', "%{$search}%");
        }

        $permissions = $query
            ->with(['roles'])->paginate($perPage);

        return $this->successResponse($permissions, 'permissions fetched successfully');
    }

    public function store(Request $request)
    {
        $validator = app('validator')->make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:255'
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', 422, $validator->errors());
        }

        // Check if user has an institution
        if (!Auth::user()->institution) {
            return $this->errorResponse('Permission does not belong to this institution', 403);
        }

        $validatedData = $validator->validated();
        $validatedData['institution_id'] = Auth::user()->institution->id;

        try {
            $permission = Permission::create($validatedData);
            return $this->successResponse($permission, 'permission created successfully', 201);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to create permission: ' . $e->getMessage(), 500);
        }
    }

    public function update(Request $request, Permission $permission)
    {
        $validator = app('validator')->make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:255'
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', 422, $validator->errors());
        }

        $permission->update($validator->validated());

        return $this->successResponse($permission, 'Permission updated successfully');
    }


    public function show(Permission $permission)
    {
        $permission->load('roles'); // Eager load roles
        return $this->successResponse($permission, 'Permission fetched successfully');
    }


    public function destroy(Permission $permission)
    {
        $permission->delete();
        return $this->successResponse($permission, 'Permission deleted successfully');
    }
}
