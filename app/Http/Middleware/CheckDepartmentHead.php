<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckDepartmentHead
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user || !$user->role || !in_array($user->role->name, ['Admin', 'Department Head', 'HOD', 'Hod', 'Head of Department', 'hod'])) {
            return response()->json(['error' => 'Unauthorized. Admin or Department Head role required.'], 403);
        }

        return $next($request);
    }
}
