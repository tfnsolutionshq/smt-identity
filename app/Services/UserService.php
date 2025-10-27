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

    public function getUser($id)
    {
        $user = User::sameInstitution()->findOrFail($id);
        return $this->successResponse($user, 'User fetched successfully');
    }

    public function getByInstitution()
    {
        $users = User::sameInstitution()->get();
        return $this->successResponse($users, 'Users fetched successfully');
    }
}
