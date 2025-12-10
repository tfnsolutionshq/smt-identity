<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateProfileRequest;
use App\Http\Requests\ChangePasswordRequest;
use App\Traits\ApiResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class ProfileController extends Controller
{
    use ApiResponse;

    public function update(UpdateProfileRequest $request)
    {
        $user = $request->user();
        $data = $request->only(['first_name', 'middle_name', 'last_name', 'phone', 'location', 'bio', 'timezone', 'language']);

        if ($request->hasFile('signature')) {
            if ($user->signature) {
                Storage::disk('public')->delete($user->signature);
            }
            $data['signature'] = $request->file('signature')->store('signatures', 'public');
        }

        if ($request->hasFile('avatar')) {
            if ($user->avatar) {
                Storage::disk('public')->delete($user->avatar);
            }
            $data['avatar'] = $request->file('avatar')->store('avatars', 'public');
        }

        $user->update($data);
        $user->load(['department', 'role']);

        return $this->successResponse($user, 'Profile updated successfully');
    }

    public function changePassword(ChangePasswordRequest $request)
    {
        $user = $request->user();

        if (!Hash::check($request->current_password, $user->password)) {
            return $this->errorResponse('Current password is incorrect', 422);
        }

        $user->update(['password' => $request->new_password]);

        return $this->successResponse(null, 'Password changed successfully');
    }
}
