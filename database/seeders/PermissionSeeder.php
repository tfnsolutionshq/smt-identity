<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Institution;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $institution = Institution::first();
        
        $permissions = [
            'users.view',
            'users.create',
            'users.edit',
            'users.delete',
            'roles.view',
            'roles.create',
            'roles.edit',
            'roles.delete',
        ];

        foreach ($permissions as $permission) {
            Permission::create([
                'name' => $permission,
                'description' => ucfirst(str_replace('.', ' ', $permission)),
                'institution_id' => $institution->id,
            ]);
        }
    }
}