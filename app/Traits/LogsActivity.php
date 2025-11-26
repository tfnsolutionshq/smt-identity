<?php

namespace App\Traits;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

trait LogsActivity
{
    /**
     * Simple method to log activity
     */
    protected function logActivity(string $action, string $description, array $properties = [])
    {
        $baseUrl = rtrim(config('services.setting.base_url'), '/');
        $url = "{$baseUrl}/logs"; // Make sure this is correct

        $payload = [
            'user_id' => $properties['user_id'] ?? 'unknown',
            'institution_id' => $properties['institution_id'] ?? 'unknown',
            'service_source' => 'identity-service',
            'action' => $action,
            'description' => $description,
            'properties' => array_merge([
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ], $properties)
        ];

        try {
            $response = Http::timeout(3)->post($url, $payload);

            if (!$response->successful()) {
                Log::warning("Failed to log activity: {$response->status()} - {$response->body()}");
                return false;
            }

            return true;
        } catch (\Throwable $e) {
            Log::error('Error logging activity: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Specific methods for common actions
     */
    protected function logLogin($user, $loginMethod = 'email_password')
    {
        return $this->logActivity(
            'login',
            'User logged in successfully',
            [
                'user_id' => $user->id,
                'name' => $user->first_name . " " . $user->last_name,
                'institution_id' => $user->institution_id,
                'login_method' => $loginMethod,
                'user_email' => $user->email
            ]
        );
    }

    protected function logLogout($user)
    {
        return $this->logActivity(
            'logout',
            'User logged out',
            [
                'user_id' => $user->id,
                'name' => $user->first_name . " " . $user->last_name,
                'institution_id' => $user->institution_id,
                'user_email' => $user->email
            ]
        );
    }

    protected function logFailedLogin($email, $reason = 'invalid_credentials')
    {
        return $this->logActivity(
            'login_failed',
            'Failed login attempt',
            [
                'attempted_email' => $email,
                'reason' => $reason,
                'user_id' => 'unknown',
                'institution_id' => 'unknown'
            ]
        );
    }

    protected function logPasswordResetRequest($user)
    {
        return $this->logActivity(
            'password_reset_requested',
            'User requested password reset',
            [
                'user_id' => $user->id,
                'name' => $user->first_name . " " . $user->last_name,
                'institution_id' => $user->institution_id,
                'user_email' => $user->email,
                'reset_method' => 'email'
            ]
        );
    }

    protected function logPasswordReset($user)
    {
        return $this->logActivity(
            'password_reset',
            'User password reset successfully',
            [
                'user_id' => $user->id,
                'name' => $user->first_name . " " . $user->last_name,
                'institution_id' => $user->institution_id,
                'user_email' => $user->email,
                'password_changed' => true
            ]
        );
    }

    protected function logPasswordResetFailed($email, $reason)
    {
        return $this->logActivity(
            'password_reset_failed',
            'Failed to reset password',
            [
                'user_email' => $email,
                'reason' => $reason,
                'user_id' => 'unknown',
                'institution_id' => 'unknown'
            ]
        );
    }
}
