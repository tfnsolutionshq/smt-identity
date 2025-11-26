<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Institution;
use Illuminate\Database\Seeder;

class DepartmentSeeder extends Seeder
{
    public function run(): void
    {
        $institution = Institution::first();
        
        Department::create([
            'name' => 'Computer Science',
            'department_code' => 'CS',
            'institution_id' => $institution->id,
            'is_active' => true,
        ]);

        Department::create([
            'name' => 'Mathematics',
            'department_code' => 'MATH',
            'institution_id' => $institution->id,
            'is_active' => true,
        ]);
    }
}