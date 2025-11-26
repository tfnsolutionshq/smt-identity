<?php

namespace App\Services;

use App\Models\User;
use App\Traits\ApiResponse;
use App\Services\MemoService;
use Illuminate\Support\Facades\Auth;

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
        $query = User::with(['department', 'role'])
            ->sameInstitution()
            ->excludeCurrentUser()
            ->active();

        $users = $query->paginate(10);
        
        $users->getCollection()->transform(function ($user) {
            $user->approval_stats = $this->memoService->getUserApprovalStats($user->id);
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
            return $user;
        });

        return $this->successResponse($users, 'Users with role: ' . $role);
    }

    public function getByDepartment($departmentId)
    {
        $users = User::with(['department', 'role'])
            ->sameInstitution()
            ->excludeCurrentUser()
            ->where('department_id', $departmentId)
            ->get();
            
        $users->transform(function ($user) {
            $user->approval_stats = $this->memoService->getUserApprovalStats($user->id);
            return $user;
        });

        return $this->successResponse($users, 'Users in department');
    }

    public function getUser($id, $auth_user)
    {
        $user = User::with(['department', 'role'])
            ->sameInstitution($auth_user)
            ->find($id);
            
        if ($user) {
            $user->approval_stats = $this->memoService->getUserApprovalStats($user->id);
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
}
