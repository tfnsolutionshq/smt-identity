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

    public function update(Request $request, $id)
    {
        return $this->users->updateUser($request, $id);
    }

    public function userStatistics()
    {
        return $this->users->getUserStatistics();
    }

    public function assignRole(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'role_id' => 'required|exists:roles,id',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', 422, $validator->errors());
        }

        $user = User::find($id);
        if (!$user) {
            return $this->errorResponse('User not found', 404);
        }

        // Check authorization
        $authUser = Auth::user();
        if ($authUser->isDepartmentHead() && $user->department_id !== $authUser->department_id) {
            return $this->errorResponse('You can only assign roles to users in your department', 403);
        }
        
        if (!$authUser->isAdmin() && !$authUser->isDepartmentHead()) {
            return $this->errorResponse('Unauthorized to assign roles', 403);
        }

        $user->update(['role_id' => $request->role_id]);
        return $this->successResponse($user->load('role'), 'Role assigned successfully');
    }

    public function enrollInDepartment(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'department_id' => 'required|exists:departments,id',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', 422, $validator->errors());
        }

        $user = User::find($id);
        if (!$user) {
            return $this->errorResponse('User not found', 404);
        }

        // Check authorization
        $authUser = Auth::user();
        if (!$authUser->isAdmin()) {
            return $this->errorResponse('Only admins can enroll users in departments', 403);
        }

        $user->update(['department_id' => $request->department_id]);
        return $this->successResponse($user->load('department'), 'User enrolled in department successfully');
    }

    public function verifyByEmail(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', 422, $validator->errors());
        }

        $user = User::where('email', $request->email)->with(['department', 'role'])->first();
        
        if (!$user) {
            return $this->errorResponse('User not found', 404);
        }

        return $this->successResponse(['user' => $user], 'User found successfully');
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
