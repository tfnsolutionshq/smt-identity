<?php

namespace App\Services;

use App\Models\User;
use App\Traits\ApiResponse;

class UserService
{
    use ApiResponse;

    public function getAll($request)
    {
        $query = User::sameInstitute()->excludeCurrentUser()->active();
       return $this->successResponse(
            $query->paginate(10),
            'Users fetched successfully'
        );
    }

    public function getByRole($role)
    {
        $users = User::sameInstitute()->excludeCurrentUser()->role($role)->get();

        return $this->successResponse(
            $users,
            'Users with role: ' . $role
        );
    }

    public function getUser($id)
    {
        $user = User::sameInstitute()->findOrFail($id);

        return response()->json([
            'status' => true,
            'data' => $user,
        ]);
        return $this->successResponse(
            $user,
            'User fetched successfully'
        );
    }

    public function getByInstitute($instituteId)
    {
        $users = User::where('institute_id', $instituteId)->get();
        return $this->successResponse(
            $users,
            'Users fetched successfully'
        );
    }


}
