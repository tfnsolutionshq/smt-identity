<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Role;
use App\Models\Department;
use App\Models\Institution;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $institution = Institution::first();
        $adminRole = Role::where('name', 'Admin')->first();
        $userRole = Role::where('name', 'User')->first();
        $department = Department::first();

        User::create([
            'first_name' => 'Admin',
            'last_name' => 'User',
            'email' => 'admin@example.com',
            'password' => 'password',
            'role_id' => $adminRole->id,
            'department_id' => $department->id,
            'institution_id' => $institution->id,
            'is_active' => true,
        ]);

        User::create([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
            'password' => 'password',
            'role_id' => $userRole->id,
            'department_id' => $department->id,
            'institution_id' => $institution->id,
            'is_active' => true,
        ]);
    }
}