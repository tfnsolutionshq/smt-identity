<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Http\Request;
use App\Traits\ApiResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use App\Imports\UsersImport;
use Maatwebsite\Excel\Facades\Excel;

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
        /** @var User $authUser */
        $authUser = Auth::user();
        if ($authUser->isDepartmentHead() && $user->department_id !== $authUser->department_id) {
            return $this->errorResponse('You can only assign roles to users in your department', 403);
        }

        if (!$authUser->isAdmin() && !$authUser->isDepartmentHead()) {
            return $this->errorResponse('Unauthorized to assign roles', 403);
        }

        $user->update(['role_id' => $request->role_id]);
        $user->load('role');
        $user->avatar = $user->avatar_url;
        return $this->successResponse($user, 'Role assigned successfully');
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
        /** @var User $authUser */
        $authUser = Auth::user();
        if (!$authUser->isAdmin()) {
            return $this->errorResponse('Only admins can enroll users in departments', 403);
        }

        $user->update(['department_id' => $request->department_id]);
        $user->load('department');
        $user->avatar = $user->avatar_url;
        return $this->successResponse($user, 'User enrolled in department successfully');
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

        $user->avatar = $user->avatar_url;
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

    public function bulkUpload(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|file|mimes:xlsx,xls,csv|max:2048',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', 422, $validator->errors());
        }

        /** @var User $authUser */
        $authUser = Auth::user();
        if (!$authUser->isAdmin()) {
            return $this->errorResponse('Only admins can upload users', 403);
        }

        try {
            Excel::import(new UsersImport, $request->file('file'));
            return $this->successResponse(null, 'Users uploaded successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Upload failed: ' . $e->getMessage(), 500);
        }
    }

    public function assignUsersToDepartment(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_ids' => 'required|array',
            'user_ids.*' => 'required|exists:users,id',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', 422, $validator->errors());
        }

        /** @var User $authUser */
        $authUser = Auth::user();
        if (!$authUser->isDepartmentHead() && !$authUser->isAdmin()) {
            return $this->errorResponse('Only department heads and admins can assign users to departments', 403);
        }

        $users = User::whereIn('id', $request->user_ids)
            ->sameInstitution()
            ->get();

        if ($users->count() !== count($request->user_ids)) {
            return $this->errorResponse('Some users not found or not in your institution', 404);
        }

        $users->each(function ($user) use ($authUser) {
            $user->update(['department_id' => $authUser->department_id]);
        });

        return $this->successResponse(
            $users->load('department'),
            'Users assigned to department successfully'
        );
    }

    public function getDepartmentUsers(Request $request)
    {
        /** @var User $authUser */
        $authUser = Auth::user();

        // if (!$authUser->isDepartmentHead()) {
        //     return $this->errorResponse('Only department heads can access this endpoint', 403);
        // }

        $perPage = $request->get('per_page', 20);
        $users = User::with(['department', 'role'])
            ->where('department_id', $authUser->department_id)
            ->paginate($perPage);

        $users->getCollection()->transform(function ($user) {
            $user->avatar = $user->avatar_url;
            return $user;
        });

        return $this->successResponse($users, 'Department users retrieved successfully');
    }

    public function search(Request $request)
    {
        $search = $request->get('q');

        if (!$search) {
            return $this->errorResponse('Search query is required', 400);
        }

        $perPage = $request->get('per_page', 20);
        $users = User::with(['department', 'role'])
            ->sameInstitution()
            ->where(function($query) use ($search) {
                $query->where('first_name', 'like', "%{$search}%")
                      ->orWhere('last_name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%");
            })
            ->paginate($perPage);

        $users->getCollection()->transform(function ($user) {
            $user->avatar = $user->avatar_url;
            return $user;
        });

        return $this->successResponse($users, 'Users found successfully');
    }

    public function destroy($id)
    {
        $user = User::sameInstitution()->find($id);
        
        if (!$user) {
            return $this->errorResponse('User not found', 404);
        }

        /** @var User $authUser */
        $authUser = Auth::user();
        if (!$authUser->isAdmin()) {
            return $this->errorResponse('Only admins can delete users', 403);
        }

        $user->delete();
        return $this->successResponse(null, 'User deleted successfully');
    }

    public function resetPassword(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'password' => 'required|min:8',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', 422, $validator->errors());
        }

        /** @var User $authUser */
        $authUser = Auth::user();
        if (!$authUser->isAdmin()) {
            return $this->errorResponse('Only admins can reset user passwords', 403);
        }

        $user = User::sameInstitution()->find($id);
        if (!$user) {
            return $this->errorResponse('User not found', 404);
        }

        $user->update([
            'password' => $request->password,
            'must_change_password' => true
        ]);
        return $this->successResponse(null, 'Password reset successfully');
    }
}
