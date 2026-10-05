<?php

namespace App\Services;

use App\Models\User;
use App\Traits\ApiResponse;
use App\Services\MemoService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class UserService
{
    use ApiResponse;
    
    protected $memoService;
    
    public function __construct(MemoService $memoService)
    {
        $this->memoService = $memoService;
    }

    public function getAll($request)
    {
        $perPage = $request->get('per_page', 20);
        $query = User::with(['department', 'role'])
            ->sameInstitution()
            ->excludeCurrentUser();

        $users = $query->paginate($perPage);
        
        $users->getCollection()->transform(function ($user) {
            $user->approval_stats = $this->memoService->getUserApprovalStats($user->id);
            $user->avatar = $user->avatar_url;
            return $user;
        });

        return $this->successResponse($users, 'Users fetched successfully');
    }

    public function getByRole($role)
    {
        $users = User::with(['department', 'role'])
            ->sameInstitution()
            ->excludeCurrentUser()
            ->role($role)
            ->get();
            
        $users->transform(function ($user) {
            $user->approval_stats = $this->memoService->getUserApprovalStats($user->id);
            $user->avatar = $user->avatar_url;
            return $user;
        });

        return $this->successResponse($users, 'Users with role: ' . $role);
    }

    public function getByDepartment($departmentId)
    {
        $isNone = empty($departmentId) || strtolower((string)$departmentId) === 'none' || $departmentId === 'null';

        $query = User::with(['department', 'role'])
            ->sameInstitution()
            ->excludeCurrentUser();

        if (!$isNone) {
            $query->where('department_id', $departmentId);
        }

        $users = $query->get();
            
        $users->transform(function ($user) {
            $user->approval_stats = $this->memoService->getUserApprovalStats($user->id);
            $user->avatar = $user->avatar_url;
            return $user;
        });

        return $this->successResponse($users, $isNone ? 'Users fetched successfully' : 'Users in department');
    }

    public function getUser($id, $auth_user)
    {
        $user = User::with(['department', 'role'])
            ->sameInstitution($auth_user)
            ->find($id);
            
        if ($user) {
            $user->approval_stats = $this->memoService->getUserApprovalStats($user->id);
            $user->avatar = $user->avatar_url;
        }
        
        return $this->successResponse($user, 'User fetched successfully');
    }

    public function getByInstitution()
    {
        $users = User::with(['department', 'role'])
            ->sameInstitution()
            ->get();
            
        $users->transform(function ($user) {
            $user->approval_stats = $this->memoService->getUserApprovalStats($user->id);
            $user->avatar = $user->avatar_url;
            return $user;
        });
        
        return $this->successResponse($users, 'Users fetched successfully');
    }

    public function getUserStatistics()
    {
        $totalUsers = User::sameInstitution()->count();
        $activeUsers = User::sameInstitution()->active()->count();

        // Count users grouped by role
        $usersByRole = User::sameInstitution()
            ->with('role')
            ->get()
            ->groupBy('role.name')
            ->map(function ($users) {
                return $users->count();
            });

        return $this->successResponse([
            'totalUsers' => $totalUsers,
            'activeUsers' => $activeUsers,
            'usersByRole' => $usersByRole,
        ], 'User statistics fetched successfully');
    }

    public function updateUser($request, $id)
    {
        $validator = Validator::make($request->all(), [
            'first_name' => 'sometimes|string|max:255',
            'middle_name' => 'sometimes|nullable|string|max:255',
            'last_name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:users,email,' . $id,
            'phone' => 'sometimes|nullable|string|max:20',
            'phone_number' => 'sometimes|nullable|string|max:20',
            'avatar' => 'sometimes|nullable',
            'avatar_url' => 'sometimes|nullable|string',
            'location' => 'sometimes|nullable|string|max:255',
            'bio' => 'sometimes|nullable|string',
            'timezone' => 'sometimes|nullable|string|max:50',
            'language' => 'sometimes|nullable|string|max:10',
            'department_id' => 'sometimes|exists:departments,id',
            'role_id' => 'sometimes|exists:roles,id',
            'is_active' => 'sometimes|boolean'
        ]);

        if ($validator->fails()) {
            return $this->errorResponse($validator->errors()->first(), 422);
        }

        $user = User::sameInstitution()->find($id);
        if (!$user) {
            return $this->errorResponse('User not found', 404);
        }

        $data = $request->only([
            'first_name', 'middle_name', 'last_name', 'email', 'phone',
            'location', 'bio', 'timezone', 'language',
            'department_id', 'role_id', 'is_active'
        ]);

        if ($request->has('phone_number') && !$request->has('phone')) {
            $data['phone'] = $request->phone_number;
        }

        if ($request->filled('avatar')) {
            $data['avatar'] = $request->avatar;
        } elseif ($request->filled('avatar_url')) {
            $data['avatar'] = $request->avatar_url;
        }

        $user->update($data);

        $user->load(['department', 'role']);
        $user->role = strtolower($user->role->name);
        unset($user->role_id);
        $user->approval_stats = $this->memoService->getUserApprovalStats($user->id);
        $user->avatar = $user->avatar_url;

        return $this->successResponse($user, 'User updated successfully');
    }
}
