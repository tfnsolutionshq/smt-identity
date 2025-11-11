<?php

namespace App\Services;

use App\Models\Role;
use App\Models\Permission;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class RolePermissionService
{
    /**
     * Assign permissions to a role
     */
    public function assignPermissionsToRole(string $roleId, array $permissionIds): array
    {
        try {
            // Validate input
            $validator = Validator::make([
                'role_id' => $roleId,
                'permission_ids' => $permissionIds
            ], [
                'role_id' => 'required|uuid|exists:roles,id',
                'permission_ids' => 'required|array',
                'permission_ids.*' => 'required|uuid|exists:permissions,id'
            ]);

            if ($validator->fails()) {
                throw new ValidationException($validator);
            }

            $result = DB::transaction(function () use ($roleId, $permissionIds) {
                $role = Role::sameInstitute()->findOrFail($roleId);

                // Validate that all permissions belong to the same institution
                $validPermissions = Permission::sameInstitute()
                    ->whereIn('id', $permissionIds)
                    ->pluck('id')
                    ->toArray();

                if (count($validPermissions) !== count($permissionIds)) {
                    throw new \Exception('Some permissions do not exist or belong to different institution');
                }

                // Assign permissions
                $role->permissions()->syncWithoutDetaching($validPermissions);

                // Get updated role with permissions
                $updatedRole = Role::with('permissions')->find($roleId);

                return [
                    'success' => true,
                    'message' => 'Permissions assigned successfully',
                    'role' => $updatedRole,
                    'assigned_permissions' => $validPermissions
                ];
            });

            return $result;

        } catch (ValidationException $e) {
            return [
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ];
        } catch (\Exception $e) {
            logger()->error('Failed to assign permissions to role: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to assign permissions: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Sync role permissions (replace all)
     */
    public function syncRolePermissions(string $roleId, array $permissionIds): array
    {
        try {
            // Validate input
            $validator = Validator::make([
                'role_id' => $roleId,
                'permission_ids' => $permissionIds
            ], [
                'role_id' => 'required|uuid|exists:roles,id',
                'permission_ids' => 'required|array',
                'permission_ids.*' => 'required|uuid|exists:permissions,id'
            ]);

            if ($validator->fails()) {
                throw new ValidationException($validator);
            }

            $result = DB::transaction(function () use ($roleId, $permissionIds) {
                $role = Role::sameInstitute()->findOrFail($roleId);

                // Validate permissions belong to same institution
                $validPermissions = Permission::sameInstitute()
                    ->whereIn('id', $permissionIds)
                    ->pluck('id')
                    ->toArray();

                // Sync permissions
                $role->permissions()->sync($validPermissions);

                // Get updated role with permissions
                $updatedRole = Role::with('permissions')->find($roleId);

                return [
                    'success' => true,
                    'message' => 'Permissions synced successfully',
                    'role' => $updatedRole,
                    'current_permissions' => $validPermissions
                ];
            });

            return $result;

        } catch (ValidationException $e) {
            return [
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ];
        } catch (\Exception $e) {
            logger()->error('Failed to sync role permissions: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to sync permissions: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Remove specific permissions from role
     */
    public function removePermissionsFromRole(string $roleId, array $permissionIds): array
    {
        try {
            // Validate input
            $validator = Validator::make([
                'role_id' => $roleId,
                'permission_ids' => $permissionIds
            ], [
                'role_id' => 'required|uuid|exists:roles,id',
                'permission_ids' => 'required|array',
                'permission_ids.*' => 'required|uuid|exists:permissions,id'
            ]);

            if ($validator->fails()) {
                throw new ValidationException($validator);
            }

            $role = Role::sameInstitute()->findOrFail($roleId);
            $removedCount = $role->permissions()->detach($permissionIds);

            return [
                'success' => true,
                'message' => 'Permissions removed successfully',
                'removed_count' => $removedCount,
                'removed_permissions' => $permissionIds
            ];

        } catch (ValidationException $e) {
            return [
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ];
        } catch (\Exception $e) {
            logger()->error('Failed to remove permissions from role: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to remove permissions: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get role with permissions
     */
    public function getRoleWithPermissions(string $roleId): array
    {
        try {
            $validator = Validator::make([
                'role_id' => $roleId
            ], [
                'role_id' => 'required|uuid|exists:roles,id'
            ]);

            if ($validator->fails()) {
                throw new ValidationException($validator);
            }

            $role = Role::sameInstitute()
                ->with('permissions')
                ->findOrFail($roleId);

            return [
                'success' => true,
                'role' => $role,
                'permissions' => $role->permissions
            ];

        } catch (ValidationException $e) {
            return [
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Role not found or access denied'
            ];
        }
    }

    /**
     * Get all permissions for current institution
     */
    public function getAllPermissions(): array
    {
        try {
            $permissions = Permission::sameInstitute()
                ->get();

            return [
                'success' => true,
                'permissions' => $permissions
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to fetch permissions'
            ];
        }
    }
}
