<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Http\Request;
use App\Traits\ApiResponse;

class UserController extends Controller
{
    use ApiResponse;
    protected $users;

    public function __construct(UserService $users)
    {
        $this->users = $users;
    }

    public function index(Request $request)
    {
        return $this->users->getAll($request);
    }

    public function byRole($role)
    {
        return $this->users->getByRole($role);
    }

    public function byInstitute(Request $request)
    {
        return $this->users->getByInstitution();
    }

    public function show($id)
    {
        return $this->users->getUser($id);
    }

    public function update(Request $request){

    }

    public function userStatistics()
    {
        $totalUsers = User::sameInstitute()->count();
        $activeUsers = User::sameInstitute()->active()->count();

        // Count users grouped by role
        $usersByRole = User::sameInstitute()
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
