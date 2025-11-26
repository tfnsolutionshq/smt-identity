<?php

namespace Database\Seeders;

use App\Models\Institution;
use Illuminate\Database\Seeder;

class InstitutionSeeder extends Seeder
{
    public function run(): void
    {
        Institution::create([
            'name' => 'Sample University',
            'code' => 'SU',
            'address' => '123 University Ave',
            'city' => 'Sample City',
            'state' => 'Sample State',
            'country' => 'Sample Country',
            'is_active' => true,
        ]);
    }
}