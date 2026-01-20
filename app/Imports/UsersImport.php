<?php

namespace App\Imports;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\Importable;

class UsersImport implements ToModel, WithHeadingRow, WithValidation
{
    use Importable;

    public function model(array $row)
    {
        $authUser = Auth::user();
        
        // Split name into first and last name
        $nameParts = explode(' ', trim($row['firstname'] . ' ' . ($row['lastname'] ?? '')), 2);
        $firstName = $nameParts[0] ?? '';
        $lastName = $nameParts[1] ?? '';
        
        return new User([
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $row['email'],
            'password' => Hash::make('password123'),
            'institution_id' => $authUser->institution_id,
            'is_active' => true,
        ]);
    }

    public function rules(): array
    {
        return [
            'email' => 'required|email|unique:users,email',
            'firstname' => 'required|string|max:255',
        ];
    }
}