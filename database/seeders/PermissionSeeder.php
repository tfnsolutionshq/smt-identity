<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Institution;
use App\Models\Role;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $institutions = Institution::all();

        if ($institutions->isEmpty()) {
            return;
        }

        $permissions = [
            // Users
            ['name' => 'users.view', 'description' => 'View user list and profiles'],
            ['name' => 'users.create', 'description' => 'Register / create new users'],
            ['name' => 'users.edit', 'description' => 'Update user details and profile'],
            ['name' => 'users.delete', 'description' => 'Delete user accounts'],
            ['name' => 'users.assign_role', 'description' => 'Assign roles to users'],
            ['name' => 'users.enroll_department', 'description' => 'Assign or transfer users to departments'],
            ['name' => 'users.reset_password', 'description' => 'Reset passwords for users'],
            ['name' => 'users.bulk_upload', 'description' => 'Bulk upload users via Excel/CSV'],

            // Departments
            ['name' => 'departments.view', 'description' => 'View departments and members'],
            ['name' => 'departments.create', 'description' => 'Create new departments'],
            ['name' => 'departments.edit', 'description' => 'Update department details'],
            ['name' => 'departments.delete', 'description' => 'Delete departments'],
            ['name' => 'departments.enroll_user', 'description' => 'Enroll users into departments'],
            ['name' => 'departments.manage_roles', 'description' => 'Manage department-specific roles'],

            // Roles & Permissions
            ['name' => 'roles.view', 'description' => 'View roles and their assigned permissions'],
            ['name' => 'roles.create', 'description' => 'Create new custom roles'],
            ['name' => 'roles.edit', 'description' => 'Update existing roles'],
            ['name' => 'roles.delete', 'description' => 'Delete custom roles'],
            ['name' => 'roles.assign_permissions', 'description' => 'Assign, sync, or remove permissions on roles'],
            ['name' => 'permissions.view', 'description' => 'View all available permissions'],
            ['name' => 'permissions.create', 'description' => 'Create permission records'],
            ['name' => 'permissions.edit', 'description' => 'Edit permission definitions'],
            ['name' => 'permissions.delete', 'description' => 'Delete permission records'],

            // Institutions
            ['name' => 'institutions.view', 'description' => 'View institution details'],
            ['name' => 'institutions.create', 'description' => 'Create new institution profiles'],
            ['name' => 'institutions.edit', 'description' => 'Update institution settings and profile'],
            ['name' => 'institutions.delete', 'description' => 'Delete institution profile'],

            // Memos
            ['name' => 'memos.view', 'description' => 'View mailbox, inbox, sent, and drafts'],
            ['name' => 'memos.create', 'description' => 'Compose and save memo drafts'],
            ['name' => 'memos.edit', 'description' => 'Edit own drafted memos'],
            ['name' => 'memos.send', 'description' => 'Dispatch memos to recipients'],
            ['name' => 'memos.broadcast_department', 'description' => 'Broadcast memo to entire department'],
            ['name' => 'memos.reply', 'description' => 'Reply to received memos'],
            ['name' => 'memos.forward', 'description' => 'Forward memos to other users'],
            ['name' => 'memos.pin_star_archive', 'description' => 'Pin, star, or archive personal memos'],
            ['name' => 'memos.cancel', 'description' => 'Cancel in-flight sent memos'],
            ['name' => 'memos.delete', 'description' => 'Move memos to trash / soft delete'],
            ['name' => 'memos.force_delete', 'description' => 'Permanently delete memos'],
            ['name' => 'memos.view_all', 'description' => 'View all institution memos (audit / compliance)'],

            // Workflows & Approvals
            ['name' => 'workflows.view', 'description' => 'View approval workflow templates'],
            ['name' => 'workflows.create', 'description' => 'Create new approval workflows'],
            ['name' => 'workflows.edit', 'description' => 'Update approval workflows'],
            ['name' => 'workflows.delete', 'description' => 'Delete approval workflows'],
            ['name' => 'workflows.toggle_status', 'description' => 'Activate or deactivate workflows'],
            ['name' => 'approvals.view_pending', 'description' => 'View pending approval requests queue'],
            ['name' => 'approvals.process', 'description' => 'Approve, reject, or request revision on memos'],

            // Comments
            ['name' => 'comments.view', 'description' => 'View comments and discussion threads'],
            ['name' => 'comments.create', 'description' => 'Add comments or review notes on memos'],
            ['name' => 'comments.edit', 'description' => 'Edit own comments'],
            ['name' => 'comments.delete', 'description' => 'Delete comments'],

            // External Memos
            ['name' => 'external_memos.view', 'description' => 'View external documents and inbox'],
            ['name' => 'external_memos.create', 'description' => 'Register and dispatch external memos'],
            ['name' => 'external_memos.edit', 'description' => 'Update external memo details'],
            ['name' => 'external_memos.delete', 'description' => 'Delete external memos'],
            ['name' => 'external_memos.track', 'description' => 'Access tracking info and QR codes'],
            ['name' => 'external_memos.share', 'description' => 'Share external memos externally'],

            // Categories & Priorities
            ['name' => 'categories.view', 'description' => 'View memo categories'],
            ['name' => 'categories.create', 'description' => 'Create memo categories'],
            ['name' => 'categories.edit', 'description' => 'Edit categories'],
            ['name' => 'categories.delete', 'description' => 'Delete categories'],
            ['name' => 'priorities.view', 'description' => 'View priority levels'],

            // Analytics & Reports
            ['name' => 'analytics.view', 'description' => 'View department analytics & statistics'],
            ['name' => 'analytics.institution', 'description' => 'View institution-wide performance metrics'],
            ['name' => 'reports.view', 'description' => 'View volume trends and report overviews'],
            ['name' => 'reports.export', 'description' => 'Export reporting data (PDF/Excel)'],

            // Settings & Audit Logs
            ['name' => 'settings.view', 'description' => 'View institution configurations'],
            ['name' => 'settings.update', 'description' => 'Update general settings'],
            ['name' => 'settings.security', 'description' => 'Configure security policies & 2FA'],
            ['name' => 'settings.appearance', 'description' => 'Configure institution branding & appearance'],
            ['name' => 'settings.integrations', 'description' => 'Configure external API integrations'],
            ['name' => 'logs.view_own', 'description' => 'View personal activity logs'],
            ['name' => 'logs.view_department', 'description' => 'View department-wide activity logs'],
            ['name' => 'logs.view_institution', 'description' => 'View entire institution audit logs'],

            // Notifications
            ['name' => 'notifications.view', 'description' => 'View own notifications'],
            ['name' => 'notifications.manage', 'description' => 'Mark read, clear notifications'],
            ['name' => 'notifications.settings', 'description' => 'Configure personal notification preferences'],
        ];

        foreach ($institutions as $institution) {
            $permissionIds = [];
            foreach ($permissions as $permission) {
                $p = Permission::firstOrCreate(
                    [
                        'name' => $permission['name'],
                        'institution_id' => $institution->id,
                    ],
                    [
                        'description' => $permission['description'],
                    ]
                );
                $permissionIds[] = $p->id;
            }

            // Sync all permissions to the Admin role for this institution
            $adminRole = Role::where('institution_id', $institution->id)
                ->where('name', 'Admin')
                ->first();

            if ($adminRole) {
                $adminRole->permissions()->syncWithoutDetaching($permissionIds);
            }
        }
    }
}