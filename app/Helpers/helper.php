<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;

if (!function_exists('current_user')) {
    /**
     * Get current authenticated user with proper typing
     *
     * @return User|null
     */
    function current_user(): ?User
    {
        /** @var User $user */
        $user = Auth::user();
        return $user;
    }
}


if (!function_exists('is_admin')) {
    /**
     * Check if current user is admin
     *
     * @return bool
     */
    function is_admin(): bool
    {
        return current_user() && current_user()->hasRole('admin');
    }
}
