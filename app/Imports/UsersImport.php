<?php

namespace App\Imports;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\Importable;
use App\Traits\NotifyTrait;

class UsersImport implements ToModel, WithHeadingRow, WithValidation
{
    use Importable, NotifyTrait;

    protected function getServiceName(): string
    {
        return 'identity';
    }

    public function model(array $row)
    {
        $authUser = Auth::user();
        $defaultPassword = 'password123';
        
        $nameParts = explode(' ', trim($row['firstname'] . ' ' . ($row['lastname'] ?? '')), 2);
        $firstName = $nameParts[0] ?? '';
        $lastName = $nameParts[1] ?? '';
        
        $user = new User([
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $row['email'],
            'password' => Hash::make($defaultPassword),
            'institution_id' => $authUser->institution_id,
            'is_active' => true,
        ]);
        
        $user->save();
        
        $this->sendNotification('user_created', $user->email, [
            'user_name' => $firstName . ' ' . $lastName,
            'email' => $user->email,
            'password' => $defaultPassword,
        ]);
        
        return $user;
    }

    public function rules(): array
    {
        return [
            'email' => 'required|email|unique:users,email',
            'firstname' => 'required|string|max:255',
        ];
    }
}