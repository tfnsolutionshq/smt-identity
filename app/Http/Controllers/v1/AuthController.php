<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;
use App\Models\User;
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

class AuthController extends Controller
{
    use ApiResponse, LogsActivity;

    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|confirmed|min:8',
            'department_id' => 'required|exists:departments,id',
            'role_id' => 'required|exists:roles,id',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse($validator->errors()->first(), 422);
        }

        $user = User::create([
            'first_name' => $request->first_name,
            'middle_name' => $request->middle_name,
            'last_name' => $request->last_name,
            'email' => $request->email,
            'password' => $request->password,
            'department_id' => $request->department_id,
            'role_id' => $request->role_id,
            'institution_id' => Auth::user()->institution_id,
            'is_active' => true
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

        if ($user->default_password ?? false) {
            $this->logFailedLogin($request->email, 'default_password_not_changed');

            return $this->errorResponse('Invalid credentials', 419);
        }

        $user->update(['last_login_at' => now()]);

        $token = $user->createToken('auth')->plainTextToken;
        $user->load(['department', 'role']);

        $this->logLogin($user, 'email_password');

        return $this->successResponse([
            'token' => $token,
            'user' => $user
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

        $validated = $validator->validated();

        $status = Password::sendResetLink($validated);

        if ($status === Password::RESET_LINK_SENT) {
            $user = User::where('email', $validated['email'])->first();
            if ($user) {
                $this->logPasswordResetRequest($user);
            }

            return $this->successResponse([
                'email' => $validated['email'],
            ], __($status));
        } else {
            $this->logPasswordResetFailed($validated['email'], $status);

            return $this->errorResponse(__($status), 400);
        }
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
}
