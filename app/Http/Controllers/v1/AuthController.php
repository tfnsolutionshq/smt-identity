<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\PasswordResetOtp;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use App\Traits\LogsActivity;
use Illuminate\Support\Facades\Log;
use App\Traits\NotifyTrait;

class AuthController extends Controller
{
    use ApiResponse, LogsActivity , NotifyTrait;

    protected function getServiceName(): string
    {
        return 'identity';
    }

    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|confirmed|min:8',
            'department_id' => 'nullable|exists:departments,id',
            'role_id' => 'nullable|exists:roles,id',
            'phone' => 'nullable|string|max:20',
            'location' => 'nullable|string|max:255',
            'bio' => 'nullable|string',
            'timezone' => 'nullable|string|max:50',
            'language' => 'nullable|string|max:10',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse($validator->errors()->first(), 422);
        }

        $user = User::create([
            'first_name' => $request->first_name,
            'middle_name' => $request->middle_name,
            'last_name' => $request->last_name,
            'email' => $request->email,
            'phone' => $request->phone,
            'password' => $request->password,
            'must_change_password' => true,
            'department_id' => $request->department_id,
            'role_id' => $request->role_id,
            'institution_id' => Auth::user()->institution_id,
            'location' => $request->location,
            'bio' => $request->bio,
            'timezone' => $request->timezone,
            'language' => $request->language,
            'is_active' => true
        ]);

        $this->sendNotification('user_created', $user->email, [
            'user_name' => $user->first_name . ' ' . $user->last_name,
            'email' => $user->email,
            'password' => $request->password,
        ]);

        $token = $user->createToken('auth')->plainTextToken;
        $user->load(['department', 'role']);

        return $this->successResponse([
            'token' => $token,
            'user' => $user
        ], 'User registered successfully');
    }

    public function login(Request $request)
    {
        $key = 'login:' . $request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);

            $this->logFailedLogin($request->email, 'rate_limit_exceeded');

            return response()->json([
                'status' => false,
                'message' => 'Too many login attempts. Try again in ' . gmdate("i:s", $seconds)
            ], 429);
        }

        RateLimiter::hit($key, 60);

        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            $this->logFailedLogin($request->email, 'invalid_credentials');

            return $this->errorResponse('The email address or password you entered is incorrect. Pleas check and try again.', 401);
        }

        // if ($user->must_change_password) {
        //     return response()->json([
        //         'status' => false,
        //         'message' => 'You must change your password before logging in',
        //         'must_change_password' => true,
        //         'user_id' => $user->id
        //     ], 403);
        // }

        // if ($user->default_password ?? false) {
        //     $this->logFailedLogin($request->email, 'default_password_not_changed');

        //     return $this->errorResponse('Invalid credentials', 419);
        // }

        $user->update(['last_login_at' => now()]);

        $token = $user->createToken('auth')->plainTextToken;
        $user->load(['department', 'role']);

        $this->logLogin($user, 'email_password');

        $this->sendNotification('login', $user->email, [
            'user_name' => $user->first_name . ' ' . $user->last_name,
            'login_time' => now()->format('Y-m-d H:i:s'),
            'ip_address' => $request->ip()
        ]);

        // Get role name and add it to user data
        $role = $user->role ? $user->role->name : null;

        // Prepare user data
        $userData = [
            'id' => $user->id,
            'first_name' => $user->first_name,
            'middle_name' => $user->middle_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'must_change_password' => $user->must_change_password,
            'last_login_at' => $user->last_login_at,
            'department_id' => $user->department_id,
            'institution_id' => $user->institution_id,
            'is_active' => $user->is_active,
            'avatar' => $user->avatar_url,
            'email_verified_at' => $user->email_verified_at,
            'created_at' => $user->created_at,
            'updated_at' => $user->updated_at,
            'department' => $user->department,
            'role' => $role ? strtolower($role) : null,
            'role_id' => $user->role_id,
        ];

        return $this->successResponse([
            'token' => $token,
            'user' => $userData
        ], 'Logged in successfully');
    }

    public function resetPasswordRequest(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => ['required', 'email'],
        ]);

        if ($validator->fails()) {
            return $this->errorResponse($validator->errors()->first(), 422);
        }

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return $this->errorResponse('User not found', 404);
        }

        $token = Password::createToken($user);

        $this->sendNotification('reset_password_request', $user->email, [
            'user_name' => $user->first_name . ' ' . $user->last_name,
            'reset_token' => $token,
            'reset_url' => config('app.frontend_url') . '/reset-password?token=' . $token . '&email=' . urlencode($user->email)
        ]);

        $this->logPasswordResetRequest($user);

        return $this->successResponse([
            'email' => $user->email,
        ], 'Password reset link sent successfully');
    }

    public function resetPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => ['required', 'email'],
            'token' => ['required', 'string'],
            'password' => ['required', 'confirmed', 'min:8'],
        ]);

        if ($validator->fails()) {
            return $this->errorResponse($validator->errors()->first(), 422);
        }

        $validated = $validator->validated();

        $status = Password::reset(
            $validated,
            function ($user, $password) {
                $user->forceFill([
                    'password' => Hash::make($password),
                ])->save();

                $user->tokens()->delete();

                $this->logPasswordReset($user);
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return $this->successResponse(['email' => $validated['email']], __($status));
        } else {
            $this->logPasswordResetFailed($validated['email'], $status);

            return $this->errorResponse(__($status), 400);
        }
    }

    public function logout(Request $request)
    {
        $user = $request->user();

        $this->logLogout($user);

        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'status' => true,
            'message' => 'Logged out successfully'
        ]);
    }

    public function forgotPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:users,email',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse($validator->errors()->first(), 422);
        }

        $user = User::where('email', $request->email)->first();
        $otp = PasswordResetOtp::generateOtp();

        PasswordResetOtp::where('email', $request->email)->delete();
        PasswordResetOtp::create([
            'email' => $request->email,
            'otp' => $otp,
            'expires_at' => now()->addMinutes(10),
        ]);

        $this->sendNotification('forgot_password_otp', $user->email, [
            'user_name' => $user->first_name . ' ' . $user->last_name,
            'otp' => $otp,
        ]);

        return $this->successResponse([
            'email' => $user->email,
        ], 'OTP sent to your email');
    }

    public function verifyOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'otp' => 'required|string|size:6',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse($validator->errors()->first(), 422);
        }

        $otpRecord = PasswordResetOtp::where('email', $request->email)
            ->where('otp', $request->otp)
            ->first();

        if (!$otpRecord || $otpRecord->isExpired()) {
            return $this->errorResponse('Invalid or expired OTP', 400);
        }

        return $this->successResponse([
            'email' => $request->email,
            'otp_verified' => true,
        ], 'OTP verified successfully');
    }

    public function resetPasswordWithOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'otp' => 'required|string|size:6',
            'password' => 'required|confirmed|min:8',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse($validator->errors()->first(), 422);
        }

        $otpRecord = PasswordResetOtp::where('email', $request->email)
            ->where('otp', $request->otp)
            ->first();

        if (!$otpRecord || $otpRecord->isExpired()) {
            return $this->errorResponse('Invalid or expired OTP', 400);
        }

        $user = User::where('email', $request->email)->first();
        $user->update(['password' => Hash::make($request->password)]);
        $user->tokens()->delete();

        $otpRecord->delete();

        return $this->successResponse([
            'email' => $user->email,
        ], 'Password reset successfully');
    }

    public function forceChangePassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'old_password' => 'required',
            'password' => 'required|confirmed|min:8',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse($validator->errors()->first(), 422);
        }

        $user = User::find($request->user_id);

        if (!Hash::check($request->old_password, $user->password)) {
            return $this->errorResponse('Old password is incorrect', 401);
        }

        $user->update([
            'password' => Hash::make($request->password),
            'must_change_password' => false
        ]);

        return $this->successResponse(null, 'Password changed successfully. You can now login.');
    }

    public function getCurrentUser(Request $request)
    {
        $user = $request->user()->load(['department', 'role']);
        $token = $request->bearerToken();

        // Get memo stats
        $memoService = app(\App\Services\MemoService::class);
        $memoStats = $memoService->getUserMemoStats($user->id, $token);

        $role = $user->role ? $user->role->name : null;

        $userData = [
            'id' => $user->id,
            'first_name' => $user->first_name,
            'middle_name' => $user->middle_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'last_login_at' => $user->last_login_at,
            'department_id' => $user->department_id,
            'institution_id' => $user->institution_id,
            'is_active' => $user->is_active,
            'avatar' => $user->avatar_url,
            'email_verified_at' => $user->email_verified_at,
            'created_at' => $user->created_at,
            'updated_at' => $user->updated_at,
            'department' => $user->department,
            'role' => $role ? strtolower($role) : null,
            'role_id' => $user->role_id,
            'sent_memos_count' => $memoStats['sent_memos_count'],
            'pending_action_count' => $memoStats['pending_action_count'],
        ];

        return $this->successResponse($userData, 'User data retrieved successfully');
    }
}
