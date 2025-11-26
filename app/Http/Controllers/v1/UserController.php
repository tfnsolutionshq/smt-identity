<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Http\Request;
use App\Traits\ApiResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class UserController extends Controller
{
    use ApiResponse;
    protected $users;

    public function __construct(UserService $users)
    {
        $this->users = $users;
    }

    public function index(Request $request, User $user)
    {
        return $this->users->getAll($request);
    }

    public function byRole($role)
    {
        return $this->users->getByRole($role);
    }

    public function byDepartment($departmentId)
    {
        return $this->users->getByDepartment($departmentId);
    }

    public function byInstitute(Request $request)
    {
        return $this->users->getByInstitution();
    }

    public function show($id, $auth_user = null)
    {
        return $this->users->getUser($id, $auth_user);
    }

    public function update(Request $request) {}

    public function userStatistics()
    {
        return $this->users->getUserStatistics();
    }

    public function verifyRecipients(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'emails' => 'required|array',
            'emails.*' => 'required|email',
            'institution_id' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors(),
            ], 422);
        }

        $users = User::where('institution_id', $request->institution_id)
            ->whereIn('email', $request->emails)
            ->get(['id', 'email', 'first_name', 'last_name']);

        return response()->json([
            'valid_recipients' => $users,
            'invalid_emails' => array_diff($request->emails, $users->pluck('email')->toArray()),
        ]);
    }
}
