<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class S2SController extends Controller
{
    public function validateUser(Request $request)
    {
        $userId = $request->input('user_id');
        $user = User::with(['department', 'role.permissions'])->find($userId);
        
        if (!$user) {
            return response()->json(['valid' => false], 404);
        }

        return response()->json([
            'valid' => true,
            'user' => $user
        ]);
    }

    public function getUsersByRole(Request $request)
    {
        $roleId = $request->input('role_id');
        $users = User::with(['department', 'role'])
            ->where('role_id', $roleId)
            ->get();

        return response()->json(['users' => $users]);
    }

    public function getUsersByDepartment(Request $request)
    {
        $departmentId = $request->input('department_id');
        $users = User::with(['department', 'role'])
            ->where('department_id', $departmentId)
            ->get();

        return response()->json(['users' => $users]);
    }

    public function getUserById(Request $request)
    {
        $userId = $request->input('user_id');
        $user = User::with(['department', 'role.permissions'])->find($userId);
        
        if (!$user) {
            return response()->json(['user' => null], 404);
        }

        return response()->json(['user' => $user]);
    }

    public function getUsersByIds(Request $request)
    {
        $userIds = $request->input('user_ids', []);
        $users = User::with(['department', 'role.permissions'])
            ->whereIn('id', $userIds)
            ->get();

        return response()->json(['users' => $users]);
    }
}