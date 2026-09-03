<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $avatarRules = ['sometimes', 'nullable'];
        if ($this->hasFile('avatar')) {
            $avatarRules = array_merge($avatarRules, ['image', 'mimes:jpeg,png,jpg', 'max:2048']);
        } else {
            $avatarRules[] = 'string';
        }

        return [
            'first_name' => 'sometimes|string|max:255',
            'middle_name' => 'sometimes|nullable|string|max:255',
            'last_name' => 'sometimes|string|max:255',
            'phone' => 'sometimes|nullable|string|max:20',
            'phone_number' => 'sometimes|nullable|string|max:20',
            'location' => 'sometimes|nullable|string|max:255',
            'bio' => 'sometimes|nullable|string',
            'timezone' => 'sometimes|nullable|string|max:50',
            'language' => 'sometimes|nullable|string|max:10',
            'signature' => 'sometimes|image|mimes:jpeg,png,jpg|max:2048',
            'avatar' => $avatarRules,
            'avatar_url' => 'sometimes|nullable|string',
        ];
    }
}
