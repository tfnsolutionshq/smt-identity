<?php
namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;

use App\Services\RolePermissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request; // ← Correct import
use Illuminate\Support\Facades\Validator;

class RolePermissionController extends Controller
{
    public function __construct(private RolePermissionService $rolePermissionService)
    {
    }

    /**
     * Assign permissions to role
     */
    public function assignPermissions(Request $request, string $roleId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'permission_ids' => 'required|array',
            'permission_ids.*' => 'required|uuid'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $result = $this->rolePermissionService->assignPermissionsToRole(
            $roleId,
            $request->permission_ids
        );

        return response()->json($result, $result['success'] ? 200 : 400);
    }

    /**
     * Sync role permissions (replace all)
     */
    public function syncPermissions(Request $request, string $roleId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'permission_ids' => 'required|array',
            'permission_ids.*' => 'required|uuid'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $result = $this->rolePermissionService->syncRolePermissions(
            $roleId,
            $request->permission_ids
        );

        return response()->json($result, $result['success'] ? 200 : 400);
    }

    /**
     * Remove permissions from role
     */
    public function removePermissions(Request $request, string $roleId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'permission_ids' => 'required|array',
            'permission_ids.*' => 'required|uuid'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $result = $this->rolePermissionService->removePermissionsFromRole(
            $roleId,
            $request->permission_ids
        );

        return response()->json($result, $result['success'] ? 200 : 400);
    }

    /**
     * Get role with permissions
     */
    public function getRolePermissions(string $roleId): JsonResponse
    {
        $result = $this->rolePermissionService->getRoleWithPermissions($roleId);
        return response()->json($result, $result['success'] ? 200 : 404);
    }

    /**
     * Get all available permissions
     */
    public function getAllPermissions(): JsonResponse
    {
        $result = $this->rolePermissionService->getAllPermissions();
        return response()->json($result);
    }

    /**
     * Bulk operations - multiple actions in one request
     */
    public function bulkOperations(Request $request, string $roleId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'operations' => 'required|array',
            'operations.assign' => 'sometimes|array',
            'operations.assign.*' => 'uuid',
            'operations.remove' => 'sometimes|array',
            'operations.remove.*' => 'uuid',
            'operations.sync' => 'sometimes|array',
            'operations.sync.*' => 'uuid',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $operations = $request->operations;
        $results = [];

        // Sync operation (replaces all permissions)
        if (isset($operations['sync'])) {
            $results['sync'] = $this->rolePermissionService->syncRolePermissions(
                $roleId,
                $operations['sync']
            );
        }

        // Assign additional permissions
        if (isset($operations['assign'])) {
            $results['assign'] = $this->rolePermissionService->assignPermissionsToRole(
                $roleId,
                $operations['assign']
            );
        }

        // Remove specific permissions
        if (isset($operations['remove'])) {
            $results['remove'] = $this->rolePermissionService->removePermissionsFromRole(
                $roleId,
                $operations['remove']
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Bulk operations completed',
            'operations' => $results,
            'final_role' => $this->rolePermissionService->getRoleWithPermissions($roleId)
        ]);
    }
}
