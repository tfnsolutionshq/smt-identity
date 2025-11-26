<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\Institution;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $institution = Institution::first();
        
        Role::create([
            'name' => 'Admin',
            'description' => 'System Administrator',
            'institution_id' => $institution->id,
            'is_active' => true,
        ]);

        Role::create([
            'name' => 'User',
            'description' => 'Regular User',
            'institution_id' => $institution->id,
            'is_active' => true,
        ]);

        Role::create([
            'name' => 'Manager',
            'description' => 'Department Manager',
            'institution_id' => $institution->id,
            'is_active' => true,
        ]);
    }
}