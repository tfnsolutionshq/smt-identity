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
        $isNone = empty($departmentId) || strtolower((string)$departmentId) === 'none' || $departmentId === 'null';

        $query = User::with(['department', 'role']);

        if (!$isNone) {
            $query->where('department_id', $departmentId);
        }

        $search = $request->input('search') ?? $request->input('q');
        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('middle_name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhereRaw("CONCAT_WS(' ', first_name, last_name) LIKE ?", ["%{$search}%"])
                  ->orWhereRaw("CONCAT_WS(' ', first_name, middle_name, last_name) LIKE ?", ["%{$search}%"]);
            });
        }

        $users = $query->get();

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