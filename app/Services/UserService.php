<?php

namespace App\Services;

use App\Models\User;
use App\Traits\ApiResponse;
use Illuminate\Support\Facades\Auth;

class UserService
{
    use ApiResponse;

    public function getAll($request)
    {
        $query = User::sameInstitution()
            ->excludeCurrentUser()
            ->active();

        return $this->successResponse(
            $query->paginate(10),
            'Users fetched successfully'
        );
    }

    public function getByRole($role)
    {
        $users = User::sameInstitution()
            ->excludeCurrentUser()
            ->role($role)
            ->get();

        return $this->successResponse($users, 'Users with role: ' . $role);
    }

    public function getUser($id, $auth_user)
    {
        $user = User::sameInstitution($auth_user)->find($id);
        return $this->successResponse($user, 'User fetched successfully');
    }

    public function getByInstitution()
    {
        $users = User::sameInstitution()->get();
        return $this->successResponse($users, 'Users fetched successfully');
    }

    public function getUserStatistics()
    {
        $totalUsers = User::sameInstitution()->count();
        $activeUsers = User::sameInstitution()->active()->count();

        // Count users grouped by role
        $usersByRole = User::sameInstitution()
            ->selectRaw('role, COUNT(*) as count')
            ->groupBy('role')
            ->pluck('count', 'role');

        return $this->successResponse([
            'totalUsers' => $totalUsers,
            'activeUsers' => $activeUsers,
            'usersByRole' => $usersByRole,
        ], 'User statistics fetched successfully');
    }
}
